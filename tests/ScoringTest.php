<?php
declare(strict_types=1);

// Tests du moteur de calcul. Lancer : php tests/run.php
// Cas repris du simulateur (test_rules.py), adaptés au barème final.

use TEC\Scoring\Defaults;
use TEC\Scoring\Ranking;
use TEC\Scoring\YearScorer;

$P = Defaults::params();

/** Une table de 5 joueurs « neutres » (célibataires présents) dont on change ce qu'on teste. */
function table(array $over = [], int $n = 5): array
{
    $pl = [];
    for ($i = 1; $i <= $n; $i++) {
        $pl[$i] = ['id' => $i] + ($over[$i] ?? []);
    }
    return $pl;
}

function couple(int $years, string $partner = 'Alex', array $extra = []): array
{
    return $extra + [
        'status' => 'couple', 'partner' => $partner,
        'state' => ['series' => $years, 'partner' => mb_strtolower($partner), 'presented' => $years > 0,
            'visio' => 0, 'steps_done' => [], 'deferred' => 0, 'fake' => null],
    ];
}

// ---- 1. Niveaux de série : 2, 2, 3, 4, 5, 5, 5
foreach ([0 => 2, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 5, 6 => 5] as $y => $exp) {
    $r = YearScorer::score($P, table([1 => couple($y)]));
    check("série année " . ($y + 1), ($r[1]['cats']['serie'] ?? 0) === $exp, "+$exp");
}

// ---- 2. Partenaire absent = gel : 0 point, série conservée
$r = YearScorer::score($P, table([1 => couple(2, 'Alex', ['partner_presence' => 'absent'])]));
check('gel partenaire absent', $r[1]['score'] === 0 && $r[1]['state']['series'] === 2 && !empty($r[1]['flags']['gel_partner']));

// ---- 3. Visio : autorisée si présenté en personne et moins de 2 années de suite
$r = YearScorer::score($P, table([1 => couple(2, 'Alex', ['partner_presence' => 'visio'])]));
check('visio autorisée : pas de gel, série +3', ($r[1]['cats']['serie'] ?? 0) === 3 && $r[1]['state']['visio'] === 1);
$c = couple(2, 'Alex', ['partner_presence' => 'visio']);
$c['state']['visio'] = 2;
$r = YearScorer::score($P, table([1 => $c]));
check('3e année de visio de suite : compte comme absence (gel)', $r[1]['score'] === 0 && !empty($r[1]['flags']['gel_partner']));
$c = couple(0, 'Alex', ['partner_presence' => 'visio']);
$r = YearScorer::score($P, table([1 => $c]));
check('visio refusée si jamais présenté en personne', $r[1]['score'] === 0 && !empty($r[1]['flags']['gel_partner']));
$c = couple(2, 'Alex', ['partner_presence' => 'present']);
$c['state']['visio'] = 2;
$r = YearScorer::score($P, table([1 => $c]));
check('une présence réelle remet le compteur de visio à zéro', $r[1]['state']['visio'] === 0);

// ---- 4. Bluff démasqué par un seul : -5 pour lui, +2 pour l'accusateur
$r = YearScorer::score($P, table([1 => ['bluff' => 'Faux'], 2 => ['accusations' => [1]]]));
check('bluff démasqué par un seul : -5 -> plancher 0', $r[1]['cats']['bluff'] === -5 && $r[1]['score'] === 0);
check('seul accusateur correct : +2', ($r[2]['cats']['solo'] ?? 0) === 2 && $r[2]['score'] === 2);

// ---- 5. Bluff démasqué par deux : personne ne gagne le bonus
$r = YearScorer::score($P, table([1 => ['bluff' => 'Faux'], 2 => ['accusations' => [1]], 3 => ['accusations' => [1]]]));
check('bluff démasqué à deux : pas de bonus', !isset($r[2]['cats']['solo']) && !isset($r[3]['cats']['solo']) && $r[1]['cats']['bluff'] === -5);

// ---- 6. Bluff réussi +3, puis tromperie -10 : plancher
$r = YearScorer::score($P, table([1 => ['bluff' => 'Faux', 'cheating' => true]]));
check('bluff +3 / tromperie -10 : total -7, plancher 0', $r[1]['raw'] === -7 && $r[1]['score'] === 0);
$P2 = Defaults::with($P, 'floor_min', -100);
$r = YearScorer::score($P2, table([1 => ['bluff' => 'Faux', 'cheating' => true]]));
check('sans plancher : -7', $r[1]['score'] === -7);

