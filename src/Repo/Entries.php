<?php
declare(strict_types=1);

namespace TEC\Repo;

use TEC\Db;

/** Déclarations des joueurs (relation, étapes, ruptures, bluff...) et leur validation. */
final class Entries
{
    public const TYPES = [
        'relation' => 'Statut de relation',
        'step' => 'Étape de vie',
        'breakup' => 'Rupture',
        'cheating' => 'Tromperie',
        'hidden_breakup' => 'Rupture cachée',
        'bluff' => 'Bluff',
        'attempt' => 'Tentative',
        'absence' => 'Absence prévue',
    ];
    public const STEPS = [
        'move_in' => 'Emménagement ensemble', 'buy' => 'Achat commun', 'engaged' => 'Fiançailles',
        'pacs' => 'PACS', 'married' => 'Mariage', 'child' => 'Enfant (naissance ou adoption)',
    ];
    public const BREAKUPS = [
        'simple' => 'Rupture simple', 'end_shared' => 'Fin de vie commune ou de PACS',
        'divorce' => 'Divorce', 'engaged' => 'Rupture entre les fiançailles et le mariage',
    ];
    public const ATTEMPTS = [
        'app' => 'Compte actif sur une application de rencontre', 'date' => 'Rendez-vous',
        'activity' => 'Activité pour rencontrer du monde', 'setup' => 'Rencontre arrangée par un ami',
    ];
    /** Types que seul l'administrateur peut saisir pour un autre joueur (signalements). */
    public const PRIVATE_TYPES = ['bluff'];

    public static function find(int $id): ?array
    {
        $e = Db::one('SELECT * FROM entries WHERE id = ?', [$id]);
        if ($e) {
            $e['data'] = json_decode_array($e['data']);
        }
        return $e;
    }

    /** @return array [données nettoyées|null, erreur|null] */
    public static function validate(string $type, array $in): array
    {
        $s = fn(string $k) => trim((string)($in[$k] ?? ''));
        switch ($type) {
            case 'relation':
                $status = $s('status');
                if (!in_array($status, ['single', 'couple'], true)) {
                    return [null, 'Choisis « célibataire » ou « en couple ».'];
                }
                if ($status === 'single') {
                    return [['status' => 'single'], null];
                }
                if ($s('partner') === '') {
                    return [null, 'Indique le prénom du partenaire.'];
                }
                return [['status' => 'couple', 'partner' => mb_substr($s('partner'), 0, 80), 'far' => !empty($in['far'])], null];
            case 'step':
                if (!isset(self::STEPS[$s('step')])) {
                    return [null, 'Choisis une étape de vie.'];
                }
                return [['step' => $s('step'), 'date' => $s('date')], null];
            case 'breakup':
                if (!isset(self::BREAKUPS[$s('kind')])) {
                    return [null, 'Choisis le type de rupture.'];
                }
                return [[
                    'kind' => $s('kind'), 'date' => $s('date'),
                    'couple_continues' => $s('kind') === 'end_shared' && !empty($in['couple_continues']),
                    'good_terms_requested' => !empty($in['good_terms_requested']),
                ], null];
            case 'cheating':
            case 'hidden_breakup':
                return [['note' => mb_substr($s('note'), 0, 500)], null];
            case 'bluff':
                if ($s('fake_partner') === '') {
                    return [null, 'Indique le prénom du faux partenaire.'];
                }
                return [['fake_partner' => mb_substr($s('fake_partner'), 0, 80)], null];
            case 'attempt':
                $kind = $s('attempt_kind') !== '' ? $s('attempt_kind') : $s('kind');
                if (!isset(self::ATTEMPTS[$kind])) {
                    return [null, 'Choisis le type de tentative.'];
                }
                return [['kind' => $kind, 'date' => $s('date')], null];
            case 'absence':
                if ($s('reason') === '') {
                    return [null, 'Indique le motif de l\'absence.'];
                }
                return [['reason' => mb_substr($s('reason'), 0, 500)], null];
        }
        return [null, 'Type de déclaration inconnu.'];
    }

