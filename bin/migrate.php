<?php
declare(strict_types=1);
// Met à jour la base : php bin/migrate.php
require __DIR__ . '/../src/autoload.php';
date_default_timezone_set(TEC\Config::get('app.timezone', 'Europe/Paris'));
$applied = TEC\Migrator::run();
echo $applied ? "Migrations appliquées : " . implode(', ', $applied) . "\n" : "Base déjà à jour.\n";