// ---- 7. Accusation à tort : -1
$r = YearScorer::score($P, table([1 => couple(1), 2 => ['accusations' => [1]]]));
check('accusation à tort : -1', ($r[2]['cats']['accuse'] ?? 0) === -1);
$r = YearScorer::score($P, table([2 => ['accusations' => [3, 4]]]));
check('deux accusations à tort : -2', ($r[2]['cats']['accuse'] ?? 0) === -2);

// ---- 8. Absence : gel / pénalité / fuite
$r = YearScorer::score($P2, table([1 => ['present' => false, 'absence_notice_ok' => true, 'absence_valid' => true]]));
check('absent prévenu + motif valable : gel, 0', $r[1]['score'] === 0 && !empty($r[1]['flags']['gel_player']));
$r = YearScorer::score($P2, table([1 => ['present' => false]]));
check('absent sans préavis : -1', $r[1]['score'] === -1);
$r = YearScorer::score($P2, table([1 => ['present' => false, 'absence_notice_ok' => true, 'absence_valid' => false]]));
check('absent prévenu mais motif refusé : -1', $r[1]['score'] === -1);
$br = [['kind' => 'simple', 'couple_continues' => false, 'good_terms' => false]];
$r = YearScorer::score($P2, table([1 => ['present' => false, 'absence_notice_ok' => true, 'absence_valid' => true, 'breakups' => $br]]));
check('absent légitime + rupture : -2 -1 fuite = -3, pas de gel', $r[1]['score'] === -3 && empty($r[1]['flags']['gel_player']));
$r = YearScorer::score($P2, table([1 => ['present' => false, 'breakups' => $br]]));
check('absent non légitime + rupture : -1 -1 -2 = -4', $r[1]['score'] === -4);

// ---- 9. Gel : la tromperie n'est PAS gelée
$r = YearScorer::score($P2, table([1 => couple(3, 'Alex', ['partner_presence' => 'absent', 'cheating' => true])]));
check('gel partenaire absent + tromperie : -10 appliqué', $r[1]['score'] === -10);
$r = YearScorer::score($P2, table([1 => couple(3, 'Alex', ['partner_presence' => 'absent', 'hidden_breakup' => true])]));
check('gel + rupture cachée : -3 appliqué', $r[1]['score'] === -3);
$ec = [['kind' => 'end_shared', 'couple_continues' => true, 'good_terms' => false]];
$r = YearScorer::score($P2, table([1 => couple(3, 'Alex', ['partner_presence' => 'absent', 'breakups' => $ec])]));
check('gel + fin de vie commune (couple continue) : -3, série gardée', $r[1]['score'] === -3 && $r[1]['state']['series'] === 3);

// ---- 10. Étape de vie sous gel : reportée à la réunion suivante
$r = YearScorer::score($P, table([1 => couple(3, 'Alex', ['partner_presence' => 'absent', 'steps' => ['married']])]));
check('mariage sous gel : 0 cette année, +5 reportés', $r[1]['score'] === 0 && $r[1]['state']['deferred'] === 5);
$c = couple(3);
$c['state']['deferred'] = 5;
$r = YearScorer::score($P, table([1 => $c]));
check('année suivante : série 4e année (+4) + 5 reportés = 9', $r[1]['score'] === 9 && $r[1]['state']['deferred'] === 0);
$r = YearScorer::score($P, table([1 => ['present' => false, 'absence_notice_ok' => true, 'absence_valid' => true] + couple(1, 'Alex', ['steps' => ['buy']])]));
check('absence justifiée : étape reportée aussi', $r[1]['state']['deferred'] === 3 && $r[1]['score'] === 0);

// ---- 11. Hôte et prix
$r = YearScorer::score($P, table([4 => ['host' => true], 2 => ['prizes' => 1], 3 => ['prizes' => 1]]));
$sc = array_map(fn($x) => $x['score'], $r);
check('hôte +3, deux prix +1', $sc === [1 => 0, 2 => 1, 3 => 1, 4 => 3, 5 => 0], json_encode($sc));

