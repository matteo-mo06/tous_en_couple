<?php
declare(strict_types=1);

namespace TEC\Repo;

use TEC\Auth;
use TEC\Db;

final class Players
{
    public static function all(bool $activeOnly = false): array
    {
        return Db::all('SELECT * FROM players ' . ($activeOnly ? 'WHERE active = 1 ' : '') . 'ORDER BY name');
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM players WHERE id = ?', [$id]);
    }

    /** id => joueur, pour afficher des noms sans requête par ligne */
    public static function map(): array
    {
        $out = [];
        foreach (Db::all('SELECT * FROM players') as $p) {
            $out[(int)$p['id']] = $p;
        }
        return $out;
    }

    public static function slug(string $name): string
    {
        $s = mb_strtolower(trim($name));
        $s = strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c']);
        $s = preg_replace('/[^a-z0-9._-]+/', '', str_replace(' ', '.', $s)) ?? '';
        return $s !== '' ? $s : 'joueur';
    }

    /**
     * @return array{0:?int,1:?string} [id, erreur]
     */
    public static function create(string $name, string $login, string $password, string $role = 'player',
                                  ?string $homePlace = null, bool $mustChange = true, ?float $startGlobal = null,
                                  ?int $joinedYear = null): array
    {
        $name = trim($name);
        $login = mb_strtolower(trim($login));
        if ($name === '') {
            return [null, 'Le nom est obligatoire.'];
        }
        if (!preg_match('/^[a-z0-9._-]{2,40}$/', $login)) {
            return [null, 'Identifiant : 2 à 40 caractères parmi lettres minuscules, chiffres, point, tiret.'];
        }
        if (mb_strlen($password) < 8) {
            return [null, 'Le mot de passe doit faire au moins 8 caractères.'];
        }
        if (!in_array($role, ['player', 'admin'], true)) {
            return [null, 'Rôle inconnu.'];
        }
        if (Db::one('SELECT id FROM players WHERE login = ?', [$login])) {
            return [null, 'Cet identifiant existe déjà.'];
        }
        $id = Db::insert('players', [
            'login' => $login, 'name' => $name, 'password_hash' => Auth::hash($password), 'role' => $role,
            'active' => 1, 'must_change' => $mustChange ? 1 : 0, 'home_place' => $homePlace,
            'joined_year' => $joinedYear, 'start_global' => $startGlobal, 'created_at' => now(),
        ]);
        return [$id, null];
    }

    public static function setPassword(int $id, string $password, bool $mustChange): ?string
    {
        if (mb_strlen($password) < 8) {
            return 'Le mot de passe doit faire au moins 8 caractères.';
        }
        Db::update('players', ['password_hash' => Auth::hash($password), 'must_change' => $mustChange ? 1 : 0], 'id = ?', [$id]);
        return null;
    }

    public static function adminCount(): int
    {
        return (int)Db::val("SELECT COUNT(*) FROM players WHERE role = 'admin' AND active = 1");
    }
}
