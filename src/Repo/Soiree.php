<?php
declare(strict_types=1);

namespace TEC\Repo;

use TEC\Db;

/** La soirée : présence, accusations en direct, votes, prix. */
final class Soiree
{
    // ---------------------------------------------------------------- présence (saisie par l'administrateur)
    public static function setAttendance(int $yearId, int $playerId, ?int $present, bool $late, string $partnerPresence, int $band): void
    {
        if (!in_array($partnerPresence, ['present', 'absent', 'visio'], true)) {
            $partnerPresence = 'present';
        }
        Db::update('year_players', [
            'present' => $present, 'late' => $late ? 1 : 0, 'partner_presence' => $partnerPresence,
            'travel_band' => max(0, min(3, $band)),
        ], 'year_id = ? AND player_id = ?', [$yearId, $playerId]);
    }

    // ---------------------------------------------------------------- accusations
    public static function accusationsBy(int $yearId, int $accuserId): array
    {
        return array_map('intval', array_column(
            Db::all('SELECT target_id FROM accusations WHERE year_id = ? AND accuser_id = ?', [$yearId, $accuserId]),
            'target_id'
        ));
    }

    /** Pose ou retire une accusation (bascule). */
    public static function toggleAccusation(int $yearId, int $accuserId, int $targetId): ?string
    {
        if ($accuserId === $targetId) {
            return 'On ne s\'accuse pas soi-même.';
        }
        if (!Years::isParticipant($yearId, $targetId)) {
            return 'Ce joueur ne participe pas cette année.';
        }
        $row = Db::one('SELECT id FROM accusations WHERE year_id = ? AND accuser_id = ? AND target_id = ?', [$yearId, $accuserId, $targetId]);
        if ($row) {
            Db::run('DELETE FROM accusations WHERE id = ?', [$row['id']]);
        } else {
            Db::insert('accusations', ['year_id' => $yearId, 'accuser_id' => $accuserId, 'target_id' => $targetId, 'created_at' => now()]);
        }
        return null;
    }

    public static function allAccusations(int $yearId): array
    {
        return Db::all('SELECT * FROM accusations WHERE year_id = ? ORDER BY id', [$yearId]);
    }

    // ---------------------------------------------------------------- votes
    public static function vote(string $kind, int $refId, int $voterId, string $choice): void
    {
        $row = Db::one('SELECT id FROM votes WHERE kind = ? AND ref_id = ? AND voter_id = ?', [$kind, $refId, $voterId]);
        if ($row) {
            Db::update('votes', ['choice' => $choice, 'created_at' => now()], 'id = ?', [$row['id']]);
        } else {
            Db::insert('votes', ['kind' => $kind, 'ref_id' => $refId, 'voter_id' => $voterId, 'choice' => $choice, 'created_at' => now()]);
        }
    }

    public static function myVote(string $kind, int $refId, int $voterId): ?string
    {
        $v = Db::val('SELECT choice FROM votes WHERE kind = ? AND ref_id = ? AND voter_id = ?', [$kind, $refId, $voterId]);
        return $v === null ? null : (string)$v;
    }

    /** choice => nombre de voix */
    public static function tally(string $kind, int $refId, ?int $excludeVoter = null): array
    {
        $sql = 'SELECT choice, COUNT(*) AS n FROM votes WHERE kind = ? AND ref_id = ?';
        $args = [$kind, $refId];
        if ($excludeVoter !== null) {
            $sql .= ' AND voter_id <> ?';
            $args[] = $excludeVoter;
        }
        $out = [];
        foreach (Db::all($sql . ' GROUP BY choice', $args) as $r) {
            $out[$r['choice']] = (int)$r['n'];
        }
        return $out;
    }

    /** Majorité simple des votes exprimés ; l'égalité vaut refus. */
    public static function majorityYes(string $kind, int $refId, ?int $excludeVoter = null): bool
    {
        $t = self::tally($kind, $refId, $excludeVoter);
        return ($t['yes'] ?? 0) > ($t['no'] ?? 0);
    }

    // ---------------------------------------------------------------- prix et mentions
    public static function prizes(int $yearId): array
    {
        return Db::all('SELECT * FROM prizes WHERE year_id = ? ORDER BY id', [$yearId]);
    }

    public static function addPrize(int $yearId, string $label, int $max): ?string
    {
        $label = trim($label);
        if ($label === '') {
            return 'Donne un nom au prix.';
        }
        if (count(self::prizes($yearId)) >= $max) {
            return "Il y a déjà $max prix cette année (maximum du barème).";
        }
        Db::insert('prizes', ['year_id' => $yearId, 'label' => mb_substr($label, 0, 120)]);
        return null;
    }

    public static function setPrizeWinner(int $prizeId, ?int $winnerId): ?string
    {
        $p = Db::one('SELECT * FROM prizes WHERE id = ?', [$prizeId]);
        if (!$p) {
            return 'Prix introuvable.';
        }
        if ($winnerId !== null) {
            if (!Years::isParticipant((int)$p['year_id'], $winnerId)) {
                return 'Ce joueur ne participe pas cette année.';
            }
            $other = Db::one('SELECT id FROM prizes WHERE year_id = ? AND winner_id = ? AND id <> ?', [$p['year_id'], $winnerId, $prizeId]);
            if ($other) {
                return 'Un joueur ne peut recevoir qu\'un prix par an.';
            }
        }
        Db::update('prizes', ['winner_id' => $winnerId], 'id = ?', [$prizeId]);
        return null;
    }

    public static function prizeCount(int $yearId, int $playerId): int
    {
        return (int)Db::val('SELECT COUNT(*) FROM prizes WHERE year_id = ? AND winner_id = ?', [$yearId, $playerId]);
    }

    /** Candidat en tête (id) selon les votes, ou null si aucun vote. */
    public static function prizeLeader(int $prizeId): ?int
    {
        $t = self::tally('prize', $prizeId);
        if (!$t) {
            return null;
        }
        arsort($t);
        return (int)array_key_first($t);
    }
}
