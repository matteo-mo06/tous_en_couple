<?php
declare(strict_types=1);

namespace TEC;

use PDO;

/** Connexion unique à la base (PDO). Même code pour SQLite et MySQL. */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            if ($c['driver'] === 'mysql') {
                $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
                self::$pdo = new PDO($dsn, $c['user'], $c['pass']);
            } else {
                $dir = dirname($c['sqlite_path']);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }
                self::$pdo = new PDO('sqlite:' . $c['sqlite_path']);
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA journal_mode = WAL');
                self::$pdo->exec('PRAGMA busy_timeout = 5000');
            }
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    public static function driver(): string
    {
        return (string)Config::get('db.driver');
    }

    /** @return array[] */
    public static function all(string $sql, array $args = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $args = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function val(string $sql, array $args = [])
    {
        $row = self::one($sql, $args);
        return $row === null ? null : reset($row);
    }

    public static function run(string $sql, array $args = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($args);
        return $st->rowCount();
    }

    /** INSERT et renvoie l'identifiant créé. */
    public static function insert(string $table, array $data): int
    {
        $cols = implode(',', array_keys($data));
        $marks = implode(',', array_fill(0, count($data), '?'));
        self::run("INSERT INTO $table ($cols) VALUES ($marks)", array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $args = []): int
    {
        $set = implode(',', array_map(fn($k) => "$k = ?", array_keys($data)));
        return self::run("UPDATE $table SET $set WHERE $where", array_merge(array_values($data), $args));
    }

    /** Exécute $fn dans une transaction. */
    public static function transaction(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
