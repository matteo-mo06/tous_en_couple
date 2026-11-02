<?php
declare(strict_types=1);

// Journal personnel : validation, confidentialité (autres joueurs et administrateur), modification.

use TEC\Calendar;
use TEC\Config;
use TEC\Db;
use TEC\Migrator;
use TEC\Repo\Journal;
use TEC\Repo\Players;
use TEC\Repo\Rules;
use TEC\Repo\Years;

$tmp = sys_get_temp_dir() . '/tec_test_journal_' . getmypid() . '.sqlite';
@unlink($tmp);
Config::override(['db' => ['driver' => 'sqlite', 'sqlite_path' => $tmp]]);
Db::reset();
Migrator::run();
[$adm] = Players::create('Admin', 'admin', 'motdepasse1', 'admin', null, false, 0.0);
[$ann] = Players::create('Ann', 'ann', 'motdepasse2', 'player', null, false, 0.0);
[$bob] = Players::create('Bob', 'bob', 'motdepasse3', 'player', null, false, 0.0);
$y = Years::create(2027, Rules::ensureInitial(2027));
$year = Years::find($y);

[, $err] = Journal::create($ann, $y, ['kind' => 'inconnu', 'title' => 'x', 'start_date' => '2027-03-01']);
check('type inconnu refusé', $err !== null);
[, $err] = Journal::create($ann, $y, ['kind' => 'crush', 'title' => '  ', 'start_date' => '2027-03-01']);
check('titre vide refusé', $err !== null);
[, $err] = Journal::create($ann, $y, ['kind' => 'crush', 'title' => 'x', 'start_date' => '2027-02-30']);
check('date impossible refusée', $err !== null);
[, $err] = Journal::create($ann, $y, ['kind' => 'travel', 'title' => 'x', 'start_date' => '2027-03-10', 'end_date' => '2027-03-05']);
check('fin avant début refusée', $err !== null);

[$e1] = Journal::create($ann, $y, ['kind' => 'date', 'title' => 'Double date', 'start_date' => '2027-03-10', 'end_date' => '2027-03-12', 'shared' => '1']);
[$e2] = Journal::create($ann, $y, ['kind' => 'crush', 'title' => 'Crush secret', 'start_date' => '2027-03-20']);
check('partagé par défaut seulement si coché', (int)Journal::find($e1)['shared'] === 1 && (int)Journal::find($e2)['shared'] === 0);
[$e3] = Journal::create($bob, $y, ['kind' => 'memory', 'title' => 'Bob partagé', 'start_date' => '2027-04-01', 'shared' => '1']);

$titles = fn(array $rows) => array_column($rows, 'title');
check('avant la soirée : on ne voit que les siens', $titles(Journal::visibleForYear($year, $bob)) === ['Bob partagé']);
check('avant la soirée : l\'administrateur ne voit rien des autres', Journal::visibleForYear($year, $adm) === []);
check('l\'auteur voit ses événements privés', count(Journal::visibleForYear($year, $ann)) === 2);

Db::update('game_years', ['status' => 'soiree'], 'id = ?', [$y]);
$year = Years::find($y);
check('soirée : les événements partagés des autres apparaissent', $titles(Journal::visibleForYear($year, $bob)) === ['Double date', 'Bob partagé']);
check('soirée : un événement privé reste privé', !in_array('Crush secret', $titles(Journal::visibleForYear($year, $bob)), true) && !in_array('Crush secret', $titles(Journal::visibleForYear($year, $adm)), true));

check('un événement partagé n\'est plus modifiable une fois la soirée ouverte', !Journal::canEdit(Journal::find($e1), $ann) && !Journal::delete($e1, $ann));
check('un événement privé reste modifiable', Journal::update($e2, $ann, ['kind' => 'crush', 'title' => 'Crush modifié', 'start_date' => '2027-03-21']) === null && Journal::find($e2)['title'] === 'Crush modifié');
check('on ne modifie pas l\'événement d\'un autre', Journal::update($e2, $bob, ['kind' => 'crush', 'title' => 'piraté', 'start_date' => '2027-03-21']) !== null);
check('on ne supprime pas l\'événement d\'un autre', !Journal::delete($e2, $bob));
check('suppression d\'un événement privé par son auteur', Journal::delete($e2, $ann) && Journal::find($e2) === null);

$cal = Calendar::month('2027-03', Journal::forPlayer($ann));
$flat = array_merge(...$cal['weeks']);
$withEvents = array_values(array_filter($flat, fn($c) => $c['events']));
check('calendrier : une période couvre chaque jour', array_column($withEvents, 'date') === ['2027-03-10', '2027-03-11', '2027-03-12']);
check('calendrier : semaines complètes du lundi au dimanche', count($flat) % 7 === 0 && date('N', strtotime($flat[0]['date'])) === '1');
check('mois suivant/précédent', $cal['prev'] === '2027-02' && $cal['next'] === '2027-04');
check('période lisible', fr_period('2027-03-10', '2027-03-12') === '10 → 12 mars' && fr_period('2027-03-30', '2027-04-02') === '30 mars → 2 avril' && fr_period('2027-03-10', null) === '10 mars');
[, $err] = Journal::create($ann, $y, ['kind' => 'travel', 'title' => 'x', 'start_date' => '2027-05-01', 'mode' => 'periode']);
check('mode période sans fin refusé', $err !== null);
[$ep] = Journal::create($ann, $y, ['kind' => 'travel', 'title' => 'Vacances', 'start_date' => '2027-05-01', 'end_date' => '2027-05-09', 'mode' => 'periode']);
check('mode période enregistre la fin', Journal::find($ep)['end_date'] === '2027-05-09');
[$ed] = Journal::create($ann, $y, ['kind' => 'travel', 'title' => 'Un jour', 'start_date' => '2027-05-01', 'end_date' => '2027-05-09', 'mode' => 'date']);
check('mode date ignore la fin', Journal::find($ed)['end_date'] === null);
@unlink($tmp);
