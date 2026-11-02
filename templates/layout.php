<?php
/** @var string $title @var string $content @var ?array $_user @var array $_flash @var string $_tpl */
$nav = [
    '/' => ['Accueil', 'home'], '/moi' => ['Mon espace', 'user'], '/joueurs' => ['Joueurs', 'users'],
    '/classement' => ['Classement', 'trophy'], '/historique' => ['Historique', 'clock'],
    '/soiree' => ['Soirée', 'sparkles'], '/amendements' => ['Amendements', 'scroll'],
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
  <link rel="icon" type="image/svg+xml" href="<?= e(url('/favicon.svg')) ?>">
  <link rel="stylesheet" href="<?= e($asset('/css/app.css')) ?>">
  <script src="<?= e($asset('/js/theme.js')) ?>"></script>
</head>
<body>
<?php require __DIR__ . '/partials/icons.php'; ?>
<header class="site">
  <div class="wrap">
    <a class="brand" href="<?= e(url('/')) ?>"><span class="logo"><?= icon('ring') ?></span><span class="brand-name"><?= e(TEC\Config::get('app.name')) ?></span></a>
    <?php if ($_user): ?>
      <input type="checkbox" id="menu-toggle" class="menu-toggle" aria-label="Ouvrir le menu">
      <label for="menu-toggle" class="menu-btn" aria-hidden="true"><?= icon('menu', 'open') ?><?= icon('x', 'close') ?></label>
      <nav class="main" aria-label="Navigation principale">
        <?php foreach ($nav as $href => [$label, $ico]): ?>
          <a href="<?= e(url($href)) ?>" class="<?= ($path === $href || ($href !== '/' && str_starts_with($path, $href))) ? 'on' : '' ?>"><?= icon($ico) ?><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
        <?php if ($_user['role'] === 'admin'): ?>
          <a href="<?= e(url('/admin')) ?>" class="admin <?= str_starts_with($path, '/admin') ? 'on' : '' ?>"><?= icon('shield') ?><span>Admin</span></a>
        <?php endif; ?>
        <div class="who">
          <a class="me <?= $path === '/parametres' ? 'on' : '' ?>" href="<?= e(url('/parametres')) ?>"><?= icon('sliders') ?><span><?= e($_user['name']) ?></span></a>
          <form method="post" action="<?= e(url('/deconnexion')) ?>"><?= TEC\Csrf::field() ?><button class="out" type="submit"><?= icon('logout') ?><span>Déconnexion</span></button></form>
        </div>
      </nav>
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
    <div class="toast <?= e($f['type']) ?>"><?= icon(['ok' => 'check', 'error' => 'alert'][$f['type']] ?? 'info', 'lead') ?><span><?= e($f['msg']) ?></span><button type="button" aria-label="Fermer"><?= icon('x') ?></button></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<script src="<?= e($asset('/js/app.js')) ?>" defer></script>
</body>
</html>
