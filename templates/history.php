<h1>Historique</h1>
<?php if (!$closed): ?>
  <div class="card"><p class="muted">Aucune année clôturée pour l'instant.</p></div>
<?php else: ?>
<div class="card">
  <h2>Rang annuel de chaque joueur</h2>
  <div class="scroll"><table>
    <tr><th>Joueur</th><?php foreach ($closed as $y): ?><th class="num"><a href="<?= e(url('/historique/' . (int)$y['year'])) ?>"><?= (int)$y['year'] ?></a></th><?php endforeach; ?></tr>
    <?php foreach ($matrix as $pid => $years): ?>
      <tr><td><?= e($players[$pid]['name'] ?? '?') ?></td>
        <?php foreach ($closed as $y): $r = $years[(int)$y['year']] ?? null; ?>
          <td class="num"><?= $r ? (int)$r['rank_pos'] . '<span class="muted small"> · ' . (int)$r['score'] . ' pts</span>' : '<span class="muted">—</span>' ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>
<?php endif; ?>
