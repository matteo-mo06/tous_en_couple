<?php
declare(strict_types=1);

namespace TEC\Controller;

use TEC\Auth;
use TEC\Csrf;
use TEC\Http;
use TEC\Repo\Players;

final class AuthController
{
    public static function loginForm(): void
    {
        if (Auth::user()) {
            Http::redirect('/');
        }
        Http::render('login', ['title' => 'Connexion']);
    }

    public static function login(): void
    {
        Csrf::check();
        $login = Http::post('login');
        $err = Auth::attempt($login, (string)($_POST['password'] ?? ''));
        if ($err) {
            Http::flash('error', $err);
            Http::render('login', ['title' => 'Connexion', 'login' => $login]);
            return;
        }
        Http::redirect('/');
    }

    public static function logout(): void
    {
        Csrf::check();
        Auth::logout();
        Auth::startSession();
        Http::redirect('/connexion');
    }

    public static function passwordForm(): void
    {
        Auth::requireLogin();
        Http::render('password', ['title' => 'Mot de passe']);
    }

    public static function passwordSave(): void
    {
        $u = Auth::requireLogin();
        Csrf::check();
        if (!password_verify((string)($_POST['current'] ?? ''), $u['password_hash'])) {
            Http::flash('error', 'Le mot de passe actuel est incorrect.');
            Http::redirect('/mot-de-passe');
        }
        $new = (string)($_POST['new'] ?? '');
        if ($new !== (string)($_POST['new2'] ?? '')) {
            Http::flash('error', 'Les deux nouveaux mots de passe sont différents.');
            Http::redirect('/mot-de-passe');
        }
        if ($new === (string)($_POST['current'] ?? '')) {
            Http::flash('error', 'Choisis un mot de passe différent de l\'ancien.');
            Http::redirect('/mot-de-passe');
        }
        $err = Players::setPassword((int)$u['id'], $new, false);
        if ($err) {
            Http::flash('error', $err);
            Http::redirect('/mot-de-passe');
        }
        Http::flash('ok', 'Mot de passe changé.');
        Http::redirect('/');
    }
}
