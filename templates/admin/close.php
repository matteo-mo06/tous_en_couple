<h1>Clôture <?= (int)$c['year']['year'] ?></h1>
<?php require __DIR__ . '/nav.php'; ?>
<p class="muted">Aperçu : rien n'est enregistré tant que tu n'as pas confirmé en bas de page. Barème utilisé : <?= e($c['rules']['label']) ?>.</p>

<?php foreach ($c['problems'] as $p): ?><div class="flash error"><?= e($p) ?></div><?php endforeach; ?>
<?php foreach ($c['warnings'] as $w): ?><div class="flash info"><?= e($w) ?></div><?php endforeach; ?>

<div class="card">
  <h2>Classement de l'année</h2>
  <div class="scroll"><table>
    <tr><th>#</th><th>Joueur</th><th class="num">Points</th><th>Détail</th><th class="num">Global</th></tr>
    <?php
      $order = array_keys($c['ranks']); usort($order, fn($a, $b) => [$c['ranks'][$a], $a] <=> [$c['ranks'][$b], $b]);
      foreach ($order as $pid): $r = $c['results'][$pid]; ?>
      <tr>
        <td><?= (int)$c['ranks'][$pid] ?></td>
        <td><?= e($c['names'][$pid]) ?></td>
        <td class="num"><strong><?= (int)$r['score'] ?></strong></td>
        <td class="small"><?php
          $bits = []; foreach ($r['cats'] as $k => $v) { $bits[] = e(TEC\Labels::cat($k)) . ' ' . signed($v); } echo $bits ? implode(' · ', $bits) : '—';
          foreach ($r['notes'] as $n) { echo '<div class="muted">• ' . e($n) . '</div>'; } ?></td>
        <td class="num"><?= num($c['global_before'][$pid]) ?> → <strong><?= num($c['global_after'][$pid]) ?></strong><div class="small muted">+<?= (int)$c['global_points'][$pid] ?> · rang <?= (int)$c['global_ranks'][$pid] ?></div></td>
      </tr>
    <?php endforeach; ?>
  </table></div>
</div>

<div class="grid">
  <div class="card">
    <h2>Cadeaux de l'année</h2>
    <?php if (!$c['gifts']): ?><p class="muted">Aucun.</p><?php endif; ?>
    <ul><?php foreach ($c['gifts'] as $g): ?>
      <li><?= e($c['names'][$g['giver']]) ?> → <?= e($c['names'][$g['receiver']]) ?><?= $g['tie'] ? ' <span class="muted small">(ex aequo : même valeur)</span>' : '' ?></li>
    <?php endforeach; ?></ul>
  </div>
  <div class="card">
    <h2>Cadeau global</h2>
    <?php if ($c['cycle']): ?>
      <p>🎁 <?= e(implode(' et ', array_map(fn($id) => $c['names'][$id], $c['cycle']['winners']))) ?></p>
    <?php else: ?><p class="muted">Pas cette année.</p><?php endif; ?>
  </div>
</div>

<?php if ($c['amendments']): ?>
<div class="card">
  <h2>Amendements</h2>
  <table><tr><th>Amendement</th><th class="num">Pour</th><th class="num">Contre</th><th>Résultat</th></tr>
    <?php foreach ($c['amendments'] as $a): ?>
      <tr><td><?= e($a['title']) ?><?= $a['param_key'] ? '<div class="small muted">' . e($labels[$a['param_key']] ?? $a['param_key']) . ' → ' . e($a['param_value']) . '</div>' : '<div class="small muted">Texte : à intégrer à la main</div>' ?></td>
        <td class="num"><?= (int)$a['yes'] ?></td><td class="num"><?= (int)$a['no'] ?></td>
        <td><span class="badge <?= $a['adopted'] ? 'approved' : 'rejected' ?>"><?= $a['adopted'] ? 'Adopté' : 'Rejeté' ?></span></td></tr>
    <?php endforeach; ?>
  </table>
  <?php if ($next && $next['applied']): ?><p class="small">Les amendements adoptés qui changent une valeur créent un nouveau barème pour <?= (int)$c['year']['year'] + 1 ?>.</p><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
  <h2>Confirmer</h2>
  <?php if ($c['problems']): ?>
    <p class="muted">Corrige les points en rouge avant de clôturer.</p>
  <?php else: ?>
    <p>La clôture enregistre les résultats pour toujours (ils ne sont plus recalculés) et ouvre l'année <?= (int)$c['year']['year'] + 1 ?>.</p>
    <form method="post" action="<?= e(url('/admin/cloture')) ?>" class="row">
      <?= TEC\Csrf::field() ?>
      <input type="text" name="confirm" placeholder="Tape <?= (int)$c['year']['year'] ?>" style="max-width:160px" autocomplete="off" required>
      <button class="btn primary" type="submit">Clôturer l'année</button>
    </form>
  <?php endif; ?>
</div>
