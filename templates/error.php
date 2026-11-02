<div class="card">
  <h1><?= e($title) ?></h1>
  <p><?= e($msg ?: 'Une erreur est survenue.') ?></p>
  <p><a class="btn" href="<?= e(url('/')) ?>">Retour à l'accueil</a></p>
</div>
