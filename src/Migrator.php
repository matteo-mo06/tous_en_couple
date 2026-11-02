<?php
declare(strict_types=1);

namespace TEC;

/**
 * Applique les fichiers de migrations/ dans l'ordre. Chaque fichier renvoie une liste d'instructions SQL
 * avec deux jetons : {pk} (clé primaire auto-incrémentée) et {engine} (options de table MySQL).
 */
final class Migrator
{
    /** @return string[] noms des migrations appliquées */
    public static function run(): array
    {
        $mysql = Db::driver() === 'mysql';
        $pk = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $engine = $mysql ? 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

        Db::run('CREATE TABLE IF NOT EXISTS schema_migrations (name VARCHAR(190) PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)'
            . ($mysql ? ' ' . $engine : ''));
        $done = array_column(Db::all('SELECT name FROM schema_migrations'), 'name');

        $applied = [];
        $files = glob(dirname(__DIR__) . '/migrations/*.php');
        sort($files);
        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $done, true)) {
                continue;
            }
            $statements = require $file;
            foreach ($statements as $sql) {
                Db::run(str_replace(['{pk}', '{engine}'], [$pk, $engine], $sql));
            }
            Db::insert('schema_migrations', ['name' => $name, 'applied_at' => date('Y-m-d H:i:s')]);
            $applied[] = $name;
        }
        return $applied;
    }
}
