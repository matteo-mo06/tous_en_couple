<?php
declare(strict_types=1);

namespace TEC;

/** Libellés français affichés dans les pages. */
final class Labels
{
    public const CATEGORIES = [
        'serie' => 'Série', 'steps' => 'Étapes de vie', 'travel' => 'Déplacement', 'attempt' => 'Tentative',
        'comeback' => 'Rattrapage', 'host' => 'Hôte', 'prize' => 'Prix et mentions', 'bluff' => 'Bluff',
        'repeat' => 'Même faux partenaire', 'solo' => 'Seul accusateur correct', 'accuse' => 'Accusations à tort',
        'late' => 'Retard', 'absent' => 'Absence', 'flight' => 'Fuite', 'rupture' => 'Rupture',
        'endcohab' => 'Fin de vie commune', 'cheat' => 'Tromperie', 'hidden' => 'Rupture cachée', 'floor' => 'Plancher à 0',
    ];

    public const YEAR_STATUS = [
        'open' => 'Déclarations ouvertes', 'soiree' => 'Soirée en cours', 'closed' => 'Clôturée',
    ];

    public const ENTRY_STATUS = ['pending' => 'En attente', 'approved' => 'Validée', 'rejected' => 'Refusée'];

    public const DISTANCE = [
        0 => 'moins de 300 km', 1 => '300 à 1 500 km', 2 => '1 500 à 6 000 km', 3 => 'plus de 6 000 km',
    ];

    public static function cat(string $k): string
    {
        return self::CATEGORIES[$k] ?? $k;
    }
}
