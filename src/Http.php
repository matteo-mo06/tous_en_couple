<?php
declare(strict_types=1);

namespace TEC;

/** Requête, redirections, messages flash, rendu des pages. */
final class Http
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** Chemin demandé, sans le dossier de l'application ni la query string. */
    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = base_path();
        if ($base !== '' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '/index.php' ? '/' : $uri;
    }

    public static function post(string $key, $default = ''): string
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : (string)$default;
    }

    public static function postArray(string $key): array
    {
        $v = $_POST[$key] ?? [];
        return is_array($v) ? $v : [];
    }

    public static function get(string $key, $default = ''): string
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : (string)$default;
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    public static function flash(string $type, string $msg): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
    }

    public static function takeFlash(): array
    {
        $f = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $f;
    }

    public static function abort(int $code, string $msg = ''): never
    {
        http_response_code($code);
        $titles = [403 => 'Accès refusé', 404 => 'Page introuvable', 500 => 'Erreur'];
        self::render('error', ['code' => $code, 'title' => $titles[$code] ?? 'Erreur', 'msg' => $msg]);
        exit;
    }

    /** Affiche templates/$tpl.php dans la mise en page commune. */
    public static function render(string $tpl, array $vars = []): void
    {
        $vars['_user'] = Auth::user();
        $vars['_flash'] = self::takeFlash();
        $vars['_tpl'] = $tpl;
        extract($vars, EXTR_SKIP);
        ob_start();
        require dirname(__DIR__) . '/templates/' . $tpl . '.php';
        $content = ob_get_clean();
        $title = $vars['title'] ?? Config::get('app.name');
        require dirname(__DIR__) . '/templates/layout.php';
    }
}
