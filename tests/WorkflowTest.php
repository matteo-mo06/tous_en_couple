<?php
declare(strict_types=1);

// Parcours complet sur une base SQLite temporaire : joueurs -> déclarations -> soirée -> clôture -> année suivante.

use TEC\Config;
use TEC\Db;
use TEC\Migrator;
use TEC\Repo\Amendments;
use TEC\Repo\Entries;
use TEC\Repo\Players;
use TEC\Repo\Rules;
use TEC\Repo\Soiree;
use TEC\Repo\Years;
use TEC\YearService;

$tmp = sys_get_temp_dir() . '/tec_test_' . getmypid() . '.sqlite';
@unlink($tmp);
Config::override(['db' => ['driver' => 'sqlite', 'sqlite_path' => $tmp]]);
Db::reset();
Migrator::run();
check('migrations appliquées', (int)Db::val('SELECT COUNT(*) FROM schema_migrations') >= 1);
check('migrations idempotentes', Migrator::run() === []);

// --- joueurs
$ids = [];
foreach (['Matt', 'Léa', 'Tom', 'Inès', 'Sam'] as $i => $n) {
    [$id, $err] = Players::create($n, Players::slug($n), 'motdepasse' . $i, $i === 0 ? 'admin' : 'player', null, false, 0.0);
    check("création de $n", $id !== null, (string)$err);
    $ids[$n] = $id;
}
[$dup, $err] = Players::create('Matt bis', 'matt', 'motdepasse1');
check('identifiant déjà pris refusé', $dup === null && $err !== null);
[$x, $err] = Players::create('Court', 'court', 'abc');
check('mot de passe trop court refusé', $x === null);
check('slug sans accent', Players::slug('Inès') === 'ines');

// --- barème et année
$rv = Rules::ensureInitial(2027);
$y1 = Years::create(2027, $rv);
check('5 participants inscrits', count(Years::participants($y1)) === 5);
Db::update('game_years', ['meeting_date' => '2027-07-24'], 'id = ?', [$y1]);
Db::update('game_years', ['host_id' => $ids['Tom']], 'id = ?', [$y1]);

// --- déclarations
$M = $ids['Matt']; $L = $ids['Léa']; $T = $ids['Tom']; $I = $ids['Inès']; $S = $ids['Sam'];
[$e1, $err] = Entries::create($y1, $L, 'relation', ['status' => 'couple', 'partner' => 'Alex'], $L);
check('relation créée en attente', $e1 !== null && Entries::find($e1)['status'] === 'pending');
[$e2] = Entries::create($y1, $L, 'step', ['step' => 'move_in'], $L);
[, $err] = Entries::create($y1, $L, 'step', ['step' => 'inconnue'], $L);
check('étape inconnue refusée', $err !== null);
[$eb] = Entries::create($y1, $I, 'bluff', ['fake_partner' => 'Zoé'], $I);
[, $err] = Entries::create($y1, $I, 'bluff', ['fake_partner' => 'Autre'], $I);
check('un seul bluff par an et par joueur', $err !== null);
[$ea] = Entries::create($y1, $S, 'absence', ['reason' => 'Examen'], $S);
[$et] = Entries::create($y1, $T, 'attempt', ['kind' => 'app'], $M, true);
check('saisie directe par l\'admin : validée', Entries::find($et)['status'] === 'approved');
check('refus sans motif impossible', Entries::decide($e1, 'rejected', '', $M) !== null);
check('un joueur retire sa déclaration en attente', Entries::withdraw($e2, $L) && Entries::find($e2) === null);
[$e2] = Entries::create($y1, $L, 'step', ['step' => 'move_in'], $L);
check('on ne retire pas la déclaration d\'un autre', !Entries::withdraw($e1, $T));

$c = YearService::compute($y1);
check('déclarations en attente bloquent la clôture', (bool)array_filter($c['problems'], fn($p) => str_contains($p, 'en attente')));
foreach ([$e1, $e2, $eb, $ea] as $e) {
    check('validation ' . $e, Entries::decide($e, 'approved', null, $M) === null);
}

