<?php
declare(strict_types=1);
/**
 * Première installation : php bin/install.php [--admin=login] [--nom="Prénom"] [--annee=2027]
 * Crée les tables, le barème de départ, le compte administrateur et la première année de jeu.
 * Le mot de passe provisoire est affiché une seule fois ; il sera à changer à la première connexion.
 */
require __DIR__ . '/../src/autoload.php';
date_default_timezone_set(TEC\Config::get('app.timezone', 'Europe/Paris'));

$opt = [];
foreach (array_slice($argv, 1) as $a) {
    if (preg_match('/^--(\w+)=(.*)$/', $a, $m)) {
        $opt[$m[1]] = $m[2];
    }
}
$login = $opt['admin'] ?? 'matt';
$name = $opt['nom'] ?? 'Matt';
$year = (int)($opt['annee'] ?? 2027);

TEC\Migrator::run();
if (TEC\Db::val('SELECT COUNT(*) FROM players') > 0) {
    fwrite(STDERR, "Déjà installé (des joueurs existent). Utilise l'interface d'administration.\n");
    exit(1);
}
$ruleId = TEC\Repo\Rules::ensureInitial($year);
$password = $opt['motdepasse'] ?? substr(strtr(base64_encode(random_bytes(9)), '+/=', 'abc'), 0, 12);
[$id, $err] = TEC\Repo\Players::create($name, $login, $password, 'admin', null, true, null, $year);
if ($err) {
    fwrite(STDERR, $err . "\n");
    exit(1);
}
TEC\Repo\Years::create($year, $ruleId);
echo "Installation terminée.\n";
echo "  Administrateur : $login\n  Mot de passe provisoire : $password\n  Première année : $year\n";
