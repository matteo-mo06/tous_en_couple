<h1>Classement</h1>
<?php if (!$sel): ?>
  <div class="card"><p class="muted">Aucune année n'est encore clôturée. Les classements apparaîtront après la première réunion.</p></div>
<?php else: ?>
  <div class="row" style="margin-bottom:12px">
    <?php foreach ($closed as $y): ?>
      <a class="btn small <?= (int)$y['id'] === (int)$sel['id'] ? 'on' : '' ?>" href="<?= e(url('/classement?annee=' . (int)$y['year'])) ?>"><?= (int)$y['year'] ?></a>
    <?php endforeach; ?>
  </div>
  <div class="card">
    <h2>Classement de l'année <?= (int)$sel['year'] ?></h2>
    <div class="scroll"><table>
      <tr><th>#</th><th>Joueur</th><th class="num">Points</th><th class="num">+ global</th><th class="num">Global</th><th class="num">Rang global</th></tr>
      <?php foreach ($rows as $r): ?>
        <tr class="rank<?= (int)$r['rank_pos'] ?>">
          <td><?= (int)$r['rank_pos'] ?></td>
          <td><a href="<?= e(url('/historique/' . (int)$sel['year'])) ?>#p<?= (int)$r['player_id'] ?>"><?= e($players[(int)$r['player_id']]['name'] ?? '?') ?></a></td>
          <td class="num"><?= (int)$r['score'] ?></td>
          <td class="num"><?= signed($r['global_points']) ?></td>
          <td class="num"><?= num($r['global_after']) ?></td>
          <td class="num"><?= (int)$r['global_rank'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
  </div>

  <?php $gifts = $summary ? json_decode_array($summary['gifts']) : []; $cycle = $summary && $summary['cycle_winners'] ? json_decode_array($summary['cycle_winners']) : []; ?>
  <div class="grid">
    <div class="card">
      <h2>Cadeaux de l'année</h2>
      <?php if (!$gifts): ?><p class="muted">Aucun.</p><?php else: ?>
        <ul>
          <?php foreach ($gifts as $g): ?>
            <li><strong><?= e($players[(int)$g['giver']]['name'] ?? '?') ?></strong> offre à <strong><?= e($players[(int)$g['receiver']]['name'] ?? '?') ?></strong><?= !empty($g['tie']) ? ' <span class="muted small">(ex aequo : cadeaux de même valeur)</span>' : '' ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="muted small">À remettre dans le mois qui suit la réunion.</p>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>Cadeau global (tous les 3 ans)</h2>
      <?php if ($cycle): ?>
        <p><?= icon("gift", "inline") ?> <?= e(implode(' et ', array_map(fn($id) => $players[(int)$id]['name'] ?? '?', $cycle))) ?> reçoit<?= count($cycle) > 1 ? 'ent' : '' ?> le cadeau commun des autres joueurs.</p>
      <?php else: ?>
        <p class="muted">Pas de cadeau global cette année-là.</p>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>
