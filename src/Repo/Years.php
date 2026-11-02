<?php
declare(strict_types=1);

namespace TEC\Repo;

use TEC\Db;

/** Années de jeu (une par réunion) et participants. */
final class Years
{
    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM game_years WHERE id = ?', [$id]);
    }

    public static function byYear(int $year): ?array
    {
        return Db::one('SELECT * FROM game_years WHERE year = ?', [$year]);
    }

    public static function all(): array
    {
        return Db::all('SELECT * FROM game_years ORDER BY year DESC');
    }

    /** L'année en cours (la plus récente non clôturée), ou null. */
    public static function current(): ?array
    {
        return Db::one("SELECT * FROM game_years WHERE status <> 'closed' ORDER BY year DESC LIMIT 1");
    }

    public static function lastClosed(?int $beforeYear = null): ?array
    {
        if ($beforeYear === null) {
            return Db::one("SELECT * FROM game_years WHERE status = 'closed' ORDER BY year DESC LIMIT 1");
        }
        return Db::one("SELECT * FROM game_years WHERE status = 'closed' AND year < ? ORDER BY year DESC LIMIT 1", [$beforeYear]);
    }

    public static function firstYear(): ?int
    {
        $v = Db::val('SELECT MIN(year) FROM game_years');
        return $v === null ? null : (int)$v;
    }

    /** Crée l'année et y inscrit tous les joueurs actifs. */
    public static function create(int $year, int $ruleVersionId): int
    {
        $id = Db::insert('game_years', [
            'year' => $year, 'status' => 'open', 'rule_version_id' => $ruleVersionId, 'created_at' => now(),
        ]);
        foreach (Players::all(true) as $p) {
            self::addParticipant($id, (int)$p['id']);
        }
        return $id;
    }

    public static function addParticipant(int $yearId, int $playerId): void
    {
        if (!Db::one('SELECT id FROM year_players WHERE year_id = ? AND player_id = ?', [$yearId, $playerId])) {
            Db::insert('year_players', ['year_id' => $yearId, 'player_id' => $playerId]);
        }
    }

    /** Participants avec leur fiche de joueur et leur présence. */
    public static function participants(int $yearId): array
    {
        return Db::all(
            'SELECT yp.*, p.name, p.login, p.active, p.home_place FROM year_players yp
             JOIN players p ON p.id = yp.player_id WHERE yp.year_id = ? ORDER BY p.name',
            [$yearId]
        );
    }

    public static function isParticipant(int $yearId, int $playerId): bool
    {
        return (bool)Db::one('SELECT id FROM year_players WHERE year_id = ? AND player_id = ?', [$yearId, $playerId]);
    }

    public static function hostOfYear(int $yearNumber): ?int
    {
        $v = Db::val('SELECT host_id FROM game_years WHERE year = ?', [$yearNumber]);
        return $v === null ? null : (int)$v;
    }

    /** Dernier classement global connu : player_id => global (dernière année clôturée, sinon départ du joueur). */
    public static function currentGlobals(): array
    {
        $out = [];
        foreach (Players::all(true) as $p) {
            $last = Db::val(
                'SELECT yr.global_after FROM year_results yr JOIN game_years gy ON gy.id = yr.year_id
                 WHERE yr.player_id = ? ORDER BY gy.year DESC LIMIT 1',
                [$p['id']]
            );
            $out[(int)$p['id']] = $last !== null ? (float)$last : (float)($p['start_global'] ?? 0);
        }
        return $out;
    }
}