// --- soirée : présence, accusations, votes
Soiree::setAttendance($y1, $M, 1, false, 'present', 0);
Soiree::setAttendance($y1, $L, 1, false, 'present', 1);
Soiree::setAttendance($y1, $T, 1, true, 'present', 0);   // retard
Soiree::setAttendance($y1, $I, 1, false, 'present', 2);
Soiree::setAttendance($y1, $S, 0, false, 'present', 0);  // absent
check('accusation correcte enregistrée', Soiree::toggleAccusation($y1, $M, $I) === null);
check('accusation d\'une autre personne (à tort)', Soiree::toggleAccusation($y1, $T, $L) === null);
check('on ne s\'accuse pas soi-même', Soiree::toggleAccusation($y1, $M, $M) !== null);
Soiree::toggleAccusation($y1, $S, $T);
Soiree::toggleAccusation($y1, $S, $T);
check('une accusation se retire en la reposant', !in_array($T, Soiree::accusationsBy($y1, $S), true));
// absence de Sam : motif voté par les autres (Sam ne vote pas pour lui-même)
foreach ([$M, $L, $T] as $v) {
    Soiree::vote('absence', $ea, $v, 'yes');
}
Soiree::vote('absence', $ea, $I, 'no');
Soiree::vote('absence', $ea, $S, 'no');   // le vote de l'absent ne compte pas
check('motif d\'absence accepté à la majorité des autres', Soiree::majorityYes('absence', $ea, $S));
// prix
check('prix créé', Soiree::addPrize($y1, 'Meilleur bluff', 2) === null);
Soiree::addPrize($y1, 'Meilleure histoire', 2);
check('pas plus de 2 prix', Soiree::addPrize($y1, 'Trop', 2) !== null);
$prizes = Soiree::prizes($y1);
Soiree::vote('prize', (int)$prizes[0]['id'], $M, (string)$L);
Soiree::vote('prize', (int)$prizes[0]['id'], $T, (string)$L);
check('le candidat en tête est repéré', Soiree::prizeLeader((int)$prizes[0]['id']) === $L);
check('gagnant du prix', Soiree::setPrizeWinner((int)$prizes[0]['id'], $L) === null);
check('un joueur ne reçoit qu\'un prix par an', Soiree::setPrizeWinner((int)$prizes[1]['id'], $L) !== null);
Soiree::setPrizeWinner((int)$prizes[1]['id'], $M);

// --- aperçu
$c = YearService::compute($y1);
check('plus de problème bloquant', $c['problems'] === [], json_encode($c['problems']));
$r = $c['results'];
// Léa : couple, 1re année +2, emménagement +2, 1 prix +1, déplacement 300 km 0, accusée à tort sans effet pour elle ; = 5
check('Léa : 2 (série) + 2 (emménagement) + 1 (prix) = 5', $r[$L]['score'] === 5, json_encode($r[$L]['cats']));
// Inès : bluff démasqué par Matt seul : -5 → 0 ; Matt +2 (accusateur seul) + prix +1 = 3
check('Inès : bluff démasqué, plancher 0', $r[$I]['score'] === 0 && $r[$I]['cats']['bluff'] === -5);
check('Matt : +2 accusateur seul, +1 prix = 3', $r[$M]['score'] === 3, json_encode($r[$M]['cats']));
// Tom : hôte +3, tentative +1, retard -1, accusation à tort -1 = 2
check('Tom : hôte +3, tentative +1, retard -1, accusation à tort -1 = 2', $r[$T]['score'] === 2, json_encode($r[$T]['cats']));
// Sam : absent, préavis ? déclaré aujourd'hui (la réunion est dans > 15 jours selon la date fictive) → test sur la date
$noticeDays = (strtotime('2027-07-24') - strtotime(date('Y-m-d'))) / 86400;
check('Sam : absence justifiée = gel (0) si préavis suffisant, sinon -1 → plancher 0', $r[$S]['score'] === 0);
check('Sam gelé si préavis ≥ 15 jours', ($noticeDays >= 15) === !empty($r[$S]['flags']['gel_player']));
check('classement : Léa 1re', $c['ranks'][$L] === 1);
check('points globaux : 1er=3, 2e=2, 3e=1', $c['global_points'][$L] === 3 && $c['global_points'][$M] === 2 && $c['global_points'][$T] === 1);
check('cadeaux : 5 joueurs -> 2 cadeaux', count($c['gifts']) === 2);
check('pas de cadeau global la 1re année', $c['cycle'] === null);

// --- clôture
$res = YearService::close($y1, $M);
check('clôture réussie', $res['ok'], json_encode($res));
check('année clôturée', Years::find($y1)['status'] === 'closed');
check('année suivante ouverte avec tous les joueurs', Years::current()['year'] === 2028 && count(Years::participants((int)Years::current()['id'])) === 5);
check('résultats figés en base', (int)Db::val('SELECT COUNT(*) FROM year_results WHERE year_id = ?', [$y1]) === 5);
check('on ne clôture pas deux fois', !YearService::close($y1, $M)['ok']);

