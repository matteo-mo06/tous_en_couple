<?php $p = TEC\Http::path(); $items = ['/admin' => 'Tableau de bord', '/admin/entrees' => 'Déclarations', '/admin/annee' => 'Année', '/admin/soiree' => 'Soirée', '/admin/cloture' => 'Clôture', '/admin/joueurs' => 'Joueurs', '/admin/bareme' => 'Barème']; ?>
<div class="subnav">
  <?php foreach ($items as $h => $l): ?><a class="<?= $p === $h ? 'on' : '' ?>" href="<?= e(url($h)) ?>"><?= e($l) ?></a><?php endforeach; ?>
</div>
