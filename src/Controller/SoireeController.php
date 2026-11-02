<?php
declare(strict_types=1);

namespace TEC\Controller;

use TEC\Auth;
use TEC\Csrf;
use TEC\Db;
use TEC\Http;
use TEC\Repo\Entries;
use TEC\Repo\Players;
use TEC\Repo\Soiree;
use TEC\Repo\Years;

/** Page des joueurs pendant la soirée : accusations et votes en direct. */
final class SoireeController
{
    public static function live(): void
    {
        $u = Auth::requireLogin();
        $year = Years::current();
        $me = (int)$u['id'];
        $data = ['title' => 'Soirée', 'year' => $year, 'open' => $year && $year['status'] === 'soiree'];
        if ($data['open']) {
            $yid = (int)$year['id'];
            $parts = Years::participants($yid);
            $entries = Entries::forYear($yid, 'approved');
            $absent = [];
            $terms = [];
            foreach ($entries as $e) {
                $part = null;
                foreach ($parts as $p) {
                    if ((int)$p['player_id'] === (int)$e['player_id']) {
                        $part = $p;
                    }
                }
                if ($e['type'] === 'absence' && $part && $part['present'] !== null && (string)$part['present'] === '0') {
                    $e['my_vote'] = Soiree::myVote('absence', (int)$e['id'], $me);
                    $e['votes_cast'] = array_sum(Soiree::tally('absence', (int)$e['id'], (int)$e['player_id']));
                    $absent[] = $e;
                }
                if ($e['type'] === 'breakup' && !empty($e['data']['good_terms_requested'])) {
                    $e['my_vote'] = Soiree::myVote('terms', (int)$e['id'], $me);
                    $e['votes_cast'] = array_sum(Soiree::tally('terms', (int)$e['id'], (int)$e['player_id']));
                    $terms[] = $e;
                }
            }
            $prizes = Soiree::prizes($yid);
            foreach ($prizes as &$pz) {
                $pz['my_vote'] = Soiree::myVote('prize', (int)$pz['id'], $me);
            }
            $data += [
                'parts' => $parts, 'mine' => Soiree::accusationsBy($yid, $me), 'absent' => $absent, 'terms' => $terms,
                'prizes' => $prizes, 'players' => Players::map(), 'me' => $me,
            ];
        }
        Http::render('soiree', $data);
    }

    private static function openYear(): array
    {
        $year = Years::current();
        if (!$year || $year['status'] !== 'soiree') {
            Http::flash('error', 'La soirée n\'est pas ouverte.');
            Http::redirect('/soiree');
        }
        return $year;
    }

    public static function accuse(): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        $year = self::openYear();
        if (!Years::isParticipant((int)$year['id'], (int)$u['id'])) {
            Http::flash('error', 'Tu ne participes pas à cette année.');
            Http::redirect('/soiree');
        }
        $err = Soiree::toggleAccusation((int)$year['id'], (int)$u['id'], (int)Http::post('target'));
        if ($err) {
            Http::flash('error', $err);
        }
        Http::redirect('/soiree');
    }

    public static function vote(): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        $year = self::openYear();
        $me = (int)$u['id'];
        $kind = Http::post('kind');
        $ref = (int)Http::post('ref');
        $choice = Http::post('choice');
        if (!Years::isParticipant((int)$year['id'], $me)) {
            Http::flash('error', 'Tu ne participes pas à cette année.');
            Http::redirect('/soiree');
        }
        if (in_array($kind, ['absence', 'terms'], true)) {
            $e = Entries::find($ref);
            if (!$e || (int)$e['year_id'] !== (int)$year['id'] || $e['status'] !== 'approved'
                || $e['type'] !== ($kind === 'absence' ? 'absence' : 'breakup') || !in_array($choice, ['yes', 'no'], true)) {
                Http::flash('error', 'Vote invalide.');
                Http::redirect('/soiree');
            }
            if ((int)$e['player_id'] === $me) {
                Http::flash('error', 'Tu ne votes pas sur ton propre cas.');
                Http::redirect('/soiree');
            }
            Soiree::vote($kind, $ref, $me, $choice);
        } elseif ($kind === 'prize') {
            $pz = Db::one('SELECT * FROM prizes WHERE id = ? AND year_id = ?', [$ref, $year['id']]);
            $cand = (int)$choice;
            if (!$pz || $cand === $me || !Years::isParticipant((int)$year['id'], $cand)) {
                Http::flash('error', 'Vote invalide (on ne vote pas pour soi).');
                Http::redirect('/soiree');
            }
            Soiree::vote('prize', $ref, $me, (string)$cand);
        } else {
            Http::flash('error', 'Vote invalide.');
            Http::redirect('/soiree');
        }
        Http::flash('ok', 'Vote enregistré.');
        Http::redirect('/soiree');
    }
}
