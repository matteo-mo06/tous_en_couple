<?php $p = TEC\Http::path(); ?>
<div class="subnav">
  <a class="<?= str_starts_with($p, '/historique') ? 'on' : '' ?>" href="<?= e(url('/historique')) ?>">Rangs par année</a>
  <a class="<?= $p === '/rewind' ? 'on' : '' ?>" href="<?= e(url('/rewind')) ?>">Rewind de l'année</a>
</div>
