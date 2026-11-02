<?php
declare(strict_types=1);

namespace TEC\Controller;

use TEC\Auth;
use TEC\Calendar;
use TEC\Csrf;
use TEC\Http;
use TEC\Repo\Journal;
use TEC\Repo\Players;
use TEC\Repo\Years;

/** Journal personnel (privé) et rewind de l'année (événements partagés, une fois la soirée ouverte). */
final class JournalController
{
    private static function validMonth(string $m): bool
    {
        return (bool)preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m);
    }

    public static function index(): void
    {
        $u = Auth::requireLogin();
        $uid = (int)$u['id'];
        $month = self::validMonth(Http::get('mois')) ? Http::get('mois') : date('Y-m');
        $day = Http::get('jour');
        $day = preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) && strtotime($day) ? $day : '';
        $edit = null;
        if ((int)Http::get('modifier') > 0) {
            $e = Journal::find((int)Http::get('modifier'));
            if ($e && Journal::canEdit($e, $uid)) {
                $edit = $e;
                $month = self::validMonth(substr($e['start_date'], 0, 7)) ? substr($e['start_date'], 0, 7) : $month;
            }
        }
        $end = Http::get('fin');
        $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) && strtotime($end) ? $end : '';
        $events = Journal::forPlayer($uid);
        $cal = Calendar::month($month, $events);
        $monthStart = $cal['month'] . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $inMonth = array_values(array_filter($events, fn($e) => $e['start_date'] <= $monthEnd && ($e['end_date'] ?: $e['start_date']) >= $monthStart));
        Http::render('journal', [
            'title' => 'Mon journal', 'cal' => $cal, 'inMonth' => $inMonth, 'edit' => $edit, 'day' => $day, 'dayEnd' => $end,
            'year' => Years::current(), 'me' => $uid,
        ]);
    }

    public static function save(): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        $year = Years::current();
        if (!$year) {
            Http::flash('error', 'Aucune année ouverte.');
            Http::redirect('/journal');
        }
        [$id, $err] = Journal::create((int)$u['id'], (int)$year['id'], $_POST);
        if ($err) {
            Http::flash('error', $err);
            Http::redirect('/journal');
        }
        Http::flash('ok', 'Événement ajouté à ton journal.');
        Http::redirect('/journal?mois=' . substr(Http::post('start_date'), 0, 7));
    }

    public static function update(string $id): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        $err = Journal::update((int)$id, (int)$u['id'], $_POST);
        Http::flash($err ? 'error' : 'ok', $err ?: 'Événement modifié.');
        Http::redirect($err ? '/journal?modifier=' . (int)$id : '/journal?mois=' . substr(Http::post('start_date'), 0, 7));
    }

    public static function delete(string $id): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        $ok = Journal::delete((int)$id, (int)$u['id']);
        Http::flash($ok ? 'ok' : 'error', $ok ? 'Événement supprimé.' : 'Impossible : il est partagé et la soirée a commencé, ou il ne t\'appartient pas.');
        Http::redirect('/journal' . (self::validMonth(Http::post('mois')) ? '?mois=' . Http::post('mois') : ''));
    }

    public static function rewind(): void
    {
        $u = Auth::requireLogin();
        $uid = (int)$u['id'];
        $years = Journal::revealedYears();
        $sel = null;
        foreach ($years as $y) {
            if ((int)Http::get('annee') === (int)$y['year'] || ($sel === null && !Http::get('annee'))) {
                $sel = $y;
            }
        }
        $data = ['title' => 'Rewind', 'years' => $years, 'sel' => $sel, 'current' => Years::current(), 'me' => $uid];
        if ($sel) {
            $all = Journal::visibleForYear($sel, $uid);
            // une couleur par joueur, stable (ordre des identifiants)
            $colors = [];
            $i = 0;
            foreach (Players::all() as $p) {
                $colors[(int)$p['id']] = ($i++ % 8) + 1;
            }
            $only = (int)Http::get('joueur');
            $events = $only ? array_values(array_filter($all, fn($e) => (int)$e['player_id'] === $only)) : $all;
            $authors = [];
            foreach ($all as $e) {
                $authors[(int)$e['player_id']] = $e['player_name'];
            }
            asort($authors);
            $months = [];
            if ($events) {
                $from = substr(min(array_column($events, 'start_date')), 0, 7);
                $to = substr(max(array_map(fn($e) => $e['end_date'] ?: $e['start_date'], $events)), 0, 7);
                $ref = $sel['meeting_date'] ? substr($sel['meeting_date'], 0, 7) : date('Y-m');
                $to = max($to, $ref);
                $from = min($from, date('Y-m', strtotime($to . '-01 -11 months')));
                $months = Calendar::monthsBetween($from, $to);
            }
            $minis = [];
            foreach ($months as $m) {
                $minis[] = Calendar::month($m, $events);
            }
            $data += ['events' => $events, 'colors' => $colors, 'authors' => $authors, 'only' => $only, 'minis' => $minis];
        }
        Http::render('rewind', $data);
    }
}
