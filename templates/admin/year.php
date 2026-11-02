<h1><?= $year ? 'Année ' . (int)$year['year'] : 'Année' ?></h1>
<?php require __DIR__ . '/nav.php'; ?>
<?php if (!$year): ?>
  <div class="card"><p class="muted">Aucune année ouverte.</p></div>
<?php else: ?>
<div class="card">
  <p><strong><?= e(TEC\Labels::YEAR_STATUS[$year['status']]) ?></strong></p>
  <form method="post" action="<?= e(url('/admin/annee')) ?>">
    <?= TEC\Csrf::field() ?>
    <label for="meeting_date">Date de la réunion</label><input type="date" id="meeting_date" name="meeting_date" value="<?= e($year['meeting_date']) ?>">
    <?php if ($window['known']): ?><p class="small muted">Amendements ouverts du <?= fr_date($window['from']) ?> au <?= fr_date($window['to']) ?>. Le préavis d'absence de 15 jours se compte avant cette date.</p><?php endif; ?>
    <label for="meeting_place">Lieu</label><input type="text" id="meeting_place" name="meeting_place" maxlength="190" value="<?= e($year['meeting_place']) ?>">
    <label for="host_id">Hôte de l'année</label>
    <select id="host_id" name="host_id"><option value="">— pas encore désigné —</option>
      <?php foreach ($parts as $p): ?>
        <option value="<?= (int)$p['player_id'] ?>" <?= (int)$year['host_id'] === (int)$p['player_id'] ? 'selected' : '' ?> <?= $lastHost === (int)$p['player_id'] ? 'disabled' : '' ?>><?= e($p['name']) ?><?= $lastHost === (int)$p['player_id'] ? ' (hôte l\'an passé)' : '' ?></option>
      <?php endforeach; ?>
    </select>
    <p><button class="btn primary" type="submit">Enregistrer</button></p>
  </form>
  <form method="post" action="<?= e(url('/admin/annee/ouvrir-soiree')) ?>" class="row">
    <?= TEC\Csrf::field() ?>
    <button class="btn" name="action" value="draw_host">Tirer l'hôte au sort</button>
    <?php if ($year['status'] === 'open'): ?>
      <button class="btn primary" name="action" value="open">Ouvrir la soirée</button>
    <?php else: ?>
      <button class="btn" name="action" value="reopen">Rouvrir les déclarations</button>
    <?php endif; ?>
  </form>
  <p class="small muted">Ouvrir la soirée ferme les déclarations des joueurs. Tu peux encore saisir pour eux dans « Déclarations ».</p>
</div>
<?php endif; ?>
