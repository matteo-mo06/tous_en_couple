<div class="card" style="max-width:480px">
  <h1>Mot de passe</h1>
  <?php if ($_user['must_change']): ?><p class="muted">Ton mot de passe est provisoire : choisis le tien.</p><?php endif; ?>
  <form method="post" action="<?= e(url('/mot-de-passe')) ?>">
    <?= TEC\Csrf::field() ?>
    <label for="current">Mot de passe actuel</label>
    <input type="password" id="current" name="current" autocomplete="current-password" required>
    <label for="new">Nouveau mot de passe (8 caractères minimum)</label>
    <input type="password" id="new" name="new" autocomplete="new-password" required minlength="8">
    <label for="new2">Nouveau mot de passe, encore une fois</label>
    <input type="password" id="new2" name="new2" autocomplete="new-password" required minlength="8">
    <p><button class="btn primary" type="submit">Enregistrer</button></p>
  </form>
</div>
