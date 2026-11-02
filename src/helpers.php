<?php
declare(strict_types=1);

// Petites fonctions utilisées partout dans les pages.

/** Échappe pour l'HTML. À utiliser pour TOUT texte venant d'un utilisateur. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Dossier de l'application dans l'URL (vide si le site est à la racine). */
function base_path(): string
{
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $dir === '.' ? '' : $dir;
}

function url(string $path = '/'): string
{
    return base_path() . $path;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function fr_date(?string $d): string
{
    if (!$d) {
        return '—';
    }
    $t = strtotime($d);
    return $t ? date('d/m/Y', $t) : e($d);
}

function fr_datetime(?string $d): string
{
    $t = $d ? strtotime($d) : false;
    return $t ? date('d/m/Y H:i', $t) : '—';
}

/** Nombre signé : +3, −5, 0 */
function signed($n): string
{
    $n = (float)$n;
    $s = rtrim(rtrim(number_format(abs($n), 1, ',', ''), '0'), ',');
    if ($s === '') {
        $s = '0';
    }
    if ($n > 0) {
        return '+' . $s;
    }
    return $n < 0 ? '−' . $s : '0';
}

function num($n): string
{
    $s = rtrim(rtrim(number_format((float)$n, 1, ',', ' '), '0'), ',');
    return $s === '' ? '0' : $s;
}

function json_decode_array(?string $json): array
{
    $v = $json ? json_decode($json, true) : null;
    return is_array($v) ? $v : [];
}

/** Icône SVG (définie dans le sprite de templates/partials/icons.php). */
function icon(string $name, string $class = ''): string
{
    return '<svg class="i' . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"/></svg>';
}

const FR_MONTHS = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

/** « mars 2027 » à partir de « 2027-03 ». */
function fr_month(string $ym): string
{
    [$y, $m] = array_map('intval', explode('-', $ym) + [0, 0]);
    return (FR_MONTHS[$m] ?? '?') . ' ' . $y;
}

/** « 12 mars » ou « 12 → 20 mars » (ou avec les deux mois si besoin), année en plus si $withYear. */
function fr_period(string $start, ?string $end, bool $withYear = false): string
{
    $a = new DateTimeImmutable($start);
    $fmt = fn(DateTimeImmutable $d, bool $month = true, bool $year = false) => (int)$d->format('j') . ($month ? ' ' . FR_MONTHS[(int)$d->format('n')] : '') . ($year ? ' ' . $d->format('Y') : '');
    if (!$end || $end === $start) {
        return $fmt($a, true, $withYear);
    }
    $b = new DateTimeImmutable($end);
    if ($a->format('Y-m') === $b->format('Y-m')) {
        return $fmt($a, false) . ' → ' . $fmt($b, true, $withYear);
    }
    return $fmt($a, true, $withYear && $a->format('Y') !== $b->format('Y')) . ' → ' . $fmt($b, true, $withYear);
}
