<?php /** @var ?array $s statut renvoyé par PlayerController::lastStatus */ ?>
<?php if (!$s): ?>
  <span class="muted">Pas encore de statut connu</span>
<?php elseif ($s['status'] === 'couple'): ?>
  En couple avec <strong><?= e($s['partner'] ?? '?') ?></strong><?= !empty($s['far']) ? ' (partenaire lointain)' : '' ?>
  <?php if ($s['series']): ?><span class="muted small"> · <?= (int)$s['series'] ?> an(s) ensemble</span><?php endif; ?>
<?php else: ?>
  Célibataire
<?php endif; ?>
