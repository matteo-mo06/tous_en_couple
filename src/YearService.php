<?php
declare(strict_types=1);

namespace TEC;

use TEC\Repo\Amendments;
use TEC\Repo\Entries;
use TEC\Repo\Players;
use TEC\Repo\Rules;
use TEC\Repo\Soiree;
use TEC\Repo\Years;
use TEC\Scoring\Defaults;
use TEC\Scoring\Ranking;
use TEC\Scoring\YearScorer;

/**
 * Fait le lien entre la base et le moteur de calcul : rassemble ce qui a été validé pour une année,
 * le donne au moteur, et (à la clôture) range le résultat dans la base.
 */
final class YearService
{
    /** Dernier résultat connu d'un joueur avant l'année $yearNumber. */
    private static function previousRow(int $playerId, int $yearNumber): ?array
    {
        return Db::one(
            'SELECT yr.* FROM year_results yr JOIN game_years gy ON gy.id = yr.year_id
             WHERE yr.player_id = ? AND gy.year < ? ORDER BY gy.year DESC LIMIT 1',
            [$playerId, $yearNumber]
        );
    }

    /** Aperçu complet de l'année : rien n'est enregistré. */
    public static function compute(int $yearId): array
    {
        $year = Years::find($yearId);
        $rules = Rules::find((int)$year['rule_version_id']);
        $P = $rules['params'];
        $parts = Years::participants($yearId);
        $entries = Entries::forYear($yearId, 'approved');
        $accs = Soiree::allAccusations($yearId);
        $problems = [];
        $warnings = [];

        // Rattrapage : derniers de l'année précédente
        $prevYear = Years::lastClosed((int)$year['year']);
        $lastIds = [];
        if ($prevYear) {
            $prevScores = [];
            foreach (Db::all('SELECT player_id, score FROM year_results WHERE year_id = ?', [$prevYear['id']]) as $r) {
                $prevScores[(int)$r['player_id']] = (int)$r['score'];
            }
            $lastIds = Ranking::lasts($prevScores);
        }

        $inputs = [];
        $names = [];
        $before = [];
        foreach ($parts as $yp) {
            $pid = (int)$yp['player_id'];
            $names[$pid] = $yp['name'];
            $prev = self::previousRow($pid, (int)$year['year']);
            $state = $prev ? (json_decode_array($prev['state']) + YearScorer::newState()) : YearScorer::newState();
            $p = Players::find($pid);
            $before[$pid] = $prev ? (float)$prev['global_after'] : (float)($p['start_global'] ?? 0);

            $mine = array_values(array_filter($entries, fn($e) => (int)$e['player_id'] === $pid));
            $ofType = fn(string $t) => array_values(array_filter($mine, fn($e) => $e['type'] === $t));

            // statut de relation : dernière déclaration validée, sinon celui de l'an passé
            $status = $state['partner'] !== null ? 'couple' : 'single';
            $partner = $state['partner_name'] ?? $state['partner'];
            $rel = $ofType('relation');
            if ($rel) {
                $d = end($rel)['data'];
                $status = $d['status'];
                $partner = $d['partner'] ?? null;
            }

            $present = $yp['present'] === null ? null : (bool)(int)$yp['present'];
            if ($present === null) {
                $problems[] = $yp['name'] . ' : présence non renseignée.';
            }

            $noticeOk = false;
            $valid = false;
            $abs = $ofType('absence');
            if ($present === false && $abs) {
                $a = end($abs);
                if (!empty($year['meeting_date'])) {
                    $days = (strtotime($year['meeting_date']) - strtotime(substr($a['created_at'], 0, 10))) / 86400;
                    $noticeOk = $days >= (int)$P['notice_days'];
                } else {
                    $warnings[] = 'Date de réunion non renseignée : le préavis d\'absence ne peut pas être vérifié.';
                }
                $valid = Soiree::majorityYes('absence', (int)$a['id'], $pid);
            }

            $breakups = [];
            foreach ($ofType('breakup') as $b) {
                $good = !empty($b['data']['good_terms_requested']) && Soiree::majorityYes('terms', (int)$b['id'], $pid);
                $breakups[] = ['kind' => $b['data']['kind'], 'couple_continues' => !empty($b['data']['couple_continues']), 'good_terms' => $good];
            }
            $bluff = $ofType('bluff');

            $inputs[$pid] = [
                'id' => $pid,
                'present' => $present ?? true,
                'late' => (bool)(int)$yp['late'],
                'absence_notice_ok' => $noticeOk,
                'absence_valid' => $valid,
                'status' => $status, 'partner' => $partner,
                'partner_presence' => $yp['partner_presence'],
                'steps' => array_map(fn($e) => $e['data']['step'], $ofType('step')),
                'breakups' => $breakups,
                'cheating' => (bool)$ofType('cheating'),
                'hidden_breakup' => (bool)$ofType('hidden_breakup'),
                'bluff' => $bluff ? end($bluff)['data']['fake_partner'] : null,
                'attempt' => (bool)$ofType('attempt'),
                'travel_band' => (int)$yp['travel_band'],
                'host' => (int)$year['host_id'] === $pid,
                'prizes' => Soiree::prizeCount($yearId, $pid),
                'prev_last' => in_array($pid, $lastIds, true),
                'accusations' => array_map('intval', array_column(array_filter($accs, fn($a) => (int)$a['accuser_id'] === $pid), 'target_id')),
                'state' => $state,
            ];
        }

        if (!$year['host_id']) {
            $warnings[] = 'Aucun hôte désigné : personne ne recevra le bonus d\'hôte.';
        }
        foreach (Soiree::prizes($yearId) as $pz) {
            if (!$pz['winner_id']) {
                $warnings[] = 'Le prix « ' . $pz['label'] . ' » n\'a pas de gagnant.';
            }
        }
        if (count($parts) < 2) {
            $problems[] = 'Il faut au moins 2 joueurs.';
        }

        $res = YearScorer::score($P, $inputs);
        $scores = [];
        foreach ($res as $pid => $r) {
            $scores[$pid] = $r['score'];
            // on garde le nom d'affichage du partenaire dans l'état, pour le report d'une année sur l'autre
            $res[$pid]['state']['partner_name'] = $inputs[$pid]['status'] === 'couple' ? $inputs[$pid]['partner'] : null;
        }
        $ranks = Ranking::ranks($scores);
        $gp = Ranking::globalPoints($ranks, $P['gpts']);
        $after = [];
        foreach ($scores as $pid => $_) {
            $after[$pid] = Ranking::updateGlobal($before[$pid], $gp[$pid], (float)$P['decay']);
        }
        $gRanks = Ranking::ranks($after);
        $gifts = Ranking::gifts($scores);

        // Cadeau global tous les N ans (N = cycle_years), compté depuis la première année du jeu
        $cycle = null;
        $first = Years::firstYear();
        $n = (int)$P['cycle_years'];
        if ($first !== null && $n > 0 && (((int)$year['year'] - $first + 1) % $n) === 0) {
            $sums = $scores;
            $rows = Db::all(
                'SELECT yr.player_id, SUM(yr.score) AS s FROM year_results yr JOIN game_years gy ON gy.id = yr.year_id
                 WHERE gy.year >= ? AND gy.year < ? GROUP BY yr.player_id',
                [(int)$year['year'] - $n + 1, (int)$year['year']]
            );
            foreach ($rows as $r) {
                if (isset($sums[(int)$r['player_id']])) {
                    $sums[(int)$r['player_id']] += (int)$r['s'];
                }
            }
            $cycle = ['winners' => Ranking::cycleWinners($after, $sums), 'sums' => $sums];
        }

        // Amendements de l'année : décompte
        $amend = [];
        foreach (Amendments::forYear($yearId) as $a) {
            if ($a['status'] !== 'open') {
                continue;
            }
            $t = Soiree::tally('amendment', (int)$a['id']);
            $a['yes'] = $t['yes'] ?? 0;
            $a['no'] = $t['no'] ?? 0;
            $a['adopted'] = $a['yes'] > $a['no'];   // égalité = rejet
            $amend[] = $a;
        }

        return [
            'year' => $year, 'rules' => $rules, 'names' => $names, 'results' => $res, 'ranks' => $ranks,
            'global_before' => $before, 'global_points' => $gp, 'global_after' => $after, 'global_ranks' => $gRanks,
            'gifts' => $gifts, 'cycle' => $cycle, 'amendments' => $amend, 'problems' => $problems, 'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * Prépare la version du barème de l'année suivante : barème actuel + amendements adoptés qui modifient une valeur.
     * @return array{0:array,1:int[]} [paramètres, ids des amendements appliqués]
     */
    public static function nextParams(array $currentParams, array $amendments): array
    {
        $params = $currentParams;
        $applied = [];
        foreach ($amendments as $a) {
            if ($a['adopted'] && $a['param_key']) {
                $v = json_decode((string)$a['param_value'], true);
                if (Defaults::get($params, $a['param_key']) !== null) {
                    $params = Defaults::with($params, $a['param_key'], $v);
                    $applied[] = (int)$a['id'];
                }
            }
        }
        return [$params, $applied];
    }

    /** Enregistre définitivement l'année : résultats, cadeaux, amendements, puis ouvre l'année suivante. */
    public static function close(int $yearId, int $actorId): array
    {
        $c = self::compute($yearId);
        if ($c['problems']) {
            return ['ok' => false, 'errors' => $c['problems']];
        }
        $year = $c['year'];
        if ($year['status'] === 'closed') {
            return ['ok' => false, 'errors' => ['Cette année est déjà clôturée.']];
        }
        Db::transaction(function () use ($c, $year, $yearId, $actorId) {
            foreach ($c['results'] as $pid => $r) {
                Db::insert('year_results', [
                    'year_id' => $yearId, 'player_id' => $pid, 'participated' => empty($r['flags']['absent']) ? 1 : 0,
                    'rank_pos' => $c['ranks'][$pid], 'score' => $r['score'],
                    'breakdown' => json_encode($r['cats'], JSON_UNESCAPED_UNICODE),
                    'notes' => json_encode($r['notes'], JSON_UNESCAPED_UNICODE),
                    'global_before' => $c['global_before'][$pid], 'global_points' => $c['global_points'][$pid],
                    'global_after' => $c['global_after'][$pid], 'global_rank' => $c['global_ranks'][$pid],
                    'state' => json_encode($r['state'], JSON_UNESCAPED_UNICODE),
                ]);
            }

            // Amendements : adoptés / rejetés ; ceux qui changent une valeur donnent la version de l'an prochain
            [$nextParams, $appliedIds] = self::nextParams($c['rules']['params'], $c['amendments']);
            $nextVersionId = (int)$year['rule_version_id'];
            if ($appliedIds) {
                $nextVersionId = Rules::create('Barème ' . ($year['year'] + 1), (int)$year['year'] + 1, $nextParams,
                    'Amendements adoptés en ' . $year['year'] . ' : n° ' . implode(', ', $appliedIds));
            }
            foreach ($c['amendments'] as $a) {
                Db::update('amendments', [
                    'status' => $a['adopted'] ? 'adopted' : 'rejected', 'decided_at' => now(),
                    'applied_version_id' => in_array((int)$a['id'], $appliedIds, true) ? $nextVersionId : null,
                ], 'id = ?', [$a['id']]);
            }

            Db::insert('year_summary', [
                'year_id' => $yearId, 'gifts' => json_encode($c['gifts']),
                'cycle_winners' => $c['cycle'] ? json_encode($c['cycle']['winners']) : null,
                'applied_amendments' => json_encode($appliedIds),
            ]);
            Db::update('game_years', ['status' => 'closed', 'closed_at' => now()], 'id = ?', [$yearId]);
            Years::create((int)$year['year'] + 1, $nextVersionId);
        });
        return ['ok' => true, 'errors' => []];
    }
}
