<h1>Barème</h1>
<?php require __DIR__ . '/nav.php'; ?>
<p class="muted">Chaque année est calculée avec la version du barème qui lui est rattachée. Les versions déjà utilisées ne changent jamais ; un amendement adopté en crée une nouvelle.</p>
<?php
$prev = null;
foreach ($versions as $v):
  $flat = [];
  foreach ($labels as $path => $label) { $flat[$path] = TEC\Scoring\Defaults::get($v['params'], $path); }
?>
  <div class="card">
    <h2><?= e($v['label']) ?> <span class="muted small">— à partir de <?= (int)$v['effective_year'] ?></span></h2>
    <?php if ($v['note']): ?><p class="small muted"><?= e($v['note']) ?></p><?php endif; ?>
    <div class="scroll"><table>
      <?php foreach ($flat as $path => $val): $changed = $prev !== null && ($prev[$path] ?? null) !== $val; ?>
        <tr style="<?= $changed ? 'background:var(--accent-soft)' : '' ?>"><td><?= e($labels[$path]) ?></td><td class="num"><?= e(json_encode($val)) ?><?= $changed ? ' <span class="small">(avant : ' . e(json_encode($prev[$path] ?? null)) . ')</span>' : '' ?></td></tr>
      <?php endforeach; ?>
    </table></div>
  </div>
<?php $prev = $flat; endforeach; ?>
