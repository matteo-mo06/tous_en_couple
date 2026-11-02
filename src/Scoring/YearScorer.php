<?php
declare(strict_types=1);

namespace TEC\Scoring;

/**
 * Calcul des points d'une réunion annuelle.
 *
 * C'est une fonction pure : elle reçoit le barème ($P) et, pour chaque joueur, ce qui s'est passé
 * (déjà validé par l'administrateur), et rend les points par catégorie. Aucun accès à la base ici,
 * pour que le calcul reste testable seul.
 *
 * Entrée par joueur (tout est facultatif, voir withDefaults) :
 *  id, present, late, absence_notice_ok, absence_valid,
 *  status ('single'|'couple'), partner (nom), partner_presence ('present'|'absent'|'visio'),
 *  steps [move_in|buy|engaged|pacs|married|child ...],
 *  breakups [['kind'=>simple|end_shared|divorce|engaged, 'couple_continues'=>bool, 'good_terms'=>bool]],
 *  cheating, hidden_breakup, bluff (nom du faux partenaire ou null), attempt,
 *  travel_band (0..3), host, prizes (nombre), prev_last (rattrapage), accusations [ids],
 *  state (état de fin de l'année précédente, voir newState)
 */
final class YearScorer
{
    public static function newState(): array
    {
        return [
            'series' => 0,        // années déjà comptées avec le partenaire actuel
            'partner' => null,
            'presented' => false, // partenaire déjà vu en personne
            'visio' => 0,         // années de suite en visio
            'steps_done' => [],   // étapes de vie déjà créditées pour cette relation
            'deferred' => 0,      // points d'étapes reportés par un gel
            'fake' => null,       // faux partenaire du dernier bluff réussi (pour le bonus « 2 ans de suite »)
        ];
    }

