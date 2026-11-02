<?php
declare(strict_types=1);

// Lance tous les tests : php tests/run.php
require __DIR__ . '/../src/autoload.php';

$GLOBALS['ok'] = 0;
$GLOBALS['fail'] = 0;

function check(string $name, bool $cond, string $info = ''): void
{
    if ($cond) {
        $GLOBALS['ok']++;
        if (getenv('VERBOSE')) {
            echo "ok   $name\n";
        }
    } else {
        $GLOBALS['fail']++;
        echo "ECHEC $name $info\n";
    }
}

foreach (glob(__DIR__ . '/*Test.php') as $file) {
    echo '== ' . basename($file) . "\n";
    require $file;
}

echo "\n{$GLOBALS['ok']} contrôles réussis, {$GLOBALS['fail']} échecs\n";
exit($GLOBALS['fail'] ? 1 : 0);
