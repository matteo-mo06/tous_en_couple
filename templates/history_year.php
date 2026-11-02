<h1>Année <?= (int)$year['year'] ?></h1>
<div class="card">
  <dl class="kv">
    <dt>Réunion</dt><dd><?= fr_date($year['meeting_date']) ?><?= $year['meeting_place'] ? ' — ' . e($year['meeting_place']) : '' ?></dd>
    <dt>Hôte</dt><dd><?= $host ? e($host) : '—' ?></dd>
    <dt>Barème</dt><dd><?= e($rules['label'] ?? '?') ?></dd>
    <?php foreach ($prizes as $pz): ?><dt>Prix</dt><dd><?= e($pz['label']) ?> : <?= $pz['winner_id'] ? e($players[(int)$pz['winner_id']]['name'] ?? '?') : '—' ?></dd><?php endforeach; ?>
  </dl>
</div>
<?php foreach ($rows as $r):
  $cats = json_decode_array($r['breakdown']); $notes = json_decode_array($r['notes']); ?>
  <div class="card" id="p<?= (int)$r['player_id'] ?>">
    <div class="row between">
      <h2><?= (int)$r['rank_pos'] ?>. <?= e($players[(int)$r['player_id']]['name'] ?? '?') ?></h2>
      <strong><?= (int)$r['score'] ?> pts</strong>
    </div>
    <?php if ($cats): ?>
      <table>
        <?php foreach ($cats as $k => $v): ?>
          <tr><td><?= e(TEC\Labels::cat($k)) ?></td><td class="num <?= $v > 0 ? 'pos' : 'neg' ?>"><?= signed($v) ?></td></tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?><p class="muted">Aucun point cette année-là.</p><?php endif; ?>
    <?php foreach ($notes as $n): ?><p class="small muted">• <?= e($n) ?></p><?php endforeach; ?>
    <p class="small muted">Global : <?= num($r['global_before']) ?> × usure + <?= (int)$r['global_points'] ?> = <strong><?= num($r['global_after']) ?></strong> (rang <?= (int)$r['global_rank'] ?>)</p>
  </div>
<?php endforeach; ?>
