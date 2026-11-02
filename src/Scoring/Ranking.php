<?php
declare(strict_types=1);

namespace TEC\Scoring;

/** Classements, points globaux, cadeaux, arrivée d'un joueur. Fonctions pures. */
final class Ranking
{
    /** rang = 1 + nombre de joueurs strictement meilleurs (les ex aequo partagent la meilleure place) */
    public static function ranks(array $scores): array
    {
        $out = [];
        foreach ($scores as $id => $s) {
            $better = 0;
            foreach ($scores as $s2) {
                if ($s2 > $s) {
                    $better++;
                }
            }
            $out[$id] = 1 + $better;
        }
        return $out;
    }

    /** Points globaux d'après le rang annuel (3 / 2 / 1 / 0 ...). */
    public static function globalPoints(array $ranks, array $gpts): array
    {
        $out = [];
        foreach ($ranks as $id => $r) {
            $out[$id] = (int)($gpts[$r - 1] ?? 0);
        }
        return $out;
    }

    /** Usure : global = coefficient × global précédent + points de l'année (une décimale). */
    public static function updateGlobal(float $prev, int $pts, float $decay): float
    {
        return round($prev * $decay + $pts, 1);
    }

    /** Derniers du classement (rattrapage). Personne si tous sont à égalité ou s'il y a moins de 2 joueurs. */
    public static function lasts(array $scores): array
    {
        if (count($scores) < 2) {
            return [];
        }
        $min = min($scores);
        $ids = array_keys(array_filter($scores, fn($s) => $s === $min));
        return count($ids) === count($scores) ? [] : $ids;
    }

    /**
     * Cadeaux annuels en miroir : le dernier offre au premier, l'avant-dernier au deuxième, etc.
     * Le joueur du milieu (effectif impair) est neutre. 'tie' = un des deux a un ex aequo (cadeaux de même valeur).
     */
    public static function gifts(array $scores): array
    {
        $ids = array_keys($scores);
        usort($ids, fn($a, $b) => [$scores[$b], $a] <=> [$scores[$a], $b]);
        $ranks = self::ranks($scores);
        $count = array_count_values($ranks);
        $k = count($ids);
        $gifts = [];
        for ($j = 0; $j < intdiv($k, 2); $j++) {
            $giver = $ids[$k - 1 - $j];
            $receiver = $ids[$j];
            $gifts[] = [
                'giver' => $giver, 'receiver' => $receiver,
                'tie' => $count[$ranks[$giver]] > 1 || $count[$ranks[$receiver]] > 1,
            ];
        }
        return $gifts;
    }

    /**
     * Cadeau global : le plus haut global ; égalité -> plus grosse somme des points annuels sur le cycle ;
     * égalité persistante -> tous les ex aequo reçoivent.
     * @param array $globals id => global ; @param array $sums id => somme des scores annuels du cycle
     */
    public static function cycleWinners(array $globals, array $sums): array
    {
        if (!$globals) {
            return [];
        }
        $top = max(array_map(fn($g) => (int)round($g * 10), $globals));
        $cands = array_keys(array_filter($globals, fn($g) => (int)round($g * 10) === $top));
        if (count($cands) > 1) {
            $best = max(array_map(fn($id) => $sums[$id] ?? 0, $cands));
            $cands = array_values(array_filter($cands, fn($id) => ($sums[$id] ?? 0) === $best));
        }
        return $cands;
    }

    /**
     * Points de départ d'un nouveau joueur : max(0 ; global du dernier − H),
     * H = min(3 ; max(1 ; écart médian entre joueurs consécutifs, arrondi au supérieur)).
     * @return array{0:float,1:int} [départ, H]
     */
    public static function entryStart(array $globals): array
    {
        $s = array_values($globals);
        rsort($s);
        if (count($s) < 2) {
            return [0.0, 1];
        }
        $gaps = [];
        for ($i = 0; $i < count($s) - 1; $i++) {
            $gaps[] = $s[$i] - $s[$i + 1];
        }
        sort($gaps);
        $n = count($gaps);
        $median = $n % 2 ? $gaps[intdiv($n, 2)] : ($gaps[$n / 2 - 1] + $gaps[$n / 2]) / 2;
        $h = (int)min(3, max(1, ceil(round($median, 6))));
        return [max(0.0, round($s[count($s) - 1] - $h, 1)), $h];
    }
}