// ---- 12. Étapes de vie : une fois par relation, cumulables, enfants répétables
$r = YearScorer::score($P, table([1 => couple(0, 'Alex', ['steps' => ['move_in', 'buy', 'engaged']])]));
check('emménagement+achat+fiançailles = 2+3+3, + série 2', $r[1]['score'] === 10);
$c = couple(1, 'Alex', ['steps' => ['move_in', 'child', 'child']]);
$c['state']['steps_done'] = ['move_in'];
$r = YearScorer::score($P, table([1 => $c]));
check('emménagement déjà compté ignoré, 2 enfants +5 chacun, série 2e année +2', $r[1]['score'] === 12);
$r = YearScorer::score($P, table([1 => ['steps' => ['married']]]));
check('étape sans partenaire : ignorée', $r[1]['score'] === 0);

// ---- 13. Ruptures : un seul malus, série repart
$r = YearScorer::score($P2, table([1 => ['breakups' => $br, 'state' => ['series' => 4, 'partner' => 'alex'] + YearScorer::newState()]]));
check('rupture simple : -2, série à zéro', $r[1]['score'] === -2 && $r[1]['state']['series'] === 0);
$cases = ['divorce' => -5, 'engaged' => -8, 'end_shared' => -3];
foreach ($cases as $kind => $v) {
    $r = YearScorer::score($P2, table([1 => ['breakups' => [['kind' => $kind, 'couple_continues' => false, 'good_terms' => false]]]]));
    check("malus $kind", $r[1]['score'] === $v);
}
$r = YearScorer::score($P2, table([1 => ['breakups' => [['kind' => 'simple', 'couple_continues' => false, 'good_terms' => true]]]]));
check('bons termes : malus annulé', $r[1]['score'] === 0);
$r = YearScorer::score($P2, table([1 => ['breakups' => $br, 'cheating' => true]]));
check('tromperie cumulable avec la rupture : -12', $r[1]['score'] === -12);
$r = YearScorer::score($P2, table([1 => ['breakups' => $br, 'hidden_breakup' => true]]));
check('rupture cachée : -2 -3 = -5', $r[1]['score'] === -5);
// fin de vie commune, le couple continue : série conservée, malus -3
$c = couple(2, 'Alex', ['breakups' => $ec]);
$r = YearScorer::score($P2, table([1 => $c]));
check('fin de vie commune, couple continue : série +3, malus -3, série gardée', $r[1]['score'] === 0 && $r[1]['state']['series'] === 3);
// remise en couple après rupture : série repart
$c = couple(2, 'Alex', ['breakups' => $br]);
$r = YearScorer::score($P2, table([1 => $c]));
check('rupture puis remise ensemble : série repart à +2', ($r[1]['cats']['serie'] ?? 0) === 2);
// nouveau partenaire
$c = couple(4, 'Sam');
$c['state']['partner'] = 'alex';
$r = YearScorer::score($P, table([1 => $c]));
check('changement de partenaire : série repart', ($r[1]['cats']['serie'] ?? 0) === 2 && $r[1]['state']['series'] === 1);

// ---- 14. Tentative, rattrapage, déplacement, retard
$r = YearScorer::score($P, table([1 => ['attempt' => true], 2 => ['prev_last' => true], 3 => ['travel_band' => 2], 4 => ['travel_band' => 1], 5 => ['late' => true]]));
$sc = array_map(fn($x) => $x['score'], $r);
check('tentative +1, rattrapage +1, 1500 km +1, 300 km 0, retard -1->0', $sc === [1 => 1, 2 => 1, 3 => 1, 4 => 0, 5 => 0], json_encode($sc));
$r = YearScorer::score($P2, table([5 => ['late' => true]]));
check('retard -1 (sans plancher)', $r[5]['score'] === -1);
$r = YearScorer::score($P, table([1 => couple(0, 'Alex', ['attempt' => true])]));
check('tentative refusée à un joueur en couple', !isset($r[1]['cats']['attempt']));

