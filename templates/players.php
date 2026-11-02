<h1>Joueurs</h1>
<div class="card">
  <p class="muted small">Dernier statut validé de chaque joueur. Les bluffs déclarés ne sont jamais montrés ici.</p>
  <div class="scroll"><table>
    <tr><th>Joueur</th><th>Dernier statut connu</th></tr>
    <?php foreach ($list as $row): ?>
      <tr><td><?= e($row['player']['name']) ?><?= $row['player']['home_place'] ? '<div class="small muted">' . e($row['player']['home_place']) . '</div>' : '' ?></td>
        <td><?php $s = $row['status']; require __DIR__ . '/partials/status.php'; ?>
          <?php if ($s && $s['year']): ?><div class="small muted">année <?= (int)$s['year'] ?></div><?php endif; ?></td></tr>
    <?php endforeach; ?>
  </table></div>
</div>
