<?php
declare(strict_types=1);

// Point d'entrée unique : toutes les pages passent par ici (voir .htaccess).
require __DIR__ . '/../src/autoload.php';

use TEC\Auth;
use TEC\Config;
use TEC\Http;
use TEC\Router;

date_default_timezone_set((string)Config::get('app.timezone', 'Europe/Paris'));
mb_internal_encoding('UTF-8');

if (!Config::get('app.debug')) {
    set_exception_handler(function (\Throwable $e) {
        error_log((string)$e);
        http_response_code(500);
        echo '<!doctype html><meta charset="utf-8"><title>Erreur</title><p>Une erreur est survenue. Réessaie dans un instant.</p>';
    });
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

// Fichiers statiques servis par le serveur de développement de PHP
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($f) && basename($f) !== 'index.php') {
        return false;
    }
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'");

Auth::startSession();

$router = new Router();
require __DIR__ . '/../src/routes.php';
$router->dispatch(Http::method(), Http::path());
