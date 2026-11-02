<?php
declare(strict_types=1);
/**
 * Données de démonstration (pour tester l'interface) : php bin/seed-demo.php
 * À lancer sur une base vide ou de test, JAMAIS sur la vraie base. Mot de passe de tous les comptes : « demo-demo ».
 */
require __DIR__ . '/../src/autoload.php';
date_default_timezone_set(TEC\Config::get('app.timezone', 'Europe/Paris'));
TEC\Migrator::run();
if (TEC\Db::val('SELECT COUNT(*) FROM players') > 0) {
    fwrite(STDERR, "La base n'est pas vide : abandon.\n");
    exit(1);
}
$year = 2027;
$ruleId = TEC\Repo\Rules::ensureInitial($year);
foreach ([['Matt', 'matt', 'admin'], ['Alice', 'alice', 'player'], ['Bob', 'bob', 'player'], ['Chloé', 'chloe', 'player'], ['David', 'david', 'player']] as [$n, $l, $r]) {
    TEC\Repo\Players::create($n, $l, 'demo-demo', $r, null, false, null, $year);
}
TEC\Repo\Years::create($year, $ruleId);
echo "Démo prête : matt / alice / bob / chloe / david, mot de passe demo-demo.\n";
