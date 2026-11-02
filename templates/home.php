<h1>Accueil</h1>
<div class="grid">
  <div class="card">
    <h2>Cette année</h2>
    <?php if (!$year): ?>
      <p class="muted">Aucune année n'est ouverte pour l'instant.</p>
    <?php else: ?>
      <p class="hero"><?= (int)$year['year'] ?></p>
      <dl class="kv">
        <dt><?= icon("info") ?>État</dt><dd><?= e(TEC\Labels::YEAR_STATUS[$year['status']]) ?></dd>
        <dt><?= icon("calendar") ?>Réunion</dt><dd><?= fr_date($year['meeting_date']) ?><?= $year['meeting_place'] ? ' — ' . e($year['meeting_place']) : '' ?></dd>
        <dt><?= icon("crown") ?>Hôte</dt><dd><?= $host ? e($host) : '<span class="muted">pas encore désigné</span>' ?></dd>
      </dl>
      <?php if ($year['status'] === 'soiree'): ?>
        <p><a class="btn primary" href="<?= e(url('/soiree')) ?>">Rejoindre la soirée</a></p>
      <?php endif; ?>
      <?php if ($amendWindow && $amendWindow['open']): ?>
        <p class="small">Les amendements sont ouverts<?= $amendWindow['to'] ? ' jusqu\'au ' . fr_date($amendWindow['to']) : '' ?> : <a href="<?= e(url('/amendements')) ?>">proposer ou voter</a>.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Mon statut</h2>
    <p><?php $s = $me; require __DIR__ . '/partials/status.php'; ?></p>
    <p><a class="btn" href="<?= e(url('/moi')) ?>">Mes déclarations</a></p>
  </div>
</div>

<?php if ($absences): ?>
<div class="card">
  <h2>Absences prévues cette année</h2>
  <ul>
    <?php foreach ($absences as $a): ?>
      <li><strong><?= e($a['player_name']) ?></strong> — <?= e($a['data']['reason'] ?? '') ?> <span class="muted small">(déclarée le <?= fr_date($a['created_at']) ?>)</span></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="grid">
  <div class="card">
    <h2>Dernier classement annuel<?= $last ? ' (' . (int)$last['year'] . ')' : '' ?></h2>
    <?php if (!$ranking): ?>
      <p class="muted">Aucune année clôturée pour l'instant.</p>
    <?php else: ?>
      <table>
        <tr><th>#</th><th>Joueur</th><th class="num">Points</th></tr>
        <?php foreach ($ranking as $r): ?>
          <tr class="rank<?= (int)$r['rank_pos'] ?>"><td><?= (int)$r['rank_pos'] ?></td><td><?= e($players[(int)$r['player_id']]['name'] ?? '?') ?></td><td class="num"><?= (int)$r['score'] ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h2>Classement global</h2>
    <?php if (!$globalRank): ?>
      <p class="muted">Aucune année clôturée pour l'instant.</p>
    <?php else: ?>
      <table>
        <tr><th>#</th><th>Joueur</th><th class="num">Global</th></tr>
        <?php foreach ($globalRank as $r): ?>
          <tr class="rank<?= (int)$r['global_rank'] ?>"><td><?= (int)$r['global_rank'] ?></td><td><?= e($players[(int)$r['player_id']]['name'] ?? '?') ?></td><td class="num"><?= num($r['global_after']) ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
</div>
