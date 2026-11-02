<?php
declare(strict_types=1);

namespace TEC\Repo;

use TEC\Db;

/**
 * Journal personnel : événements et moments marquants de l'année, sans effet sur les points.
 * Confidentialité : seul l'auteur voit ses événements. Ceux marqués « partagés » apparaissent dans le rewind
 * de tous une fois la soirée ouverte ; les autres restent privés pour toujours. L'administrateur n'a aucun accès spécial.
 */
final class Journal
{
    /** type => [libellé, icône, numéro de couleur] */
    public const KINDS = [
        'crush' => ['Crush', 'heart', 1],
        'situationship' => ['Situationship', 'flame', 2],
        'date' => ['Rendez-vous, sortie, double date', 'sparkles', 3],
        'couple' => ['Moment de couple', 'ring', 4],
        'breakup' => ['Rupture, fin', 'x', 5],
        'travel' => ['Voyage, déplacement', 'plane', 6],
        'memory' => ['Souvenir marquant', 'star', 7],
        'other' => ['Autre', 'pin', 8],
    ];

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM journal WHERE id = ?', [$id]);
    }

    /** @return array [données nettoyées|null, erreur|null] */
    public static function validate(array $in): array
    {
        $s = fn(string $k) => trim((string)($in[$k] ?? ''));
        $kind = $s('kind');
        if (!isset(self::KINDS[$kind])) {
            return [null, 'Choisis un type d\'événement.'];
        }
        $title = mb_substr($s('title'), 0, 120);
        if ($title === '') {
            return [null, 'Donne un titre à l\'événement.'];
        }
        $start = $s('start_date');
        if (!self::validDate($start)) {
            return [null, 'La date de début est invalide.'];
        }
        $end = $s('end_date');
        if ($end !== '') {
            if (!self::validDate($end)) {
                return [null, 'La date de fin est invalide.'];
            }
            if ($end < $start) {
                return [null, 'La fin ne peut pas être avant le début.'];
            }
        }
        return [[
            'kind' => $kind, 'title' => $title, 'note' => mb_substr($s('note'), 0, 1000) ?: null,
            'start_date' => $start, 'end_date' => ($end !== '' && $end !== $start) ? $end : null,
            'shared' => !empty($in['shared']) ? 1 : 0,
        ], null];
    }

    private static function validDate(string $d): bool
    {
        $x = \DateTimeImmutable::createFromFormat('!Y-m-d', $d);
        return $x !== false && $x->format('Y-m-d') === $d;
    }

    /** @return array [id|null, erreur|null] */
    public static function create(int $playerId, int $yearId, array $in): array
    {
        [$d, $err] = self::validate($in);
        if ($err !== null) {
            return [null, $err];
        }
        $d += ['player_id' => $playerId, 'year_id' => $yearId, 'created_at' => now()];
        return [Db::insert('journal', $d), null];
    }

    /** Un événement privé se modifie toujours ; un événement partagé, tant que l'année est ouverte aux déclarations. */
    public static function canEdit(array $event, int $playerId): bool
    {
        if ((int)$event['player_id'] !== $playerId) {
            return false;
        }
        if (!(int)$event['shared']) {
            return true;
        }
        $year = Years::find((int)$event['year_id']);
        return $year !== null && $year['status'] === 'open';
    }

    public static function update(int $id, int $playerId, array $in): ?string
    {
        $e = self::find($id);
        if (!$e || !self::canEdit($e, $playerId)) {
            return 'Cet événement n\'existe pas ou n\'est plus modifiable.';
        }
        [$d, $err] = self::validate($in);
        if ($err !== null) {
            return $err;
        }
        Db::update('journal', $d, 'id = ?', [$id]);
        return null;
    }

    public static function delete(int $id, int $playerId): bool
    {
        $e = self::find($id);
        if (!$e || !self::canEdit($e, $playerId)) {
            return false;
        }
        Db::run('DELETE FROM journal WHERE id = ?', [$id]);
        return true;
    }

    /** Tous les événements d'un joueur (c'est lui seul qui les voit). */
    public static function forPlayer(int $playerId): array
    {
        return Db::all('SELECT * FROM journal WHERE player_id = ? ORDER BY start_date, id', [$playerId]);
    }

    public static function revealed(?array $year): bool
    {
        return $year !== null && in_array($year['status'], ['soiree', 'closed'], true);
    }

    /**
     * Événements d'une année tels que $viewerId a le droit de les voir : les siens (privés compris), et ceux des
     * autres seulement s'ils sont partagés et que la soirée est ouverte.
     */
    public static function visibleForYear(array $year, int $viewerId): array
    {
        $sql = 'SELECT j.*, p.name AS player_name FROM journal j JOIN players p ON p.id = j.player_id WHERE j.year_id = ? AND (j.player_id = ?';
        if (self::revealed($year)) {
            $sql .= ' OR j.shared = 1';
        }
        return Db::all($sql . ') ORDER BY j.start_date, j.id', [$year['id'], $viewerId]);
    }

    /** Années dont le rewind est ouvert (soirée en cours ou clôturée), de la plus récente à la plus ancienne. */
    public static function revealedYears(): array
    {
        return Db::all("SELECT * FROM game_years WHERE status IN ('soiree', 'closed') ORDER BY year DESC");
    }
}
