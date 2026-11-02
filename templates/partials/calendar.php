<?php
/**
 * Grille d'un mois. Variables : $cal (Calendar::month), $mini (bool), $colorOf (fn(event): 1..8),
 * $dayUrl (?fn(date): url), $selected (date mise en avant, facultatif).
 */
$days = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
?>
<div class="cal<?= $mini ? ' mini' : '' ?>" role="grid" aria-label="<?= e(fr_month($cal['month'])) ?>">
  <?php foreach ($days as $d): ?><div class="cal-h"><?= $mini ? e(mb_substr($d, 0, 1)) : e($d) ?></div><?php endforeach; ?>
  <?php foreach ($cal['weeks'] as $week): foreach ($week as $c): ?>
    <?php
      $cls = 'cal-d' . ($c['in'] ? '' : ' out') . ($c['today'] ? ' today' : '') . (($selected ?? '') === $c['date'] ? ' sel' : '') . ($c['events'] ? ' has' : '');
      $href = (!$mini && $c['in'] && $dayUrl) ? $dayUrl($c['date']) : null;
      $n = count($c['events']);
    ?>
    <<?= $href ? 'a href="' . e($href) . '"' : 'div' ?> class="<?= $cls ?>" role="gridcell"<?= $href ? ' aria-label="' . e(fr_period($c['date'], null, true)) . ($n ? ', ' . $n . ' événement(s)' : '') . '"' : '' ?>>
      <span class="n"><?= (int)$c['day'] ?></span>
      <?php if ($c['in'] && $c['events']): ?>
        <?php if (!$mini): ?>
          <span class="chips">
            <?php foreach (array_slice($c['events'], 0, 3) as $ev): ?>
              <span class="chip c<?= (int)$colorOf($ev) ?><?= $ev['start_date'] !== $c['date'] && $ev['end_date'] ? ' cont' : '' ?>"><?= e($ev['title']) ?></span>
            <?php endforeach; ?>
            <?php if ($n > 3): ?><span class="more">+<?= $n - 3 ?></span><?php endif; ?>
          </span>
        <?php endif; ?>
        <span class="dots"><?php foreach (array_slice($c['events'], 0, 3) as $ev): ?><i class="dot c<?= (int)$colorOf($ev) ?>"></i><?php endforeach; ?></span>
      <?php endif; ?>
    </<?= $href ? 'a' : 'div' ?>>
  <?php endforeach; endforeach; ?>
</div>
