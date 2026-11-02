<h1>Déclarations</h1>
<?php require __DIR__ . '/nav.php'; ?>
<div class="card">
  <h2>En attente (<?= count($pending) ?>)</h2>
  <?php if (!$pending): ?><p class="muted">Rien à valider.</p><?php endif; ?>
  <?php foreach ($pending as $e): ?>
    <div style="border-top:1px solid var(--line);padding:10px 0">
      <strong><?= e($e['player_name']) ?></strong> — <?= e(TEC\Repo\Entries::TYPES[$e['type']]) ?> : <?= e(TEC\Repo\Entries::describe($e)) ?>
      <div class="small muted">déclarée le <?= fr_datetime($e['created_at']) ?></div>
      <form method="post" action="<?= e(url('/admin/entrees/' . (int)$e['id'] . '/decision')) ?>" class="row" style="margin-top:6px">
        <?= TEC\Csrf::field() ?>
        <input type="text" name="note" placeholder="Motif (obligatoire si refus)" style="max-width:300px">
        <button class="btn small primary" name="decision" value="approve">Valider</button>
        <button class="btn small danger" name="decision" value="reject">Refuser</button>
      </form>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($year): ?>
<div class="card">
  <h2>Saisir pour un joueur</h2>
  <p class="small muted">Pour consigner un signalement (tromperie, rupture cachée) ou une déclaration oubliée. Une saisie directe est validée tout de suite si la case est cochée.</p>
  <form method="post" action="<?= e(url('/admin/entrees')) ?>">
    <?= TEC\Csrf::field() ?>
    <label for="player_id">Joueur</label>
    <select id="player_id" name="player_id"><?php foreach ($players as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?></select>
    <label for="type">Type</label>
    <select id="type" name="type"><?php foreach (TEC\Repo\Entries::TYPES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
    <details open><summary>Détails (selon le type)</summary>
      <div class="grid">
        <div><label>Relation : statut</label><select name="status"><option value="single">Célibataire</option><option value="couple">En couple</option></select>
          <label>Partenaire</label><input type="text" name="partner">
          <label class="check"><input type="checkbox" name="far" value="1"> Partenaire lointain</label></div>
        <div><label>Étape</label><select name="step"><?php foreach (TEC\Repo\Entries::STEPS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
          <label>Rupture : type</label><select name="kind"><?php foreach (TEC\Repo\Entries::BREAKUPS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
          <label class="check"><input type="checkbox" name="couple_continues" value="1"> Le couple continue</label>
          <label class="check"><input type="checkbox" name="good_terms_requested" value="1"> Bons termes demandés</label></div>
        <div><label>Tentative : type</label><select name="attempt_kind"><?php foreach (TEC\Repo\Entries::ATTEMPTS as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
          <label>Faux partenaire (bluff)</label><input type="text" name="fake_partner">
          <label>Motif (absence)</label><input type="text" name="reason">
          <label>Précision (tromperie, rupture cachée)</label><input type="text" name="note"></div>
      </div>
    </details>
    <label class="check"><input type="checkbox" name="approve" value="1" checked> Valider immédiatement</label>
    <p><button class="btn primary" type="submit">Enregistrer</button></p>
  </form>
</div>

<div class="card">
  <h2>Déjà traitées cette année</h2>
  <?php if (!$done): ?><p class="muted">Rien.</p><?php else: ?>
  <div class="scroll"><table>
    <tr><th>Joueur</th><th>Déclaration</th><th>État</th></tr>
    <?php foreach (array_reverse($done) as $e): ?>
      <tr><td><?= e($e['player_name']) ?></td><td><?= e(TEC\Repo\Entries::TYPES[$e['type']]) ?> : <?= e(TEC\Repo\Entries::describe($e)) ?><?= $e['decision_note'] ? '<div class="small muted">' . e($e['decision_note']) . '</div>' : '' ?></td>
        <td><span class="badge <?= e($e['status']) ?>"><?= e(TEC\Labels::ENTRY_STATUS[$e['status']]) ?></span></td></tr>
    <?php endforeach; ?>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
