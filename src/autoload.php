<?php
declare(strict_types=1);

// Chargeur de classes minimal : TEC\Scoring\Ranking -> src/Scoring/Ranking.php (pas de Composer).
spl_autoload_register(function (string $class): void {
    $prefix = 'TEC\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once __DIR__ . '/helpers.php';
