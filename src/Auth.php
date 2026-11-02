<?php
declare(strict_types=1);

namespace TEC;

/** Session, connexion, rôles. */
final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $secure = Config::get('app.secure_cookies');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => base_path() ?: '/', 'httponly' => true,
            'samesite' => 'Lax', 'secure' => $secure ?? $https,
        ]);
        session_name('tec_session');
        session_start();
    }

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['uid'] ?? null;
            if ($id) {
                $u = Db::one('SELECT * FROM players WHERE id = ? AND active = 1', [$id]);
                self::$user = $u;
                if (!$u) {
                    unset($_SESSION['uid']);
                }
            }
        }
        return self::$user;
    }

    public static function id(): int
    {
        return (int)(self::user()['id'] ?? 0);
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? '') === 'admin';
    }

    public static function requireLogin(): array
    {
        $u = self::user();
        if (!$u) {
            Http::redirect('/connexion');
        }
        // Mot de passe provisoire : il faut en choisir un avant toute autre page
        if ($u['must_change'] && Http::path() !== '/mot-de-passe' && Http::path() !== '/deconnexion') {
            Http::flash('info', 'Choisis ton mot de passe personnel pour continuer.');
            Http::redirect('/mot-de-passe');
        }
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::requireLogin();
        if ($u['role'] !== 'admin') {
            Http::abort(403, 'Cette page est réservée à l\'administrateur.');
        }
        return $u;
    }

    /** @return string|null message d'erreur, ou null si connecté */
    public static function attempt(string $login, string $password): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
        $since = date('Y-m-d H:i:s', time() - 900);
        $fails = (int)Db::val(
            'SELECT COUNT(*) FROM login_attempts WHERE login = ? AND ip = ? AND attempted_at > ?',
            [mb_strtolower($login), $ip, $since]
        );
        if ($fails >= 5) {
            return 'Trop d\'essais. Réessaie dans un quart d\'heure.';
        }
        $u = Db::one('SELECT * FROM players WHERE login = ? AND active = 1', [mb_strtolower($login)]);
        // Même temps de calcul qu'un compte existe ou non (pas d'indice sur les identifiants valides)
        $hash = $u['password_hash'] ?? password_hash('x', PASSWORD_DEFAULT);
        if (!password_verify($password, $hash) || !$u) {
            Db::insert('login_attempts', ['login' => mb_strtolower($login), 'ip' => $ip, 'attempted_at' => now()]);
            return 'Identifiant ou mot de passe incorrect.';
        }
        Db::run('DELETE FROM login_attempts WHERE login = ?', [mb_strtolower($login)]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        self::$loaded = false;
        return null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$loaded = false;
        self::$user = null;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
