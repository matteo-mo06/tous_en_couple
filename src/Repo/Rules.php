<?php
declare(strict_types=1);

namespace TEC\Repo;

use TEC\Db;
use TEC\Scoring\Defaults;

/** Versions du barème. Chaque année est calculée avec la version qui lui est rattachée. */
final class Rules
{
    public static function find(int $id): ?array
    {
        $r = Db::one('SELECT * FROM rule_versions WHERE id = ?', [$id]);
        if ($r) {
            $r['params'] = json_decode_array($r['params']);
        }
        return $r;
    }

    public static function all(): array
    {
        $rows = Db::all('SELECT * FROM rule_versions ORDER BY effective_year, id');
        foreach ($rows as &$r) {
            $r['params'] = json_decode_array($r['params']);
        }
        return $rows;
    }

    public static function latest(): ?array
    {
        $r = Db::one('SELECT * FROM rule_versions ORDER BY id DESC LIMIT 1');
        if ($r) {
            $r['params'] = json_decode_array($r['params']);
        }
        return $r;
    }

    public static function create(string $label, int $effectiveYear, array $params, ?string $note = null): int
    {
        return Db::insert('rule_versions', [
            'label' => $label, 'effective_year' => $effectiveYear,
            'params' => json_encode($params, JSON_UNESCAPED_UNICODE), 'note' => $note, 'created_at' => now(),
        ]);
    }

    /** Crée la version de départ (barème final de l'année 0) si la base n'en a pas. */
    public static function ensureInitial(int $effectiveYear): int
    {
        $l = self::latest();
        if ($l) {
            return (int)$l['id'];
        }
        return self::create('Barème final (amendements de l\'année 0)', $effectiveYear, Defaults::params(),
            'Version de départ : voir le document « Amendements de l\'année 0 ».');
    }

    /**
     * Interprète la valeur saisie pour un paramètre (« 3 », « -5 », « 0,8 ») selon le type du paramètre actuel.
     * @return array{0:mixed,1:?string} [valeur, erreur]
     */
    public static function parseValue(array $params, string $path, string $raw): array
    {
        $labels = Defaults::labels();
        if (!isset($labels[$path])) {
            return [null, 'Paramètre inconnu.'];
        }
        $cur = Defaults::get($params, $path);
        $raw = trim(str_replace(',', '.', str_replace(['−', '–'], '-', $raw)));
        if ($raw === '' || !is_numeric($raw)) {
            return [null, 'La valeur doit être un nombre.'];
        }
        if (is_int($cur)) {
            if ((float)$raw != (int)$raw) {
                return [null, 'La valeur doit être un nombre entier.'];
            }
            return [(int)$raw, null];
        }
        return [(float)$raw, null];
    }
}
