<h1>Paramètres</h1>
<div class="grid">
  <div class="card">
    <h2>Mes informations</h2>
    <form method="post" action="<?= e(url('/parametres')) ?>">
      <?= TEC\Csrf::field() ?>
      <label for="name">Nom affiché</label>
      <input type="text" id="name" name="name" maxlength="60" value="<?= e($u['pending_name'] ?: $u['name']) ?>" autocomplete="name">
      <?php if (!empty($u['pending_name'])): ?>
        <p class="small muted">Demande en attente de validation par l'administrateur : « <?= e($u['pending_name']) ?> ». Ton nom actuel reste « <?= e($u['name']) ?> » en attendant. Remets-le ci-dessus pour annuler la demande.</p>
      <?php else: ?>
        <p class="small muted">Un changement de nom doit être validé par l'administrateur.</p>
      <?php endif; ?>
      <label for="login">Identifiant de connexion</label>
      <input type="text" id="login" name="login" maxlength="40" value="<?= e($u['login']) ?>" autocapitalize="none" autocomplete="username">
      <label for="home_place">Mon lieu de vie (ville ou région)</label>
      <input type="text" id="home_place" name="home_place" maxlength="190" value="<?= e($u['home_place']) ?>">
      <p class="small muted">Sert à estimer la distance jusqu'au lieu de la réunion (l'administrateur ne le consulte que pour cela, le jour de la soirée).</p>
      <p><button class="btn primary" type="submit">Enregistrer</button></p>
    </form>
  </div>

  <div>
    <div class="card">
      <h2>Mot de passe</h2>
      <form method="post" action="<?= e(url('/mot-de-passe')) ?>">
        <?= TEC\Csrf::field() ?>
        <label for="current">Mot de passe actuel</label>
        <input type="password" id="current" name="current" autocomplete="current-password" required>
        <label for="new">Nouveau mot de passe (8 caractères minimum)</label>
        <input type="password" id="new" name="new" autocomplete="new-password" required minlength="8">
        <label for="new2">Nouveau mot de passe, encore une fois</label>
        <input type="password" id="new2" name="new2" autocomplete="new-password" required minlength="8">
        <p><button class="btn" type="submit">Changer le mot de passe</button></p>
      </form>
    </div>
    <div class="card">
      <h2>Apparence</h2>
      <label for="theme">Thème</label>
      <select id="theme"><option value="auto">Automatique (selon l'appareil)</option><option value="light">Clair</option><option value="dark">Sombre</option></select>
      <p class="small muted">Mémorisé dans ce navigateur uniquement.</p>
    </div>
  </div>
</div>
