<h1>Joueurs</h1>
<?php require __DIR__ . '/nav.php'; ?>
<div class="card">
  <h2>Comptes</h2>
  <div class="scroll"><table>
    <tr><th>Nom</th><th>Identifiant</th><th>Rôle</th><th>État</th><th class="num">Global</th><th></th></tr>
    <?php foreach ($list as $p): ?>
      <tr>
        <td><?= e($p['name']) ?></td><td><?= e($p['login']) ?></td>
        <td><?= $p['role'] === 'admin' ? 'Administrateur' : 'Joueur' ?></td>
        <td><?= $p['active'] ? 'Actif' : '<span class="muted">Désactivé</span>' ?></td>
        <td class="num"><?= isset($globals[(int)$p['id']]) ? num($globals[(int)$p['id']]) : '—' ?></td>
        <td>
          <form method="post" action="<?= e(url('/admin/joueurs/' . (int)$p['id'] . '/action')) ?>" class="row">
            <?= TEC\Csrf::field() ?>
            <button class="btn small" name="action" value="toggle_role"><?= $p['role'] === 'admin' ? 'Retirer admin' : 'Rendre admin' ?></button>
            <button class="btn small" name="action" value="toggle_active"><?= $p['active'] ? 'Désactiver' : 'Réactiver' ?></button>
          </form>
          <details><summary class="small">Mot de passe provisoire</summary>
            <form method="post" action="<?= e(url('/admin/joueurs/' . (int)$p['id'] . '/action')) ?>">
              <?= TEC\Csrf::field() ?><input type="hidden" name="action" value="reset_password">
              <input type="text" name="password" minlength="8" placeholder="8 caractères minimum" autocomplete="off" required>
              <p><button class="btn small" type="submit">Définir</button></p>
            </form></details>
        </td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="card">
  <h2>Ajouter un joueur</h2>
  <?php if ($hasHistory): ?>
    <p class="small muted">Un nouveau joueur démarre avec <strong><?= num($start[0]) ?></strong> point(s) au global (formule : points du dernier − H, avec H = <?= (int)$start[1] ?>). Pense au vote à l'unanimité des autres joueurs avant de créer le compte.</p>
  <?php else: ?>
    <p class="small muted">Avant la première clôture, tout le monde démarre à 0.</p>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/admin/joueurs')) ?>">
    <?= TEC\Csrf::field() ?>
    <label for="name">Nom affiché</label><input type="text" id="name" name="name" required maxlength="190">
    <label for="login">Identifiant (minuscules, sans espace — laisse vide pour le déduire du nom)</label><input type="text" id="login" name="login" maxlength="40" autocapitalize="none">
    <label for="password">Mot de passe provisoire (8 caractères minimum)</label><input type="text" id="password" name="password" minlength="8" required autocomplete="off">
    <label for="role">Rôle</label>
    <select id="role" name="role"><option value="player">Joueur</option><option value="admin">Administrateur</option></select>
    <p><button class="btn primary" type="submit">Créer le compte</button></p>
  </form>
</div>
