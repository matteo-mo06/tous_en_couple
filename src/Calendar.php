<?php
declare(strict_types=1);

namespace TEC;

use DateTimeImmutable;

/** Construit la grille d'un mois (semaines du lundi au dimanche) avec les événements de chaque jour. */
final class Calendar
{
    /**
     * @param string $ym     « 2027-03 »
     * @param array  $events lignes du journal (start_date, end_date, ...)
     * @return array{month:string,prev:string,next:string,weeks:array}
     */
    public static function month(string $ym, array $events): array
    {
        $first = DateTimeImmutable::createFromFormat('!Y-m-d', $ym . '-01') ?: new DateTimeImmutable('first day of this month midnight');
        $ym = $first->format('Y-m');
        $gridStart = $first->modify('-' . ((int)$first->format('N') - 1) . ' days');
        $last = $first->modify('last day of this month');
        $gridEnd = $last->modify('+' . (7 - (int)$last->format('N')) . ' days');

        $byDay = [];
        foreach ($events as $e) {
            $s = max($e['start_date'], $gridStart->format('Y-m-d'));
            $end = min($e['end_date'] ?: $e['start_date'], $gridEnd->format('Y-m-d'));
            for ($d = new DateTimeImmutable($s), $n = 0; $d->format('Y-m-d') <= $end && $n < 400; $d = $d->modify('+1 day'), $n++) {
                $byDay[$d->format('Y-m-d')][] = $e;
            }
        }
        $today = date('Y-m-d');
        $weeks = [];
        for ($d = $gridStart; $d <= $gridEnd; $d = $d->modify('+7 days')) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $d->modify("+$i days");
                $key = $day->format('Y-m-d');
                $week[] = ['date' => $key, 'day' => (int)$day->format('j'), 'in' => $day->format('Y-m') === $ym,
                    'today' => $key === $today, 'events' => $byDay[$key] ?? []];
            }
            $weeks[] = $week;
        }
        return ['month' => $ym, 'prev' => $first->modify('-1 month')->format('Y-m'), 'next' => $first->modify('+1 month')->format('Y-m'), 'weeks' => $weeks];
    }

    /** Mois (« 2027-03 ») de $from à $to inclus, au plus $max. */
    public static function monthsBetween(string $from, string $to, int $max = 24): array
    {
        $out = [];
        for ($d = new DateTimeImmutable($from . '-01'); $d->format('Y-m') <= $to && count($out) < $max; $d = $d->modify('+1 month')) {
            $out[] = $d->format('Y-m');
        }
        return $out;
    }
}
