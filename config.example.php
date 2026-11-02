<?php
// Copie ce fichier en config.php (non versionné) et adapte-le. Tout est facultatif :
// sans config.php, le site utilise SQLite dans storage/app.sqlite.
return [
    'db' => [
        'driver' => 'sqlite',                       // 'sqlite' ou 'mysql'
        // 'sqlite_path' => __DIR__ . '/storage/app.sqlite',
        // Pour MySQL (hébergement mutualisé) :
        // 'driver' => 'mysql', 'host' => 'localhost', 'port' => 3306,
        // 'name' => 'ma_base', 'user' => 'mon_user', 'pass' => 'mon_mot_de_passe',
    ],
    'app' => [
        'name' => 'Tous en couple',
        'timezone' => 'Europe/Paris',
        'debug' => false,
        'secure_cookies' => null,                   // null = automatique ; true si tout passe en HTTPS
    ],
];
