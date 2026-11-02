<?php
declare(strict_types=1);

namespace TEC;

/** Protection contre les falsifications de formulaire : chaque formulaire POST porte un jeton secret. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    /** À appeler au début de chaque action POST. */
    public static function check(): void
    {
        $sent = $_POST['_csrf'] ?? '';
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            Http::abort(403, 'Formulaire expiré. Recharge la page et recommence.');
        }
    }
}
