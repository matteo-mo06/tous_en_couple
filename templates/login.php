<div class="card" style="max-width:420px;margin:40px auto">
  <h1>Connexion</h1>
  <form method="post" action="<?= e(url('/connexion')) ?>">
    <?= TEC\Csrf::field() ?>
    <label for="login">Identifiant</label>
    <input type="text" id="login" name="login" autocomplete="username" autocapitalize="none" required autofocus value="<?= e($login ?? '') ?>">
    <label for="password">Mot de passe</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>
    <p><button class="btn primary" type="submit">Se connecter</button></p>
  </form>
  <p class="muted small">Les comptes sont créés par l'administrateur.</p>
</div>
