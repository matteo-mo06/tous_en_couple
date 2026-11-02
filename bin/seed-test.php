<?php
declare(strict_types=1);
/**
 * Joueurs fictifs pour tester : php bin/seed-test.php [--cloturer]
 * Ajoute Indy, Narciso, Romane et Cali (mot de passe « test-test ») à l'année en cours, avec des déclarations
 * aléatoires (statut, étapes, tentative, bluff, absence).
 * Avec --cloturer : simule en plus la soirée (présences, accusations, prix) et CLÔTURE l'année, pour remplir
 * classement, historique et statuts. À réserver à une base de test.
 */
require __DIR__ . '/../src/autoload.php';
date_default_timezone_set(TEC\Config::get('app.timezone', 'Europe/Paris'));

use TEC\Db;
use TEC\Repo\{Entries, Players, Soiree, Years};

TEC\Migrator::run();
$year = Years::current();
if (!$year || $year['status'] !== 'open') {
    fwrite(STDERR, "Il faut une année ouverte aux déclarations (lance d'abord bin/install.php).\n");
    exit(1);
}
$yid = (int)$year['id'];
$rand = fn(array $a) => $a[array_rand($a)];

// --- joueurs
$ids = [];
foreach (['Indy', 'Narciso', 'Romane', 'Cali'] as $name) {
    $login = strtolower($name);
    $ex = Db::one('SELECT id FROM players WHERE login = ?', [$login]);
    if ($ex) {
        $ids[$name] = (int)$ex['id'];
        continue;
    }
    [$id, $err] = Players::create($name, $login, 'test-test', 'player', $rand(['Paris', 'Lyon', 'Marseille', 'Lille', 'Bordeaux', 'Bruxelles']), false, null, (int)$year['year']);
    if ($err) {
        fwrite(STDERR, "$name : $err\n");
        exit(1);
    }
    $ids[$name] = $id;
    Years::addParticipant($yid, $id);
    echo "Créé : $name ($login / test-test)\n";
}

// --- déclarations : les 4 profils sont répartis au hasard entre les joueurs, pour que la simulation montre
// des situations variées (couple, étape de vie, tentative, bluff, parfois une absence) sans cas absurdes.
$partners = ['Léa', 'Tom', 'Sacha', 'Jade', 'Noé', 'Maya', 'Eliott', 'Lina'];
shuffle($partners);
$fakes = ['Zoé', 'Camille', 'Hugo', 'Alix'];
$details = ['Club de randonnée', 'Cours de danse', 'Soirée jeux de société', 'Atelier poterie'];
$profiles = ['couple_step', 'couple', 'single_bluff', 'single_attempt'];
shuffle($profiles);
$i = 0;
foreach ($ids as $name => $pid) {
    if (Entries::forPlayer($yid, $pid)) {
        continue;
    }
    $profile = $profiles[$i++ % count($profiles)];
    if (str_starts_with($profile, 'couple')) {
        Entries::create($yid, $pid, 'relation', ['status' => 'couple', 'partner' => array_pop($partners)]);
        if ($profile === 'couple_step') {
            Entries::create($yid, $pid, 'step', ['step' => $rand(['move_in', 'buy', 'engaged']), 'date' => date('Y-m-d')]);
        }
    } else {
        Entries::create($yid, $pid, 'relation', ['status' => 'single']);
        if ($profile === 'single_bluff') {
            Entries::create($yid, $pid, 'bluff', ['fake_partner' => $rand($fakes)]);
        } else {
            $k = $rand(array_keys(Entries::ATTEMPTS));
            Entries::create($yid, $pid, 'attempt', ['kind' => $k, 'detail' => $k === 'other' ? $rand($details) : '']);
            if (random_int(0, 2) === 0) {
                Entries::create($yid, $pid, 'absence', ['reason' => $rand(['Examen le même jour', 'Voyage déjà réservé', 'Mariage dans la famille'])]);
            }
        }
    }
    echo "Déclarations ajoutées pour $name ($profile).\n";
}

