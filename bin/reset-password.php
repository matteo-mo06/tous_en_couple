<?php
declare(strict_types=1);
/**
 * Définit un mot de passe provisoire : php bin/reset-password.php <identifiant> [<mot de passe>]
 * Sans mot de passe indiqué, il est généré et affiché. Le joueur devra le changer à sa prochaine connexion.
 */
require __DIR__ . '/../src/autoload.php';
date_default_timezone_set(TEC\Config::get('app.timezone', 'Europe/Paris'));

$login = strtolower($argv[1] ?? '');
if ($login === '') {
    fwrite(STDERR, "Usage : php bin/reset-password.php <identifiant> [<mot de passe>]\n");
    exit(1);
}
$p = TEC\Db::one('SELECT id, name FROM players WHERE login = ?', [$login]);
if (!$p) {
    fwrite(STDERR, "Identifiant inconnu : $login\n");
    exit(1);
}
$password = $argv[2] ?? substr(strtr(base64_encode(random_bytes(9)), '+/=', 'abc'), 0, 12);
$err = TEC\Repo\Players::setPassword((int)$p['id'], $password, true);
if ($err) {
    fwrite(STDERR, $err . "\n");
    exit(1);
}
TEC\Db::run('DELETE FROM login_attempts WHERE login = ?', [$login]);
echo "Mot de passe provisoire de {$p['name']} ($login) : $password\n";
