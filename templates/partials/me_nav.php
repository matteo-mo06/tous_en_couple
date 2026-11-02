<?php $p = TEC\Http::path(); ?>
<div class="subnav">
  <a class="<?= $p === '/moi' ? 'on' : '' ?>" href="<?= e(url('/moi')) ?>">Mes déclarations</a>
  <a class="<?= str_starts_with($p, '/journal') ? 'on' : '' ?>" href="<?= e(url('/journal')) ?>">Mon journal</a>
</div>
