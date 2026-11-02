<?php
declare(strict_types=1);

namespace TEC\Controller;

use TEC\Auth;
use TEC\Csrf;
use TEC\Db;
use TEC\Http;
use TEC\Labels;
use TEC\Repo\Amendments;
use TEC\Repo\Entries;
use TEC\Repo\Players;
use TEC\Repo\Years;

final class PlayerController
{
    /** Dernier statut connu d'un joueur : dernière relation validée, sinon l'état de la dernière année clôturée. */
    public static function lastStatus(int $playerId): ?array
    {
        $e = Db::one(
            "SELECT e.*, gy.year FROM entries e JOIN game_years gy ON gy.id = e.year_id
             WHERE e.player_id = ? AND e.type = 'relation' AND e.status = 'approved' ORDER BY e.id DESC LIMIT 1",
            [$playerId]
        );
        if ($e) {
            $d = json_decode_array($e['data']);
            return ['status' => $d['status'], 'partner' => $d['partner'] ?? null, 'far' => !empty($d['far']),
                'since' => $e['decided_at'], 'year' => (int)$e['year'], 'series' => null];
        }
        $r = Db::one(
            'SELECT yr.state, gy.year FROM year_results yr JOIN game_years gy ON gy.id = yr.year_id
             WHERE yr.player_id = ? ORDER BY gy.year DESC LIMIT 1',
            [$playerId]
        );
        if ($r) {
            $s = json_decode_array($r['state']);
            return ['status' => !empty($s['partner']) ? 'couple' : 'single', 'partner' => $s['partner_name'] ?? $s['partner'] ?? null,
                'far' => false, 'since' => null, 'year' => (int)$r['year'], 'series' => (int)($s['series'] ?? 0)];
        }
        return null;
    }

    public static function home(): void
    {
        $u = Auth::requireLogin();
        $year = Years::current();
        $last = Years::lastClosed();
        $players = Players::map();
        $ranking = [];
        $globalRank = [];
        if ($last) {
            $ranking = Db::all('SELECT * FROM year_results WHERE year_id = ? ORDER BY rank_pos, score DESC', [$last['id']]);
            $globalRank = Db::all('SELECT * FROM year_results WHERE year_id = ? ORDER BY global_rank, global_after DESC', [$last['id']]);
        }
        $absences = [];
        if ($year) {
            foreach (Entries::forYear((int)$year['id'], 'approved') as $e) {
                if ($e['type'] === 'absence') {
                    $absences[] = $e;
                }
            }
        }
        $pendingMine = $year ? (int)Db::val("SELECT COUNT(*) FROM entries WHERE year_id = ? AND player_id = ? AND status = 'pending'", [$year['id'], $u['id']]) : 0;
        $pendingAll = Auth::isAdmin() ? (int)Db::val("SELECT COUNT(*) FROM entries WHERE status = 'pending'") : 0;
        Http::render('home', [
            'title' => 'Accueil', 'year' => $year, 'last' => $last, 'players' => $players, 'ranking' => $ranking,
            'globalRank' => $globalRank, 'absences' => $absences, 'me' => self::lastStatus((int)$u['id']),
            'host' => ($year && $year['host_id']) ? ($players[(int)$year['host_id']]['name'] ?? null) : null,
            'pendingMine' => $pendingMine, 'pendingAll' => $pendingAll,
            'amendWindow' => $year ? Amendments::window($year) : null,
        ]);
    }

    public static function me(): void
    {
        $u = Auth::requireLogin();
        $year = Years::current();
        $entries = $year ? Entries::forPlayer((int)$year['id'], (int)$u['id']) : [];
        foreach ($entries as &$e) {
            $e['log'] = Entries::logFor((int)$e['id']);
        }
        Http::render('me', [
            'title' => 'Mon espace', 'year' => $year, 'entries' => $entries, 'status' => self::lastStatus((int)$u['id']),
            'canAdd' => $year && ($year['status'] === 'open' || Auth::isAdmin()),
        ]);
    }

