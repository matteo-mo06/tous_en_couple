<?php
declare(strict_types=1);

namespace TEC\Repo;

use TEC\Db;
use TEC\Scoring\Defaults;

/** Propositions de modification des règles, et leur vote. */
final class Amendments
{
    /** Fenêtre de dépôt/vote : de 4 mois avant la réunion à 1 semaine avant. Sans date de réunion : toujours ouverte. */
    public static function window(array $year): array
    {
        if (empty($year['meeting_date'])) {
            return ['open' => $year['status'] === 'open', 'from' => null, 'to' => null, 'known' => false];
        }
        $t = strtotime($year['meeting_date'] . ' 00:00:00');
        $from = strtotime('-4 months', $t);
        $to = strtotime('-7 days', $t);
        $now = time();
        return [
            'open' => $year['status'] === 'open' && $now >= $from && $now < $to,
            'from' => date('Y-m-d', $from), 'to' => date('Y-m-d', $to), 'known' => true,
        ];
    }

    public static function forYear(int $yearId): array
    {
        return Db::all(
            'SELECT a.*, p.name AS proposer FROM amendments a JOIN players p ON p.id = a.proposer_id
             WHERE a.year_id = ? ORDER BY a.id',
            [$yearId]
        );
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM amendments WHERE id = ?', [$id]);
    }

    /** @return array{0:?int,1:?string} */
    public static function propose(array $year, int $proposerId, string $title, string $body, string $paramKey, string $paramRaw, array $currentParams): array
    {
        $w = self::window($year);
        if (!$w['open']) {
            return [null, 'Le dépôt d\'amendements est fermé (ouvert de 4 mois à 1 semaine avant la réunion).'];
        }
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || $body === '') {
            return [null, 'Le titre et l\'explication sont obligatoires.'];
        }
        $value = null;
        if ($paramKey !== '') {
            [$v, $err] = Rules::parseValue($currentParams, $paramKey, $paramRaw);
            if ($err) {
                return [null, $err];
            }
            if ($v == Defaults::get($currentParams, $paramKey)) {
                return [null, 'La valeur proposée est déjà celle du barème actuel.'];
            }
            $value = json_encode($v);
        }
        $id = Db::insert('amendments', [
            'year_id' => $year['id'], 'proposer_id' => $proposerId, 'title' => mb_substr($title, 0, 190), 'body' => $body,
            'param_key' => $paramKey !== '' ? $paramKey : null, 'param_value' => $value,
            'status' => 'open', 'created_at' => now(),
        ]);
        return [$id, null];
    }

    /** Vote sur un amendement (pour / contre), modifiable tant que la fenêtre est ouverte. */
    public static function vote(array $year, array $amendment, int $voterId, string $choice): ?string
    {
        if (!self::window($year)['open'] || $amendment['status'] !== 'open') {
            return 'Le vote est fermé.';
        }
        if (!in_array($choice, ['yes', 'no'], true)) {
            return 'Choix inconnu.';
        }
        Soiree::vote('amendment', (int)$amendment['id'], $voterId, $choice);
        return null;
    }
}
