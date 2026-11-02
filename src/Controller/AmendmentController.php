<?php
declare(strict_types=1);

namespace TEC\Controller;

use TEC\Auth;
use TEC\Csrf;
use TEC\Http;
use TEC\Repo\Amendments;
use TEC\Repo\Players;
use TEC\Repo\Rules;
use TEC\Repo\Soiree;
use TEC\Repo\Years;
use TEC\Scoring\Defaults;

final class AmendmentController
{
    public static function index(): void
    {
        $u = Auth::requireLogin();
        $year = Years::current();
        $list = [];
        $window = null;
        $params = [];
        if ($year) {
            $window = Amendments::window($year);
            $params = Rules::find((int)$year['rule_version_id'])['params'];
            foreach (Amendments::forYear((int)$year['id']) as $a) {
                $a['my_vote'] = Soiree::myVote('amendment', (int)$a['id'], (int)$u['id']);
                $t = Soiree::tally('amendment', (int)$a['id']);
                $a['cast'] = array_sum($t);
                // Le détail n'est montré qu'après la fermeture, pour que personne ne vote « comme la majorité »
                $a['yes'] = $t['yes'] ?? 0;
                $a['no'] = $t['no'] ?? 0;
                $a['label'] = $a['param_key'] ? (Defaults::labels()[$a['param_key']] ?? $a['param_key']) : null;
                $a['current'] = $a['param_key'] ? Defaults::get($params, $a['param_key']) : null;
                $list[] = $a;
            }
        }
        Http::render('amendments', [
            'title' => 'Amendements', 'year' => $year, 'window' => $window, 'list' => $list,
            'labels' => Defaults::labels(), 'params' => $params, 'past' => self::past(), 'showTally' => $window && !$window['open'],
        ]);
    }

    /** Amendements des années précédentes (décidés). */
    private static function past(): array
    {
        return \TEC\Db::all(
            "SELECT a.*, p.name AS proposer, gy.year FROM amendments a
             JOIN players p ON p.id = a.proposer_id JOIN game_years gy ON gy.id = a.year_id
             WHERE a.status <> 'open' ORDER BY gy.year DESC, a.id"
        );
    }

    public static function propose(): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        $year = Years::current();
        if (!$year) {
            Http::flash('error', 'Aucune année ouverte.');
            Http::redirect('/amendements');
        }
        $params = Rules::find((int)$year['rule_version_id'])['params'];
        [$id, $err] = Amendments::propose($year, (int)$u['id'], Http::post('title'), Http::post('body'),
            Http::post('param_key'), Http::post('param_value'), $params);
        Http::flash($err ? 'error' : 'ok', $err ?: 'Amendement déposé.');
        Http::redirect('/amendements');
    }

    public static function vote(string $id): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        $year = Years::current();
        $a = Amendments::find((int)$id);
        if (!$year || !$a || (int)$a['year_id'] !== (int)$year['id']) {
            Http::abort(404);
        }
        if (!Years::isParticipant((int)$year['id'], (int)$u['id'])) {
            Http::flash('error', 'Tu ne participes pas à cette année.');
            Http::redirect('/amendements');
        }
        $err = Amendments::vote($year, $a, (int)$u['id'], Http::post('choice'));
        Http::flash($err ? 'error' : 'ok', $err ?: 'Vote enregistré (tu peux le changer jusqu\'à la fermeture).');
        Http::redirect('/amendements');
    }
}
