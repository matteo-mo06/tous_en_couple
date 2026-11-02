<?php
declare(strict_types=1);

namespace TEC\Controller;

use TEC\Auth;
use TEC\Csrf;
use TEC\Db;
use TEC\Http;
use TEC\Repo\Amendments;
use TEC\Repo\Entries;
use TEC\Repo\Players;
use TEC\Repo\Rules;
use TEC\Repo\Soiree;
use TEC\Repo\Years;
use TEC\Scoring\Defaults;
use TEC\Scoring\Ranking;
use TEC\YearService;

final class AdminController
{
    private static function guard(bool $post = false): array
    {
        $u = Auth::requireAdmin();
        if ($post) {
            Csrf::check();
        }
        return $u;
    }

    public static function dashboard(): void
    {
        self::guard();
        $year = Years::current();
        Http::render('admin/dashboard', [
            'title' => 'Administration', 'year' => $year,
            'pending' => (int)Db::val("SELECT COUNT(*) FROM entries WHERE status = 'pending'"),
            'players' => count(Players::all(true)),
            'amendments' => $year ? count(Amendments::forYear((int)$year['id'])) : 0,
        ]);
    }

    // ------------------------------------------------------------------ joueurs
    public static function players(): void
    {
        self::guard();
        $globals = Years::currentGlobals();
        $hasHistory = (bool)Years::lastClosed();
        $start = $hasHistory && count($globals) >= 2 ? Ranking::entryStart($globals) : [0.0, 1];
        Http::render('admin/players', ['title' => 'Joueurs', 'list' => Players::all(), 'start' => $start, 'hasHistory' => $hasHistory, 'globals' => $globals]);
    }

    public static function createPlayer(): void
    {
        $me = self::guard(true);
        $name = Http::post('name');
        $login = Http::post('login') ?: Players::slug($name);
        $year = Years::current();
        $hasHistory = (bool)Years::lastClosed();
        $newcomer = $hasHistory && count(Years::currentGlobals()) >= 2;
        $start = $newcomer ? Ranking::entryStart(Years::currentGlobals())[0] : 0.0;
        [$id, $err] = Players::create(
            $name, $login, (string)($_POST['password'] ?? ''), Http::post('role') ?: 'player', null, true, $start,
            $year ? (int)$year['year'] : null
        );
        if ($err) {
            Http::flash('error', $err);
        } else {
            if ($year) {
                Years::addParticipant((int)$year['id'], (int)$id);
            }
            Http::flash('ok', "Compte créé : identifiant « $login »." . ($newcomer ? ' Points de départ : ' . num($start) . '.' : ''));
        }
        Http::redirect('/admin/joueurs');
    }

    public static function playerAction(string $id): void
    {
        $me = self::guard(true);
        $p = Players::find((int)$id);
        if (!$p) {
            Http::abort(404);
        }
        $action = Http::post('action');
        if ($action === 'toggle_active') {
            if ($p['role'] === 'admin' && $p['active'] && Players::adminCount() <= 1) {
                Http::flash('error', 'Impossible : c\'est le dernier administrateur actif.');
            } else {
                Db::update('players', ['active' => $p['active'] ? 0 : 1], 'id = ?', [$p['id']]);
                $year = Years::current();
                if (!$p['active'] && $year) {
                    Years::addParticipant((int)$year['id'], (int)$p['id']);
                }
                Http::flash('ok', $p['active'] ? 'Compte désactivé.' : 'Compte réactivé.');
            }
        } elseif ($action === 'toggle_role') {
            if ($p['role'] === 'admin' && Players::adminCount() <= 1) {
                Http::flash('error', 'Il faut garder au moins un administrateur.');
            } else {
                Db::update('players', ['role' => $p['role'] === 'admin' ? 'player' : 'admin'], 'id = ?', [$p['id']]);
                Http::flash('ok', 'Rôle modifié.');
            }
        } elseif ($action === 'reset_password') {
            $err = Players::setPassword((int)$p['id'], (string)($_POST['password'] ?? ''), true);
            Http::flash($err ? 'error' : 'ok', $err ?: 'Mot de passe provisoire défini : le joueur devra en choisir un nouveau.');
        }
        Http::redirect('/admin/joueurs');
    }

    // ------------------------------------------------------------------ déclarations
    public static function entries(): void
    {
        self::guard();
        $year = Years::current();
        $pending = Entries::pending();
        $all = $year ? Entries::forYear((int)$year['id']) : [];
        Http::render('admin/entries', [
            'title' => 'Déclarations', 'year' => $year, 'pending' => $pending,
            'done' => array_values(array_filter($all, fn($e) => $e['status'] !== 'pending')),
            'players' => Players::all(true),
        ]);
    }