// ---- 15. Même faux partenaire deux années de suite
$s1 = YearScorer::score($P, table([1 => ['bluff' => 'Zoé']]))[1];
check('bluff réussi : +3', $s1['score'] === 3 && $s1['state']['fake'] === 'zoé');
$s2 = YearScorer::score($P, table([1 => ['bluff' => 'zoé', 'state' => $s1['state']]]))[1];
check('même faux partenaire 2 ans de suite : +3 +1', $s2['score'] === 4 && $s2['state']['fake'] === null);
$s3 = YearScorer::score($P, table([1 => ['bluff' => 'Zoé', 'state' => $s2['state']]]))[1];
check('3e année : bonus une seule fois', $s3['score'] === 3);
$s4 = YearScorer::score($P, table([1 => ['bluff' => 'Autre', 'state' => $s1['state']]]))[1];
check('faux partenaire différent : pas de bonus', $s4['score'] === 3);
$s5 = YearScorer::score($P, table([1 => ['state' => $s1['state']]]))[1];
check('année sans bluff : la chaîne est coupée', $s5['state']['fake'] === null);
$r = YearScorer::score($P, table([1 => couple(0, 'Alex', ['bluff' => 'Faux'])]));
check('bluff d\'un joueur en couple : ignoré', !isset($r[1]['cats']['bluff']));
// bluff : ne fait ni série ni étape de vie
$r = YearScorer::score($P, table([1 => ['bluff' => 'Faux', 'steps' => ['married']]]));
check('bluff : pas de série ni d\'étape', !isset($r[1]['cats']['serie']) && !isset($r[1]['cats']['steps']));

// ---- 16. Plancher sur le total
$r = YearScorer::score($P, table([1 => ['breakups' => $br]]));
check('plancher : -2 devient 0', $r[1]['score'] === 0 && $r[1]['cats']['floor'] === 2);

// ---- 17. Somme des catégories == score (invariant)
mt_srand(11);
$steps = ['move_in', 'buy', 'engaged', 'pacs', 'married', 'child'];
for ($t = 0; $t < 400; $t++) {
    $players = [];
    for ($i = 1; $i <= 5; $i++) {
        $isCouple = mt_rand(0, 1) === 1;
        $pl = ['id' => $i, 'present' => mt_rand(0, 9) > 0, 'late' => mt_rand(0, 9) === 0,
            'absence_notice_ok' => mt_rand(0, 1) === 1, 'absence_valid' => mt_rand(0, 1) === 1,
            'travel_band' => mt_rand(0, 3), 'host' => $i === 3, 'prizes' => mt_rand(0, 1),
            'prev_last' => mt_rand(0, 4) === 0, 'cheating' => mt_rand(0, 19) === 0,
            'hidden_breakup' => mt_rand(0, 29) === 0, 'attempt' => mt_rand(0, 1) === 1,
            'accusations' => [mt_rand(1, 5), mt_rand(1, 5)]];
        if ($isCouple) {
            $pl = couple(mt_rand(0, 6), 'P' . $i, ['partner_presence' => ['present', 'present', 'absent', 'visio'][mt_rand(0, 3)],
                'steps' => mt_rand(0, 1) ? [$steps[mt_rand(0, 5)]] : []]) + $pl;
        } elseif (mt_rand(0, 2) === 0) {
            $pl['bluff'] = 'Faux' . $i;
        }
        if (mt_rand(0, 6) === 0) {
            $pl['breakups'] = [['kind' => ['simple', 'divorce', 'engaged', 'end_shared'][mt_rand(0, 3)], 'couple_continues' => mt_rand(0, 1) === 1, 'good_terms' => mt_rand(0, 3) === 0]];
        }
        $players[$i] = $pl;
    }
    $r = YearScorer::score($P, $players);
    foreach ($r as $res) {
        if (array_sum($res['cats']) !== $res['score'] || $res['score'] < 0) {
            check('invariant aléatoire', false, json_encode([$players[$res['id']], $res]));
        }
    }
}
check('invariants sur 400 tables aléatoires : somme des catégories = score, jamais négatif', true);

