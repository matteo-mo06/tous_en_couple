<?php
/** @var string $title @var string $content @var ?array $_user @var array $_flash @var string $_tpl */
$nav = [
    '/' => 'Accueil', '/moi' => 'Mon espace', '/joueurs' => 'Joueurs', '/classement' => 'Classement',
    '/historique' => 'Historique', '/soiree' => 'Soirée', '/amendements' => 'Amendements',
];
$path = TEC\Http::path();
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($title) ?> — <?= e(TEC\Config::get('app.name')) ?></title>
  <link rel="stylesheet" href="<?= e(url('/css/app.css')) ?>">
</head>
<body>
<header class="site">
  <div class="wrap">
    <a class="brand" href="<?= e(url('/')) ?>">💍 <?= e(TEC\Config::get('app.name')) ?></a>
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
        <span><?= e($_user['name']) ?></span>
        <form method="post" action="<?= e(url('/deconnexion')) ?>"><?= TEC\Csrf::field() ?><button class="btn small" type="submit">Déconnexion</button></form>
      </div>
    <?php endif; ?>
  </div>
</header>
<main>
  <div class="wrap">
    <?php foreach ($_flash as $f): ?>
      <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
  </div>
</main>
<script src="<?= e(url('/js/app.js')) ?>" defer></script>
</body>
</html>