    public static function decide(string $id): void
    {
        $me = self::guard(true);
        $d = Http::post('decision') === 'approve' ? 'approved' : 'rejected';
        $err = Entries::decide((int)$id, $d, Http::post('note'), (int)$me['id']);
        Http::flash($err ? 'error' : 'ok', $err ?: ($d === 'approved' ? 'Validée.' : 'Refusée.'));
        Http::redirect('/admin/entrees');
    }

    public static function createEntry(): void
    {
        $me = self::guard(true);
        $year = Years::current();
        if (!$year) {
            Http::flash('error', 'Aucune année ouverte.');
            Http::redirect('/admin/entrees');
        }
        $pid = (int)Http::post('player_id');
        if (!Years::isParticipant((int)$year['id'], $pid)) {
            Http::flash('error', 'Joueur inconnu.');
            Http::redirect('/admin/entrees');
        }
        [$id, $err] = Entries::create((int)$year['id'], $pid, Http::post('type'), $_POST, (int)$me['id'], !empty($_POST['approve']));
        Http::flash($err ? 'error' : 'ok', $err ?: 'Déclaration enregistrée.');
        Http::redirect('/admin/entrees');
    }

    // ------------------------------------------------------------------ année
    public static function year(): void
    {
        self::guard();
        $year = Years::current();
        if (!$year) {
            Http::render('admin/year', ['title' => 'Année', 'year' => null]);
            return;
        }
        $lastHost = Years::hostOfYear((int)$year['year'] - 1);
        Http::render('admin/year', [
            'title' => 'Année ' . $year['year'], 'year' => $year, 'parts' => Years::participants((int)$year['id']),
            'lastHost' => $lastHost, 'window' => Amendments::window($year),
        ]);
    }