    public static function norm(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }
        $n = mb_strtolower(trim($name));
        return $n === '' ? null : $n;
    }

    private static function withDefaults(array $pl): array
    {
        return $pl + [
            'present' => true, 'late' => false, 'absence_notice_ok' => false, 'absence_valid' => false,
            'status' => 'single', 'partner' => null, 'partner_presence' => 'present',
            'steps' => [], 'breakups' => [], 'cheating' => false, 'hidden_breakup' => false,
            'bluff' => null, 'attempt' => false, 'travel_band' => 0, 'host' => false, 'prizes' => 0,
            'prev_last' => false, 'accusations' => [], 'state' => self::newState(),
        ];
    }

    /** @return array<int,array> résultat par id de joueur */
    public static function score(array $P, array $players): array
    {
        $byId = [];
        foreach ($players as $pl) {
            $pl = self::withDefaults($pl);
            $pl['state'] = $pl['state'] + self::newState();
            $byId[$pl['id']] = $pl;
        }

        // --- Bluffs et accusations (résolus pour toute la table avant les points individuels)
        $bluffers = [];
        foreach ($byId as $id => $pl) {
            if ($pl['present'] && $pl['status'] === 'single' && self::norm($pl['bluff']) !== null) {
                $bluffers[$id] = self::norm($pl['bluff']);
            }
        }
        $correct = [];  // cible => [accusateurs ayant raison]
        $wrong = [];    // accusateur => nombre d'accusations à tort
        foreach ($byId as $aid => $pl) {
            if (!$pl['present']) {
                continue;
            }
            foreach (array_unique($pl['accusations']) as $tid) {
                if ($tid === $aid || !isset($byId[$tid]) || !$byId[$tid]['present']) {
                    continue;
                }
                if (isset($bluffers[$tid])) {
                    $correct[$tid][] = $aid;
                } else {
                    $wrong[$aid] = ($wrong[$aid] ?? 0) + 1;
                }
            }
        }
        $sole = [];     // accusateur => nombre de bluffs démasqués par lui seul
        foreach ($correct as $tid => $accusers) {
            if (count($accusers) === 1) {
                $sole[$accusers[0]] = ($sole[$accusers[0]] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($byId as $id => $pl) {
            $out[$id] = self::one($P, $pl, isset($correct[$id]), $sole[$id] ?? 0, $wrong[$id] ?? 0);
        }
        return $out;
    }

    private static function one(array $P, array $pl, bool $bluffExposed, int $soleCount, int $wrongCount): array
    {
        $cats = [];
        $notes = [];
        $add = function (string $cat, int $v) use (&$cats): void {
            if ($v !== 0) {
                $cats[$cat] = ($cats[$cat] ?? 0) + $v;
            }
        };
        $flags = [];
        $st = $pl['state'];
        $partner = $pl['status'] === 'couple' ? self::norm($pl['partner']) : null;
        if ($pl['status'] === 'couple' && $partner === null) {
            $notes[] = 'Statut « en couple » sans nom de partenaire : traité comme célibataire.';
        }

        // --- Ruptures et malus relationnels (jamais gelés)
        $realBreak = false;
        $malusCats = [];
        foreach ($pl['breakups'] as $b) {
            $kind = $b['kind'];
            $continues = !empty($b['couple_continues']) && $kind === 'end_shared';
            if (!$continues) {
                $realBreak = true;
            }
            $m = empty($b['good_terms']) ? ($P['malus'][$kind] ?? 0) : 0;
            if (!empty($b['good_terms'])) {
                $notes[] = 'Rupture en bons termes : malus annulé, série cassée.';
            }
            $malusCats[$kind === 'end_shared' ? 'endcohab' : 'rupture'] = ($malusCats[$kind === 'end_shared' ? 'endcohab' : 'rupture'] ?? 0) + $m;
        }
        if ($pl['cheating']) {
            $malusCats['cheat'] = $P['malus']['cheat'];
        }
        if ($pl['hidden_breakup']) {
            $malusCats['hidden'] = $P['malus']['hidden'];
        }
        $applyMalus = function () use (&$malusCats, $add): void {
            foreach ($malusCats as $c => $v) {
                $add($c, (int)$v);
            }
        };

        // --- Continuité de la relation
        $sameCouple = $partner !== null && $st['partner'] !== null && $st['partner'] === $partner && !$realBreak;
        $years = $sameCouple ? (int)$st['series'] : 0;
        $stepsDone = $sameCouple ? $st['steps_done'] : [];
        $presented = $sameCouple ? (bool)$st['presented'] : false;
        $visio = $sameCouple ? (int)$st['visio'] : 0;
        $deferred = (int)$st['deferred'];

        // --- Étapes de vie nouvelles de l'année (une fois par relation, sauf enfant)
        $newSteps = 0;
        if ($partner !== null) {
            $seen = [];
            foreach ($pl['steps'] as $s) {
                if (!isset($P['steps'][$s])) {
                    continue;
                }
                if ($s !== 'child' && (in_array($s, $stepsDone, true) || isset($seen[$s]))) {
                    $notes[] = 'Étape « ' . $s . ' » déjà comptée pour cette relation : ignorée.';
                    continue;
                }
                $seen[$s] = true;
                $newSteps += (int)$P['steps'][$s];
                if ($s !== 'child') {
                    $stepsDone[] = $s;
                }
            }
        } elseif ($pl['steps']) {
            $notes[] = 'Étapes de vie sans partenaire : ignorées.';
        }

        $gelPlayer = $gelPartner = false;
        $here = false;
        $levelUsed = null;

        if (!$pl['present']) {
            // ------------------------------------------------------------ joueur absent
            $legit = $pl['absence_notice_ok'] && $pl['absence_valid'];
            $flags['absent'] = true;
            if ($legit && !$realBreak) {
                $gelPlayer = true;
                $flags['gel_player'] = true;
                $deferred += $newSteps;
                $notes[] = 'Absence justifiée : année gelée, étapes de vie reportées.';
            } else {
                if (!$legit) {
                    $add('absent', (int)$P['absent']);
                    $notes[] = 'Absence sans préavis suffisant ou sans motif accepté : aucun gain.';
                }
                if ($realBreak) {
                    $add('flight', (int)$P['flight']);
                }
            }
            $applyMalus();
        } else {
            // ------------------------------------------------------------ joueur présent
            if ($partner !== null) {
                $pp = $pl['partner_presence'];
                if ($pp === 'present') {
                    $here = true;
                } elseif ($pp === 'visio') {
                    $allowed = $presented && $visio < (int)$P['visio_max_streak'];
                    if ($allowed) {
                        $here = true;
                        $visio++;
                        $flags['visio'] = true;
                    } else {
                        $notes[] = 'Visio refusée (partenaire jamais vu en personne, ou trop d\'années de suite) : compte comme absent.';
                    }
                }
                if ($pp === 'present') {
                    $visio = 0;
                }
                if (!$here) {
                    $gelPartner = true;
                    $flags['gel_partner'] = true;
                }
            }

            if ($gelPartner) {
                $deferred += $newSteps;
                $notes[] = 'Partenaire absent : année gelée, étapes de vie reportées.';
                $applyMalus();
            } else {
                if ($partner !== null && $here) {
                    $lvl = min($years + 1, count($P['serie']));
                    $add('serie', (int)$P['serie'][$lvl - 1]);
                    $levelUsed = $lvl;
                    $years++;
                    $presented = true;
                }
                $add('steps', $newSteps + $deferred);
                $deferred = 0;
                $add('travel', (int)($P['travel'][$pl['travel_band']] ?? 0));
                if ($pl['status'] === 'single' && $pl['attempt']) {
                    $add('attempt', (int)$P['attempt']);
                }
                if ($pl['prev_last']) {
                    $add('comeback', (int)$P['comeback']);
                }
                $applyMalus();
            }

            // Hôte, prix, retard, accusations : propres à la soirée, jamais gelés
            if ($pl['host']) {
                $add('host', (int)$P['host']);
            }
            if ($pl['prizes'] > 0) {
                $add('prize', (int)$P['prize'] * (int)$pl['prizes']);
            }
            if ($pl['late']) {
                $add('late', (int)$P['late']);
            }
            if ($wrongCount > 0) {
                $add('accuse', $wrongCount * (int)$P['accuse_wrong']);
            }
            if ($soleCount > 0) {
                $add('solo', $soleCount * (int)$P['accuse_sole']);
            }
        }

        // --- Bluff (célibataire présent seulement ; ne fait ni série ni étape de vie)
        $fake = $st['fake'];
        $bluffName = ($pl['present'] && $pl['status'] === 'single') ? self::norm($pl['bluff']) : null;
        if ($bluffName !== null) {
            if ($bluffExposed) {
                $add('bluff', (int)$P['bluff_caught']);
                $flags['bluff'] = 'caught';
                $fake = null;
            } else {
                $add('bluff', (int)$P['bluff_win']);
                $flags['bluff'] = 'won';
                if ($fake !== null && $fake === $bluffName) {
                    $add('repeat', (int)$P['repeat_bonus']);
                    $fake = null;      // la chaîne repart de zéro
                } else {
                    $fake = $bluffName;
                }
            }
        } else {
            if ($pl['bluff'] !== null && self::norm($pl['bluff']) !== null && $pl['status'] !== 'single') {
                $notes[] = 'Bluff déclaré par un joueur en couple : ignoré.';
            }
            $fake = null;
        }

        // --- Total et plancher
        $total = array_sum($cats);
        $score = max((int)$P['floor_min'], $total);
        if ($score !== $total) {
            $cats['floor'] = $score - $total;
        }

        // --- État pour l'année suivante
        if ($partner !== null) {
            // (après une rupture suivie d'un nouveau partenaire, $years et $stepsDone sont déjà repartis de zéro)
            $ns = ['series' => $years, 'partner' => $partner, 'presented' => $presented, 'visio' => $visio,
                'steps_done' => array_values(array_unique($stepsDone)), 'deferred' => $deferred, 'fake' => $fake];
        } else {
            $ns = ['series' => 0, 'partner' => null, 'presented' => false, 'visio' => 0,
                'steps_done' => [], 'deferred' => $deferred, 'fake' => $fake];
        }

        return [
            'id' => $pl['id'], 'score' => $score, 'raw' => $total, 'cats' => $cats, 'notes' => $notes,
            'flags' => $flags, 'level' => $levelUsed, 'state' => $ns,
        ];
    }
}
