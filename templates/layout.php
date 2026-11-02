<?php
/** @var string $title @var string $content @var ?array $_user @var array $_flash @var string $_tpl */
$nav = [
    '/' => 'Accueil', '/moi' => 'Mon espace', '/joueurs' => 'Joueurs', '/classement' => 'Classement',
    '/historique' => 'Historique', '/soiree' => 'Soirée', '/amendements' => 'Amendements',
];
$path = TEC\Http::path();
// Version = date de modification du fichier : le navigateur recharge dès qu'un fichier change.
$asset = fn(string $f) => url($f) . '?v=' . (@filemtime(dirname(__DIR__) . '/public' . $f) ?: 0);
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($title) ?> — <?= e(TEC\Config::get('app.name')) ?></title>
  <link rel="stylesheet" href="<?= e($asset('/css/app.css')) ?>">
  <script src="<?= e($asset('/js/theme.js')) ?>"></script>
</head>
<body>
<header class="site">
  <div class="wrap">
    <a class="brand" href="<?= e(url('/')) ?>"><span class="ring">💍</span><span><?= e(TEC\Config::get('app.name')) ?></span></a>
    <?php if ($_user): ?>
      <nav class="main">
        <?php foreach ($nav as $href => $label): ?>
          <a href="<?= e(url($href)) ?>" class="<?= ($path === $href || ($href !== '/' && str_starts_with($path, $href))) ? 'on' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($_user['role'] === 'admin'): ?>
          <a href="<?= e(url('/admin')) ?>" class="<?= str_starts_with($path, '/admin') ? 'on' : '' ?>">Admin</a>
        <?php endif; ?>
      </nav>
      <div class="who">
        <a class="name" href="<?= e(url('/parametres')) ?>"><?= e($_user['name']) ?></a>
        <a class="gear<?= $path === '/parametres' ? ' on' : '' ?>" href="<?= e(url('/parametres')) ?>" title="Paramètres" aria-label="Paramètres">⚙️</a>
        <form method="post" action="<?= e(url('/deconnexion')) ?>"><?= TEC\Csrf::field() ?><button class="btn small" type="submit">Déconnexion</button></form>
      </div>
    <?php endif; ?>
  </div>
</header>
<main>
  <div class="wrap">
    <?= $content ?>
  </div>
</main>
<?php if ($_flash): ?>
<div class="toasts" role="status" aria-live="polite">
  <?php foreach ($_flash as $f): ?>
    <div class="toast <?= e($f['type']) ?>"><span><?= e($f['msg']) ?></span><button type="button" aria-label="Fermer">×</button></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<script src="<?= e($asset('/js/app.js')) ?>" defer></script>
</body>
</html>
