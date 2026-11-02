<?php
declare(strict_types=1);

namespace TEC;

/** Lit config.php (non versionné). Voir config.example.php. */
final class Config
{
    private static ?array $cfg = null;

    public static function all(): array
    {
        if (self::$cfg === null) {
            $root = dirname(__DIR__);
            $file = getenv('TEC_CONFIG') ?: $root . '/config.php';
            $user = is_file($file) ? (require $file) : [];
            self::$cfg = array_replace_recursive(self::defaults($root), is_array($user) ? $user : []);
        }
        return self::$cfg;
    }

    public static function get(string $path, $default = null)
    {
        $cur = self::all();
        foreach (explode('.', $path) as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) {
                return $default;
            }
            $cur = $cur[$k];
        }
        return $cur;
    }

    /** Pour les tests : remplace la configuration. */
    public static function override(array $cfg): void
    {
        self::$cfg = array_replace_recursive(self::defaults(dirname(__DIR__)), $cfg);
    }

    private static function defaults(string $root): array
    {
        return [
            'db' => [
                'driver' => 'sqlite',                       // sqlite | mysql
                'sqlite_path' => $root . '/storage/app.sqlite',
                'host' => '127.0.0.1', 'port' => 3306, 'name' => 'tous_en_couple',
                'user' => '', 'pass' => '',
            ],
            'app' => [
                'name' => 'Tous en couple',
                'timezone' => 'Europe/Paris',
                'debug' => false,
                'secure_cookies' => null,                   // null = automatique (HTTPS détecté)
            ],
        ];
    }
}