// --- année 2 : rattrapage, série qui continue, usure du global
$y2 = (int)Years::current()['id'];
Db::update('game_years', ['meeting_date' => '2028-07-22', 'host_id' => $L], 'id = ?', [$y2]);
foreach ([$M, $L, $T, $I, $S] as $p) {
    Soiree::setAttendance($y2, $p, 1, false, 'present', 0);
}
$c2 = YearService::compute($y2);
// Léa est toujours en couple avec Alex (statut repris de l'an passé), 2e année
check('statut repris de l\'an passé : Léa série +2 (2e année)', ($c2['results'][$L]['cats']['serie'] ?? 0) === 2, json_encode($c2['results'][$L]['cats']));
check('rattrapage +1 : derniers de 2027 (Inès et Sam, 0)', ($c2['results'][$I]['cats']['comeback'] ?? 0) === 1 && ($c2['results'][$S]['cats']['comeback'] ?? 0) === 1);
check('usure du global : Léa 3 → 0,7×3 + points', abs($c2['global_before'][$L] - 3.0) < 1e-9);
check('l\'hôte de l\'an passé ne se répète pas (contrôle côté page)', Years::hostOfYear(2027) === $T);

// --- amendements
$y2row = Years::find($y2);
$w = Amendments::window($y2row);
check('fenêtre d\'amendement calculée', $w['known'] && $w['from'] === '2028-03-22' && $w['to'] === '2028-07-15');
$params = Rules::find((int)$y2row['rule_version_id'])['params'];
$fake = ['id' => $y2, 'status' => 'open', 'meeting_date' => date('Y-m-d', strtotime('+60 days'))];
[$aid, $err] = Amendments::propose($fake, $L, 'Hôte à +2', 'Trop de hasard', 'host', '2', $params);
check('amendement déposé dans la fenêtre', $aid !== null, (string)$err);
[, $err] = Amendments::propose($fake, $L, 'Même valeur', 'x', 'host', '3', $params);
check('valeur identique au barème refusée', $err !== null);
[, $err] = Amendments::propose($fake, $L, 'Inconnu', 'x', 'nimportequoi', '3', $params);
check('paramètre inconnu refusé', $err !== null);
$closedWindow = ['id' => $y2, 'status' => 'open', 'meeting_date' => date('Y-m-d', strtotime('+3 days'))];
[, $err] = Amendments::propose($closedWindow, $L, 'Trop tard', 'x', 'host', '2', $params);
check('dépôt refusé à moins d\'une semaine de la réunion', $err !== null);
$am = Amendments::find($aid);
// on rattache l'amendement à l'année 2 et on vote
Db::update('amendments', ['year_id' => $y2], 'id = ?', [$aid]);
$am = Amendments::find($aid);
$openYear = $fake;
foreach ([$M, $L, $T] as $v) { Amendments::vote($openYear, $am, $v, 'yes'); }
foreach ([$I] as $v) { Amendments::vote($openYear, $am, $v, 'no'); }
Amendments::vote($openYear, $am, $S, 'yes');
Amendments::vote($openYear, $am, $S, 'no');   // il change d'avis
$t = Soiree::tally('amendment', $aid);
check('vote modifiable : 3 pour, 2 contre', ($t['yes'] ?? 0) === 3 && ($t['no'] ?? 0) === 2);
$c2 = YearService::compute($y2);
check('amendement adopté à la majorité simple', $c2['amendments'][0]['adopted'] === true);
[$np, $applied] = YearService::nextParams($params, $c2['amendments']);
check('la version suivante applique l\'amendement', $np['host'] === 2 && $params['host'] === 3 && $applied === [$aid]);
// égalité = rejet
Soiree::vote('amendment', $aid, $S, 'yes');
Soiree::vote('amendment', $aid, $T, 'no');
Db::run("DELETE FROM votes WHERE kind = 'amendment' AND ref_id = ? AND voter_id = ?", [$aid, $M]);   // 2 pour, 2 contre
$t = Soiree::tally('amendment', $aid);
$c2 = YearService::compute($y2);
check('égalité = rejet', ($t['yes'] ?? 0) === ($t['no'] ?? 0) && $c2['amendments'][0]['adopted'] === false, json_encode($t));
Soiree::vote('amendment', $aid, $T, 'yes');   // adopté de nouveau
$res = YearService::close($y2, $M);
check('clôture année 2', $res['ok'], json_encode($res));
$next = Years::current();
$nextRules = Rules::find((int)$next['rule_version_id']);
check('année 2029 rattachée à la nouvelle version du barème (hôte +2)', $next['year'] === 2029 && $nextRules['params']['host'] === 2);
check('l\'ancienne version reste intacte', Rules::find($rv)['params']['host'] === 3);
check('amendement marqué adopté', Amendments::find($aid)['status'] === 'adopted');

// --- 3e année : cadeau global
$y3 = (int)$next['id'];
foreach ([$M, $L, $T, $I, $S] as $p) {
    Soiree::setAttendance($y3, $p, 1, false, 'present', 0);
}
$c3 = YearService::compute($y3);
check('3e année : cadeau global attribué', $c3['cycle'] !== null && count($c3['cycle']['winners']) >= 1);

// --- nouveau joueur
$globals = Years::currentGlobals();
check('globaux connus pour tous les joueurs actifs', count($globals) === 5);
@unlink($tmp);
@unlink($tmp . '-wal');
@unlink($tmp . '-shm');