    /**
     * Crée une entrée. Un joueur crée en « pending » ; l'administrateur peut valider tout de suite.
     * @return array [id|null, erreur|null]
     */
    public static function create(int $yearId, int $playerId, string $type, array $input, int $createdBy, bool $approve = false): array
    {
        [$data, $err] = self::validate($type, $input);
        if ($err !== null) {
            return [null, $err];
        }
        // Une seule déclaration active de ces types par joueur et par année
        if (in_array($type, ['bluff', 'attempt', 'absence'], true)) {
            $n = (int)Db::val(
                "SELECT COUNT(*) FROM entries WHERE year_id = ? AND player_id = ? AND type = ? AND status <> 'rejected'",
                [$yearId, $playerId, $type]
            );
            if ($n > 0) {
                return [null, 'Il y a déjà une déclaration de ce type pour cette année (' . self::TYPES[$type] . ').'];
            }
        }
        $id = Db::insert('entries', [
            'year_id' => $yearId, 'player_id' => $playerId, 'type' => $type,
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE), 'status' => $approve ? 'approved' : 'pending',
            'created_by' => $createdBy, 'created_at' => now(),
            'decided_by' => $approve ? $createdBy : null, 'decided_at' => $approve ? now() : null,
        ]);
        self::log($id, $createdBy, $createdBy === $playerId ? 'created' : 'created_for', null);
        if ($approve) {
            self::log($id, $createdBy, 'approved', 'Saisie directe par l\'administrateur');
        }
        return [$id, null];
    }

    public static function decide(int $id, string $status, ?string $note, int $actorId): ?string
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return 'Décision inconnue.';
        }
        $e = self::find($id);
        if (!$e) {
            return 'Déclaration introuvable.';
        }
        if ($status === 'rejected' && trim((string)$note) === '') {
            return 'Indique un motif de refus (le joueur le verra).';
        }
        Db::update('entries', ['status' => $status, 'decision_note' => $note ?: null, 'decided_by' => $actorId, 'decided_at' => now()], 'id = ?', [$id]);
        self::log($id, $actorId, $status, $note);
        return null;
    }

    /** Un joueur peut retirer une déclaration tant qu'elle n'est pas validée. */
    public static function withdraw(int $id, int $playerId): bool
    {
        $e = self::find($id);
        if (!$e || (int)$e['player_id'] !== $playerId || $e['status'] !== 'pending') {
            return false;
        }
        Db::run('DELETE FROM entries WHERE id = ?', [$id]);
        Db::run('DELETE FROM entry_log WHERE entry_id = ?', [$id]);
        return true;
    }

    public static function log(int $entryId, int $actorId, string $action, ?string $note): void
    {
        Db::insert('entry_log', ['entry_id' => $entryId, 'actor_id' => $actorId, 'action' => $action, 'note' => $note, 'created_at' => now()]);
    }

    public static function logFor(int $entryId): array
    {
        return Db::all('SELECT l.*, p.name AS actor FROM entry_log l JOIN players p ON p.id = l.actor_id WHERE l.entry_id = ? ORDER BY l.id', [$entryId]);
    }

    public static function forPlayer(int $yearId, int $playerId): array
    {
        return self::decode(Db::all('SELECT * FROM entries WHERE year_id = ? AND player_id = ? ORDER BY id DESC', [$yearId, $playerId]));
    }

    public static function pending(): array
    {
        return self::decode(Db::all(
            "SELECT e.*, p.name AS player_name FROM entries e JOIN players p ON p.id = e.player_id
             WHERE e.status = 'pending' ORDER BY e.id"
        ));
    }

    public static function forYear(int $yearId, ?string $status = null): array
    {
        $sql = 'SELECT e.*, p.name AS player_name FROM entries e JOIN players p ON p.id = e.player_id WHERE e.year_id = ?';
        $args = [$yearId];
        if ($status) {
            $sql .= ' AND e.status = ?';
            $args[] = $status;
        }
        return self::decode(Db::all($sql . ' ORDER BY e.id', $args));
    }

    private static function decode(array $rows): array
    {
        foreach ($rows as &$r) {
            $r['data'] = json_decode_array($r['data']);
        }
        return $rows;
    }

    /** Phrase lisible décrivant une entrée. */
    public static function describe(array $e): string
    {
        $d = $e['data'];
        switch ($e['type']) {
            case 'relation':
                return ($d['status'] ?? '') === 'couple'
                    ? 'En couple avec ' . ($d['partner'] ?? '?') . (!empty($d['far']) ? ' (partenaire lointain)' : '')
                    : 'Célibataire';
            case 'step':
                return self::STEPS[$d['step'] ?? ''] ?? '?';
            case 'breakup':
                return (self::BREAKUPS[$d['kind'] ?? ''] ?? '?')
                    . (!empty($d['couple_continues']) ? ' — le couple continue' : '')
                    . (!empty($d['good_terms_requested']) ? ' — « bons termes » demandés' : '');
            case 'cheating':
            case 'hidden_breakup':
                return self::TYPES[$e['type']] . (!empty($d['note']) ? ' : ' . $d['note'] : '');
            case 'bluff':
                return 'Bluff avec « ' . ($d['fake_partner'] ?? '?') . ' »';
            case 'attempt':
                return self::ATTEMPTS[$d['kind'] ?? ''] ?? '?';
            case 'absence':
                return 'Absence : ' . ($d['reason'] ?? '');
        }
        return '?';
    }
}