// --- journal : quelques événements dans l'année, dont un privé
$ev = [['crush', 'Crush sur un collègue', 'Rien de concret… pour l\'instant'], ['situationship', 'Situationship avec Alex', ''], ['date', 'Double date avec des amis', 'Resto + ciné'],
    ['couple', 'Week-end à deux', ''], ['travel', 'Voyage à Lisbonne', 'Une semaine de soleil'], ['memory', 'Soirée mémorable', ''], ['breakup', 'Fin d\'une histoire courte', '']];
foreach ($ids as $name => $pid) {
    if (TEC\Db::val('SELECT COUNT(*) FROM journal WHERE player_id = ?', [$pid])) {
        continue;
    }
    foreach (array_rand($ev, 3) as $k) {
        [$kind, $title, $note] = $ev[$k];
        $start = date('Y-m-d', strtotime('-' . random_int(10, 300) . ' days'));
        $end = random_int(0, 2) === 0 ? date('Y-m-d', strtotime($start . ' +' . random_int(2, 9) . ' days')) : '';
        TEC\Repo\Journal::create($pid, $yid, ['kind' => $kind, 'title' => $title, 'note' => $note, 'start_date' => $start, 'end_date' => $end, 'shared' => random_int(0, 4) ? 1 : 0]);
    }
    echo "Journal ajouté pour $name.\n";
}

if (!in_array('--cloturer', $argv, true)) {
    echo "Terminé. Ajoute --cloturer pour simuler aussi la soirée et la clôture.\n";
    exit(0);
}

// --- soirée simulée puis clôture
$parts = Years::participants($yid);
$all = array_map(fn($p) => (int)$p['player_id'], $parts);
$absentWithReason = [];
foreach (Db::all("SELECT player_id FROM entries WHERE year_id = ? AND type = 'absence'", [$yid]) as $r) {
    $absentWithReason[(int)$r['player_id']] = true;
}
$present = [];
foreach ($all as $pid) {
    $isPresent = !isset($absentWithReason[$pid]);
    $present[$pid] = $isPresent;
    Soiree::setAttendance($yid, $pid, $isPresent ? 1 : 0, $isPresent && random_int(0, 7) === 0, 'present', random_int(0, 3));
}
$host = $rand(array_keys(array_filter($present)));
Db::update('game_years', ['meeting_date' => $year['year'] . '-07-24', 'meeting_place' => 'Chez ' . Players::find($host)['name'], 'host_id' => $host, 'status' => 'soiree'], 'id = ?', [$yid]);
foreach (Db::all("SELECT player_id FROM entries WHERE year_id = ? AND type = 'bluff'", [$yid]) as $r) {
    foreach ($all as $acc) {
        if ($acc !== (int)$r['player_id'] && $present[$acc] && random_int(0, 9) < 5) {
            Soiree::toggleAccusation($yid, $acc, (int)$r['player_id']);
        }
    }
}
foreach (Db::all("SELECT id, player_id FROM entries WHERE year_id = ? AND type = 'absence'", [$yid]) as $r) {
    foreach ($all as $v) {
        if ($v !== (int)$r['player_id']) {
            Soiree::vote('absence', (int)$r['id'], $v, random_int(0, 4) ? 'yes' : 'no');
        }
    }
}
$here = array_keys(array_filter($present));
foreach (['Meilleure histoire', 'Meilleur déguisement'] as $label) {
    Soiree::addPrize($yid, $label, 2);
}
foreach (Soiree::prizes($yid) as $pz) {
    Soiree::setPrizeWinner((int)$pz['id'], (int)$rand($here));
}
$c = TEC\YearService::compute($yid);
if ($c['problems']) {
    fwrite(STDERR, "Clôture impossible : " . implode(' / ', $c['problems']) . "\n");
    exit(1);
}
$admin = Db::one("SELECT id FROM players WHERE role = 'admin' ORDER BY id LIMIT 1");
$r = TEC\YearService::close($yid, (int)$admin['id']);
echo $r['ok'] ? "Année {$year['year']} simulée et clôturée ; l'année suivante est ouverte.\n" : 'Échec : ' . implode(' / ', $r['errors']) . "\n";
