<h1>Mon espace</h1>
<div class="grid">
  <div class="card">
    <h2>Mon statut actuel</h2>
    <p><?php $s = $status; require __DIR__ . '/partials/status.php'; ?></p>
    <p class="muted small">C'est le dernier statut validé par l'administrateur. Les autres joueurs voient ce statut, pas le détail de tes déclarations.</p>
    <form method="post" action="<?= e(url('/moi/profil')) ?>">
      <?= TEC\Csrf::field() ?>
      <label for="home_place">Mon lieu de vie (ville ou région)</label>
      <input type="text" id="home_place" name="home_place" maxlength="190" value="<?= e($_user['home_place']) ?>">
      <p class="muted small">Sert à l'administrateur pour estimer la distance jusqu'au lieu de la réunion.</p>
      <p><button class="btn" type="submit">Enregistrer</button></p>
    </form>
  </div>

  <div class="card">
    <h2>Déclarer quelque chose<?= $year ? ' (' . (int)$year['year'] . ')' : '' ?></h2>
    <?php if (!$year): ?>
      <p class="muted">Aucune année ouverte.</p>
    <?php elseif (!$canAdd): ?>
      <p class="muted">Les déclarations sont fermées : la soirée a commencé.</p>
    <?php else: ?>
      <p class="muted small">Rien n'est pris en compte avant la validation de l'administrateur. Ce que tu ne déclares pas reste comme l'an passé.</p>

      <details><summary>Mon statut de relation</summary>
        <form method="post" action="<?= e(url('/moi/entree')) ?>"><?= TEC\Csrf::field() ?><input type="hidden" name="type" value="relation">
          <label for="r_status">Je suis</label>
          <select id="r_status" name="status"><option value="single">Célibataire</option><option value="couple">En couple</option></select>
          <label for="r_partner">Prénom du partenaire (si en couple)</label>
          <input type="text" id="r_partner" name="partner" maxlength="80">
          <label class="check"><input type="checkbox" name="far" value="1"> Partenaire lointain (visio possible)</label>
          <p><button class="btn primary" type="submit">Envoyer</button></p>
        </form></details>

      <details><summary>Une étape de vie</summary>
        <form method="post" action="<?= e(url('/moi/entree')) ?>"><?= TEC\Csrf::field() ?><input type="hidden" name="type" value="step">
          <label for="s_step">Étape</label>
          <select id="s_step" name="step"><?php foreach (TEC\Repo\Entries::STEPS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
          <label for="s_date">Date</label><input type="date" id="s_date" name="date">
          <p class="muted small">Une seule fois par relation (sauf les enfants).</p>
          <p><button class="btn primary" type="submit">Envoyer</button></p>
        </form></details>

      <details><summary>Une rupture</summary>
        <form method="post" action="<?= e(url('/moi/entree')) ?>"><?= TEC\Csrf::field() ?><input type="hidden" name="type" value="breakup">
          <label for="b_kind">Type</label>
          <select id="b_kind" name="kind"><?php foreach (TEC\Repo\Entries::BREAKUPS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
          <label for="b_date">Date</label><input type="date" id="b_date" name="date">
          <label class="check"><input type="checkbox" name="couple_continues" value="1"> Le couple continue (fin de vie commune ou de PACS seulement)</label>
          <label class="check"><input type="checkbox" name="good_terms_requested" value="1"> Demander la clause « bons termes » (les autres jugent à la soirée)</label>
          <p><button class="btn primary" type="submit">Envoyer</button></p>
        </form></details>

      <details><summary>Une tentative (célibataire)</summary>
        <form method="post" action="<?= e(url('/moi/entree')) ?>"><?= TEC\Csrf::field() ?><input type="hidden" name="type" value="attempt">
          <label for="a_kind">Type</label>
          <select id="a_kind" name="kind"><?php foreach (TEC\Repo\Entries::ATTEMPTS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
          <label for="a_date">Date</label><input type="date" id="a_date" name="date">
          <p><button class="btn primary" type="submit">Envoyer</button></p>
        </form></details>

      <details><summary>Un bluff (à déclarer avant la réunion)</summary>
        <form method="post" action="<?= e(url('/moi/entree')) ?>"><?= TEC\Csrf::field() ?><input type="hidden" name="type" value="bluff">
          <label for="bl_name">Prénom du faux partenaire</label><input type="text" id="bl_name" name="fake_partner" maxlength="80">
          <p class="muted small">Visible seulement par toi et l'administrateur jusqu'à la soirée. Réservé aux célibataires.</p>
          <p><button class="btn primary" type="submit">Envoyer</button></p>
        </form></details>

      <details><summary>Une absence prévue</summary>
        <form method="post" action="<?= e(url('/moi/entree')) ?>"><?= TEC\Csrf::field() ?><input type="hidden" name="type" value="absence">
          <label for="ab_reason">Motif</label><textarea id="ab_reason" name="reason" maxlength="500"></textarea>
          <p class="muted small">Pour être justifiée, elle doit être déclarée au moins 15 jours avant la réunion et le motif accepté par les autres. Le motif est visible de tous.</p>
          <p><button class="btn primary" type="submit">Envoyer</button></p>
        </form></details>

      <details><summary>Une tromperie ou une rupture cachée</summary>
        <form method="post" action="<?= e(url('/moi/entree')) ?>"><?= TEC\Csrf::field() ?>
          <label for="x_type">Quoi</label>
          <select id="x_type" name="type"><option value="cheating">Tromperie</option><option value="hidden_breakup">Rupture cachée au groupe</option></select>
          <label for="x_note">Précision (facultatif)</label><textarea id="x_note" name="note" maxlength="500"></textarea>
          <p><button class="btn primary" type="submit">Envoyer</button></p>
        </form></details>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Mes déclarations<?= $year ? ' ' . (int)$year['year'] : '' ?></h2>
  <?php if (!$entries): ?>
    <p class="muted">Rien pour l'instant.</p>
  <?php else: ?>
    <div class="scroll"><table>
      <tr><th>Type</th><th>Détail</th><th>État</th><th></th></tr>
      <?php foreach ($entries as $e): ?>
        <tr>
          <td><?= e(TEC\Repo\Entries::TYPES[$e['type']]) ?></td>
          <td><?= e(TEC\Repo\Entries::describe($e)) ?>
            <?php if ($e['decision_note']): ?><div class="small muted">Administrateur : <?= e($e['decision_note']) ?></div><?php endif; ?></td>
          <td><span class="badge <?= e($e['status']) ?>"><?= e(TEC\Labels::ENTRY_STATUS[$e['status']]) ?></span><div class="small muted"><?= fr_datetime($e['decided_at'] ?: $e['created_at']) ?></div></td>
          <td><?php if ($e['status'] === 'pending'): ?>
            <form method="post" action="<?= e(url('/moi/entree/' . (int)$e['id'] . '/retirer')) ?>"><?= TEC\Csrf::field() ?><button class="btn small danger" type="submit">Retirer</button></form>
          <?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  <?php endif; ?>
</div>
