<div class="card narrow">
  <h1>Mot de passe</h1>
  <?php if ($_user['must_change']): ?><p class="muted">Ton mot de passe est provisoire : choisis le tien.</p><?php endif; ?>
  <form method="post" action="<?= e(url('/mot-de-passe')) ?>">
    <?= TEC\Csrf::field() ?>
    <?php if ($_user['must_change']): ?>
      <label for="name">Ton nom affiché</label>
      <input type="text" id="name" name="name" maxlength="60" value="<?= e($_user['pending_name'] ?: $_user['name']) ?>" autocomplete="name">
      <p class="small muted">Si tu le changes, l'administrateur devra valider avant que le nouveau nom apparaisse.</p>
    <?php endif; ?>
    <label for="current">Mot de passe actuel</label>
    <input type="password" id="current" name="current" autocomplete="current-password" required>
    <label for="new">Nouveau mot de passe (8 caractères minimum)</label>
    <input type="password" id="new" name="new" autocomplete="new-password" required minlength="8">
    <label for="new2">Nouveau mot de passe, encore une fois</label>
    <input type="password" id="new2" name="new2" autocomplete="new-password" required minlength="8">
    <p><button class="btn primary" type="submit">Enregistrer</button></p>
  </form>
</div>