// ---- 18. Rangs, points globaux, usure
$ranks = Ranking::ranks([1 => 10, 2 => 10, 3 => 5, 4 => 5, 5 => 0]);
check('égalité en tête : rangs 1,1,3,3,5', array_values($ranks) === [1, 1, 3, 3, 5]);
check('points globaux 3,3,1,1,0', array_values(Ranking::globalPoints($ranks, $P['gpts'])) === [3, 3, 1, 1, 0]);
$ranks = Ranking::ranks([1 => 8, 2 => 5, 3 => 5, 4 => 2, 5 => 2]);
check('égalité 2e : 3,2,2,0,0', array_values(Ranking::globalPoints($ranks, $P['gpts'])) === [3, 2, 2, 0, 0]);
check('usure : 10 -> 0,7×10+3 = 10,0', Ranking::updateGlobal(10.0, 3, 0.7) === 10.0);
check('usure : 7,3 -> 0,7×7,3+2 = 7,1', Ranking::updateGlobal(7.3, 2, 0.7) === 7.1);
check('usure : décimales arrondies à 0,1', Ranking::updateGlobal(1.0, 0, 0.7) === 0.7);

// ---- 19. Rattrapage : derniers (pas si tous égaux)
check('dernier unique', Ranking::lasts([1 => 5, 2 => 3, 3 => 0]) === [3]);
check('derniers ex aequo : tous', Ranking::lasts([1 => 5, 2 => 0, 3 => 0]) === [2, 3]);
check('tous à égalité : personne', Ranking::lasts([1 => 2, 2 => 2, 3 => 2]) === []);

// ---- 20. Cadeaux en miroir
$g = Ranking::gifts([1 => 9, 2 => 7, 3 => 5, 4 => 3, 5 => 1]);
check('5 joueurs : 5e->1er, 4e->2e, 3e neutre', count($g) === 2 && $g[0]['giver'] === 5 && $g[0]['receiver'] === 1 && $g[1]['giver'] === 4 && $g[1]['receiver'] === 2);
$g = Ranking::gifts([1 => 9, 2 => 7, 3 => 5, 4 => 3, 5 => 1, 6 => 0]);
check('6 joueurs : 6->1, 5->2, 4->3', count($g) === 3 && $g[2]['giver'] === 4 && $g[2]['receiver'] === 3);
$g = Ranking::gifts([1 => 9, 2 => 7, 3 => 7, 4 => 3, 5 => 1, 6 => 0, 7 => 0]);
check('7 joueurs : 3 cadeaux, le 4e est neutre', count($g) === 3);
check('ex aequo signalé', $g[1]['tie'] === true && $g[0]['tie'] === true);

// ---- 21. Cadeau des 3 ans
check('plus haut global', Ranking::cycleWinners([1 => 9.5, 2 => 7.2], [1 => 5, 2 => 20]) === [1]);
check('égalité : somme des points annuels', Ranking::cycleWinners([1 => 9.5, 2 => 9.5, 3 => 1], [1 => 5, 2 => 7, 3 => 0]) === [2]);
check('égalité persistante : les deux reçoivent', Ranking::cycleWinners([1 => 9.5, 2 => 9.5], [1 => 5, 2 => 5]) === [1, 2]);

// ---- 22. Arrivée d'un joueur : max(0 ; dernier - H)
$e = Ranking::entryStart([1 => 10, 2 => 8, 3 => 6, 4 => 3, 5 => 1]);
check('entrée cas normal : H=2 -> 0', $e[0] === 0.0 && $e[1] === 2);
$e = Ranking::entryStart([1 => 20, 2 => 15, 3 => 10, 4 => 6, 5 => 4]);
check('entrée écarts grands : H=3 -> 1', $e[0] === 1.0 && $e[1] === 3);
$e = Ranking::entryStart([1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5]);
check('entrée tous égaux : H=1 -> 4', $e[0] === 4.0 && $e[1] === 1);
$e = Ranking::entryStart([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]);
check('entrée tous à zéro : 0', $e[0] === 0.0);
$e = Ranking::entryStart([1 => 12, 2 => 9, 3 => 7, 4 => 6, 5 => 4, 6 => 2]);
check('entrée à 6 joueurs : positif ou nul', $e[0] >= 0);

// ---- 23. Amendement d'un paramètre
$P3 = Defaults::with($P, 'malus.cheat', -8);
check('with() change une valeur imbriquée sans toucher à l\'original', $P3['malus']['cheat'] === -8 && $P['malus']['cheat'] === -10);
$P4 = Defaults::with($P, 'serie.0', 3);
check('with() sur un tableau : 1re année à +3', $P4['serie'] === [3, 2, 3, 4, 5]);
$r = YearScorer::score($P4, table([1 => couple(0)]));
check('le calcul suit le barème amendé', $r[1]['score'] === 3);
