<?php
declare(strict_types=1);

namespace TEC\Scoring;

/**
 * Barème final (« version X1 », amendements de l'année 0).
 * Ces valeurs sont stockées en base dans la table rule_versions :
 * un amendement adopté crée une nouvelle version, le code ne change pas.
 */
final class Defaults
{
    public static function params(): array
    {
        return [
            // Série : points pour la 1re, 2e, 3e, 4e, 5e année (et suivantes) avec le même partenaire
            'serie' => [2, 2, 3, 4, 5],
            // Étapes de vie (une fois par relation, sauf enfant)
            'steps' => ['move_in' => 2, 'buy' => 3, 'engaged' => 3, 'pacs' => 3, 'married' => 5, 'child' => 5],
            'bluff_win' => 3,
            'bluff_caught' => -5,
            'accuse_sole' => 2,      // seul accusateur correct
            'accuse_wrong' => -1,    // accusation à tort
            'repeat_bonus' => 1,     // même faux partenaire 2 années de suite
            'attempt' => 1,          // tentative d'un célibataire (max 1 par an)
            'prize' => 1,            // chaque prix / mention
            'n_prizes' => 2,
            'host' => 3,
            'travel' => [0, 0, 1, 1], // <300 km, 300-1500, 1500-6000, >6000
            'late' => -1,
            'absent' => -1,
            'flight' => -1,          // absence + rupture
            'comeback' => 1,         // rattrapage du dernier de l'an passé
            'floor_min' => 0,        // plancher du total annuel
            'malus' => [
                'simple' => -2,
                'end_shared' => -3,  // fin de vie commune / de PACS
                'divorce' => -5,
                'engaged' => -8,     // rupture entre fiançailles et mariage
                'cheat' => -10,
                'hidden' => -3,      // rupture cachée
            ],
            // Classement global
            'gpts' => [3, 2, 1],     // points globaux pour le 1er, 2e, 3e (0 ensuite)
            'decay' => 0.7,          // usure : global = 0,7 × global précédent + points de l'année
            'cycle_years' => 3,      // cadeau global tous les 3 ans
            // Règles de présence
            'visio_max_streak' => 2, // après 2 années de suite en visio, la 3e = absence du partenaire
            'notice_days' => 15,     // préavis d'absence
            'late_minutes' => 30,
        ];
    }

    /** Libellés français des paramètres modifiables par amendement. */
    public static function labels(): array
    {
        return [
            'serie.0' => 'Série : 1re année', 'serie.1' => 'Série : 2e année', 'serie.2' => 'Série : 3e année',
            'serie.3' => 'Série : 4e année', 'serie.4' => 'Série : 5e année et suivantes',
            'steps.move_in' => 'Étape : emménagement', 'steps.buy' => 'Étape : achat commun',
            'steps.engaged' => 'Étape : fiançailles', 'steps.pacs' => 'Étape : PACS',
            'steps.married' => 'Étape : mariage', 'steps.child' => 'Étape : enfant',
            'bluff_win' => 'Bluff réussi', 'bluff_caught' => 'Bluff démasqué',
            'accuse_sole' => 'Seul accusateur correct', 'accuse_wrong' => 'Accusation à tort',
            'repeat_bonus' => 'Même faux partenaire 2 ans de suite', 'attempt' => 'Tentative (célibataire)',
            'prize' => 'Prix / mention', 'n_prizes' => 'Nombre de prix par an', 'host' => 'Bonus d\'hôte',
            'travel.0' => 'Déplacement : moins de 300 km', 'travel.1' => 'Déplacement : 300 à 1 500 km',
            'travel.2' => 'Déplacement : 1 500 à 6 000 km', 'travel.3' => 'Déplacement : plus de 6 000 km',
            'late' => 'Retard de plus de 30 min', 'absent' => 'Absence sans préavis ou sans motif',
            'flight' => 'Fuite (absence + rupture)', 'comeback' => 'Rattrapage du dernier',
            'floor_min' => 'Plancher du total annuel',
            'malus.simple' => 'Malus : rupture simple', 'malus.end_shared' => 'Malus : fin de vie commune / PACS',
            'malus.divorce' => 'Malus : divorce', 'malus.engaged' => 'Malus : rupture entre fiançailles et mariage',
            'malus.cheat' => 'Malus : tromperie', 'malus.hidden' => 'Malus : rupture cachée',
            'gpts.0' => 'Global : points du 1er', 'gpts.1' => 'Global : points du 2e', 'gpts.2' => 'Global : points du 3e',
            'decay' => 'Usure du global (coefficient)', 'cycle_years' => 'Cadeau global : tous les N ans',
            'visio_max_streak' => 'Visio : années de suite autorisées', 'notice_days' => 'Préavis d\'absence (jours)',
        ];
    }

    /** Lit une valeur par chemin pointé (« malus.cheat », « serie.2 »). */
    public static function get(array $params, string $path)
    {
        $cur = $params;
        foreach (explode('.', $path) as $k) {
            if (!is_array($cur) || !array_key_exists($k, $cur)) {
                return null;
            }
            $cur = $cur[$k];
        }
        return $cur;
    }

    /** Retourne une copie de $params avec la valeur modifiée. */
    public static function with(array $params, string $path, $value): array
    {
        $keys = explode('.', $path);
        $ref = &$params;
        foreach ($keys as $k) {
            $ref = &$ref[$k];
        }
        $ref = $value;
        return $params;
    }
}
