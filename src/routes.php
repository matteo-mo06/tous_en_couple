<?php
declare(strict_types=1);

use TEC\Controller\AdminController as Admin;
use TEC\Controller\AmendmentController as Amend;
use TEC\Controller\AuthController as AuthC;
use TEC\Controller\PlayerController as Player;
use TEC\Controller\SoireeController as Soiree;

/** @var TEC\Router $router */

// Connexion
$router->get('/connexion', [AuthC::class, 'loginForm']);
$router->post('/connexion', [AuthC::class, 'login']);
$router->post('/deconnexion', [AuthC::class, 'logout']);
$router->get('/mot-de-passe', [AuthC::class, 'passwordForm']);
$router->post('/mot-de-passe', [AuthC::class, 'passwordSave']);

// Joueur
$router->get('/', [Player::class, 'home']);
$router->get('/moi', [Player::class, 'me']);
$router->post('/moi/entree', [Player::class, 'addEntry']);
$router->post('/moi/entree/{id}/retirer', [Player::class, 'withdrawEntry']);
$router->get('/parametres', [Player::class, 'settings']);
$router->post('/parametres', [Player::class, 'saveSettings']);
$router->get('/joueurs', [Player::class, 'players']);
$router->get('/classement', [Player::class, 'ranking']);
$router->get('/historique', [Player::class, 'history']);
$router->get('/historique/{year}', [Player::class, 'historyYear']);

// Soirée
$router->get('/soiree', [Soiree::class, 'live']);
$router->post('/soiree/accuser', [Soiree::class, 'accuse']);
$router->post('/soiree/vote', [Soiree::class, 'vote']);

// Amendements
$router->get('/amendements', [Amend::class, 'index']);
$router->post('/amendements', [Amend::class, 'propose']);
$router->post('/amendements/{id}/vote', [Amend::class, 'vote']);

// Administration
$router->get('/admin', [Admin::class, 'dashboard']);
$router->get('/admin/joueurs', [Admin::class, 'players']);
$router->post('/admin/joueurs', [Admin::class, 'createPlayer']);
$router->post('/admin/joueurs/{id}/action', [Admin::class, 'playerAction']);
$router->get('/admin/annee', [Admin::class, 'year']);
$router->post('/admin/annee', [Admin::class, 'saveYear']);
$router->post('/admin/annee/ouvrir-soiree', [Admin::class, 'openSoiree']);
$router->get('/admin/soiree', [Admin::class, 'soiree']);
$router->post('/admin/soiree/presence', [Admin::class, 'saveAttendance']);
$router->post('/admin/soiree/prix', [Admin::class, 'addPrize']);
$router->post('/admin/soiree/prix/{id}/gagnant', [Admin::class, 'setWinner']);
$router->get('/admin/cloture', [Admin::class, 'closePreview']);
$router->post('/admin/cloture', [Admin::class, 'closeConfirm']);
$router->get('/admin/bareme', [Admin::class, 'rules']);