    public static function addEntry(): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        $year = Years::current();
        if (!$year || ($year['status'] !== 'open' && !Auth::isAdmin())) {
            Http::flash('error', 'Les déclarations sont fermées pour cette année.');
            Http::redirect('/moi');
        }
        $type = Http::post('type');
        if (!isset(Entries::TYPES[$type])) {
            Http::flash('error', 'Type de déclaration inconnu.');
            Http::redirect('/moi');
        }
        [$id, $err] = Entries::create((int)$year['id'], (int)$u['id'], $type, $_POST, (int)$u['id']);
        Http::flash($err ? 'error' : 'ok', $err ?: 'Déclaration envoyée : elle sera visible une fois validée par l\'administrateur.');
        Http::redirect('/moi');
    }

    public static function withdrawEntry(string $id): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        $ok = Entries::withdraw((int)$id, (int)$u['id']);
        Http::flash($ok ? 'ok' : 'error', $ok ? 'Déclaration retirée.' : 'Impossible : elle est déjà traitée ou ne t\'appartient pas.');
        Http::redirect('/moi');
    }

    public static function saveProfile(): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        Db::update('players', ['home_place' => mb_substr(Http::post('home_place'), 0, 190) ?: null], 'id = ?', [$u['id']]);
        Http::flash('ok', 'Lieu de vie enregistré.');
        Http::redirect('/moi');
    }

    public static function players(): void
    {
        Auth::requireLogin();
        $list = [];
        foreach (Players::all(true) as $p) {
            $list[] = ['player' => $p, 'status' => self::lastStatus((int)$p['id'])];
        }
        Http::render('players', ['title' => 'Joueurs', 'list' => $list]);
    }

    public static function ranking(): void
    {
        Auth::requireLogin();
        $closed = Db::all("SELECT * FROM game_years WHERE status = 'closed' ORDER BY year DESC");
        $sel = null;
        $wanted = (int)Http::get('annee');
        foreach ($closed as $y) {
            if ($wanted === (int)$y['year'] || ($sel === null && !$wanted)) {
                $sel = $y;
            }
        }
        $rows = $summary = null;
        if ($sel) {
            $rows = Db::all('SELECT * FROM year_results WHERE year_id = ? ORDER BY rank_pos, score DESC', [$sel['id']]);
            $summary = Db::one('SELECT * FROM year_summary WHERE year_id = ?', [$sel['id']]);
        }
        Http::render('ranking', [
            'title' => 'Classement', 'closed' => $closed, 'sel' => $sel, 'rows' => $rows ?? [], 'summary' => $summary,
            'players' => Players::map(),
        ]);
    }

    public static function history(): void
    {
        Auth::requireLogin();
        $closed = Db::all("SELECT * FROM game_years WHERE status = 'closed' ORDER BY year");
        $matrix = [];
        foreach (Db::all('SELECT yr.*, gy.year FROM year_results yr JOIN game_years gy ON gy.id = yr.year_id') as $r) {
            $matrix[(int)$r['player_id']][(int)$r['year']] = $r;
        }
        Http::render('history', ['title' => 'Historique', 'closed' => $closed, 'matrix' => $matrix, 'players' => Players::map()]);
    }

    public static function historyYear(string $year): void
    {
        Auth::requireLogin();
        $y = Years::byYear((int)$year);
        if (!$y || $y['status'] !== 'closed') {
            Http::abort(404, 'Cette année n\'est pas (encore) dans l\'historique.');
        }
        $rows = Db::all('SELECT * FROM year_results WHERE year_id = ? ORDER BY rank_pos, score DESC', [$y['id']]);
        Http::render('history_year', [
            'title' => 'Année ' . $y['year'], 'year' => $y, 'rows' => $rows, 'players' => Players::map(),
            'rules' => \TEC\Repo\Rules::find((int)$y['rule_version_id']),
            'summary' => Db::one('SELECT * FROM year_summary WHERE year_id = ?', [$y['id']]),
            'prizes' => \TEC\Repo\Soiree::prizes((int)$y['id']),
            'host' => $y['host_id'] ? (Players::find((int)$y['host_id'])['name'] ?? null) : null,
        ]);
    }
}