    public static function saveYear(): void
    {
        self::guard(true);
        $year = Years::current();
        if (!$year) {
            Http::redirect('/admin/annee');
        }
        $date = Http::post('meeting_date');
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Http::flash('error', 'Date invalide.');
            Http::redirect('/admin/annee');
        }
        $host = (int)Http::post('host_id') ?: null;
        if ($host) {
            if (!Years::isParticipant((int)$year['id'], $host)) {
                Http::flash('error', 'Cet hôte ne participe pas.');
                Http::redirect('/admin/annee');
            }
            if (Years::hostOfYear((int)$year['year'] - 1) === $host) {
                Http::flash('error', 'Un joueur ne peut pas être hôte deux années de suite.');
                Http::redirect('/admin/annee');
            }
        }
        Db::update('game_years', [
            'meeting_date' => $date ?: null, 'meeting_place' => mb_substr(Http::post('meeting_place'), 0, 190) ?: null, 'host_id' => $host,
        ], 'id = ?', [$year['id']]);
        Http::flash('ok', 'Année mise à jour.');
        Http::redirect('/admin/annee');
    }

    /** Tirage au sort de l'hôte parmi les joueurs éligibles (pas l'hôte de l'an passé). */
    public static function openSoiree(): void
    {
        self::guard(true);
        $year = Years::current();
        if (!$year) {
            Http::redirect('/admin/annee');
        }
        $act = Http::post('action');
        if ($act === 'open' && $year['status'] === 'open') {
            Db::update('game_years', ['status' => 'soiree'], 'id = ?', [$year['id']]);
            Http::flash('ok', 'Soirée ouverte : les déclarations sont fermées aux joueurs.');
        } elseif ($act === 'reopen' && $year['status'] === 'soiree') {
            Db::update('game_years', ['status' => 'open'], 'id = ?', [$year['id']]);
            Http::flash('ok', 'Déclarations rouvertes.');
        } elseif ($act === 'draw_host') {
            $last = Years::hostOfYear((int)$year['year'] - 1);
            $pool = array_values(array_filter(Years::participants((int)$year['id']), fn($p) => (int)$p['player_id'] !== $last));
            if ($pool) {
                $pick = $pool[random_int(0, count($pool) - 1)];
                Db::update('game_years', ['host_id' => $pick['player_id']], 'id = ?', [$year['id']]);
                Http::flash('ok', 'Hôte tiré au sort : ' . $pick['name'] . '.');
            }
        }
        Http::redirect($act === 'open' ? '/admin/soiree' : '/admin/annee');
    }

    // ------------------------------------------------------------------ soirée
    public static function soiree(): void
    {
        self::guard();
        $year = Years::current();
        if (!$year) {
            Http::redirect('/admin/annee');
        }
        $yid = (int)$year['id'];
        $parts = Years::participants($yid);
        $entries = Entries::forYear($yid, 'approved');
        $absences = $terms = [];
        foreach ($entries as $e) {
            if ($e['type'] === 'absence') {
                $e['tally'] = Soiree::tally('absence', (int)$e['id'], (int)$e['player_id']);
                $absences[] = $e;
            }
            if ($e['type'] === 'breakup' && !empty($e['data']['good_terms_requested'])) {
                $e['tally'] = Soiree::tally('terms', (int)$e['id'], (int)$e['player_id']);
                $terms[] = $e;
            }
        }
        $prizes = Soiree::prizes($yid);
        foreach ($prizes as &$pz) {
            $pz['tally'] = Soiree::tally('prize', (int)$pz['id']);
            $pz['leader'] = Soiree::prizeLeader((int)$pz['id']);
        }
        $acc = [];
        foreach (Soiree::allAccusations($yid) as $a) {
            $acc[(int)$a['target_id']] = ($acc[(int)$a['target_id']] ?? 0) + 1;
        }
        $maxPrizes = (int)Rules::find((int)$year['rule_version_id'])['params']['n_prizes'];
        Http::render('admin/soiree', [
            'title' => 'Soirée (admin)', 'year' => $year, 'parts' => $parts, 'absences' => $absences, 'terms' => $terms,
            'prizes' => $prizes, 'accCount' => $acc, 'maxPrizes' => $maxPrizes, 'players' => Players::map(),
            'homes' => array_column(Players::all(), 'home_place', 'id'),
        ]);
    }

    public static function saveAttendance(): void
    {
        self::guard(true);
        $year = Years::current();
        if (!$year) {
            Http::redirect('/admin/annee');
        }
        $rows = Http::postArray('p');
        foreach ($rows as $pid => $r) {
            if (!is_array($r) || !Years::isParticipant((int)$year['id'], (int)$pid)) {
                continue;
            }
            $present = ($r['present'] ?? '') === '' ? null : (int)$r['present'];
            Soiree::setAttendance((int)$year['id'], (int)$pid, $present, !empty($r['late']), (string)($r['partner'] ?? 'present'), (int)($r['band'] ?? 0));
        }
        Http::flash('ok', 'Présences enregistrées.');
        Http::redirect('/admin/soiree');
    }

    public static function addPrize(): void
    {
        self::guard(true);
        $year = Years::current();
        $max = (int)Rules::find((int)$year['rule_version_id'])['params']['n_prizes'];
        $err = Soiree::addPrize((int)$year['id'], Http::post('label'), $max);
        Http::flash($err ? 'error' : 'ok', $err ?: 'Prix ajouté.');
        Http::redirect('/admin/soiree');
    }

    public static function setWinner(string $id): void
    {
        self::guard(true);
        $w = (int)Http::post('winner') ?: null;
        $err = Soiree::setPrizeWinner((int)$id, $w);
        Http::flash($err ? 'error' : 'ok', $err ?: 'Gagnant enregistré.');
        Http::redirect('/admin/soiree');
    }

    // ------------------------------------------------------------------ clôture
    public static function closePreview(): void
    {
        self::guard();
        $year = Years::current();
        if (!$year) {
            Http::redirect('/admin/annee');
        }
        $c = YearService::compute((int)$year['id']);
        $next = null;
        if ($c['amendments']) {
            [$np, $applied] = YearService::nextParams($c['rules']['params'], $c['amendments']);
            $next = ['params' => $np, 'applied' => $applied];
        }
        Http::render('admin/close', ['title' => 'Clôture ' . $year['year'], 'c' => $c, 'next' => $next, 'labels' => Defaults::labels()]);
    }

    public static function closeConfirm(): void
    {
        $me = self::guard(true);
        $year = Years::current();
        if (!$year || Http::post('confirm') !== (string)$year['year']) {
            Http::flash('error', 'Tape l\'année pour confirmer la clôture.');
            Http::redirect('/admin/cloture');
        }
        $r = YearService::close((int)$year['id'], (int)$me['id']);
        if (!$r['ok']) {
            foreach ($r['errors'] as $e) {
                Http::flash('error', $e);
            }
            Http::redirect('/admin/cloture');
        }
        Http::flash('ok', 'Année ' . $year['year'] . ' clôturée. L\'année suivante est ouverte.');
        Http::redirect('/classement');
    }

    public static function rules(): void
    {
        self::guard();
        Http::render('admin/rules', ['title' => 'Barème', 'versions' => Rules::all(), 'labels' => Defaults::labels()]);
    }
}
