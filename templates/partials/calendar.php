<?php
/**
 * Grille d'un mois. Variables : $cal (Calendar::month), $mini (bool), $colorOf (fn(event): 1..8),
 * $dayUrl (?fn(date): url), $eventUrl (?fn(event): url d'édition), $editId, $selStart/$selEnd (sélection mise en avant).
 */
$days = ['lun', 'mar', 'mer', 'jeu', 'ven', 'sam', 'dim'];
?>
<div class="cal<?= $mini ? ' mini' : '' ?>" role="grid" aria-label="<?= e(fr_month($cal['month'])) ?>">
  <?php foreach ($days as $d): ?><div class="cal-h"><?= $mini ? e(mb_substr($d, 0, 1)) : e($d) ?></div><?php endforeach; ?>
  <?php foreach ($cal['weeks'] as $week): foreach ($week as $c): ?>
    <?php
      $href = (!$mini && $c['in'] && $dayUrl) ? $dayUrl($c['date']) : null;
      $n = count($c['events']);
      $a = $selStart ?? ''; $b = ($selEnd ?? '') ?: $a;
      $cls = 'cal-d' . ($c['in'] ? '' : ' out') . ($c['today'] ? ' today' : '') . ($href ? ' link' : '') . ($c['events'] ? ' has' : '')
        . (($c['date'] === $a || $c['date'] === $b) && $a !== '' ? ' sel' : '') . ($a !== '' && $c['date'] > $a && $c['date'] < $b ? ' range' : '');
    ?>
    <div class="<?= $cls ?>" role="gridcell" data-date="<?= e($c['date']) ?>">
      <?php if ($href): ?><a class="cal-hit" href="<?= e($href) ?>" aria-label="<?= e(fr_period($c['date'], null, true)) ?><?= $n ? ', ' . $n . ' événement(s)' : '' ?>"></a><?php endif; ?>
      <span class="n"><?= (int)$c['day'] ?></span>
      <?php if ($c['in'] && $c['events']): ?>
        <?php if (!$mini): ?>
          <span class="chips">
            <?php foreach (array_slice($c['events'], 0, 3) as $ev):
              $u = !empty($eventUrl) ? $eventUrl($ev) : null;
              $chipCls = 'chip c' . (int)$colorOf($ev) . ($ev['start_date'] !== $c['date'] && $ev['end_date'] ? ' cont' : '') . (($editId ?? 0) === (int)$ev['id'] ? ' editing' : ''); ?>
              <?php if ($u): ?><a class="<?= $chipCls ?>" href="<?= e($u) ?>" title="Modifier : <?= e($ev['title']) ?>"><?= e($ev['title']) ?></a>
              <?php else: ?><span class="<?= $chipCls ?>"><?= e($ev['title']) ?></span><?php endif; ?>
            <?php endforeach; ?>
            <?php if ($n > 3): ?><span class="more">+<?= $n - 3 ?></span><?php endif; ?>
          </span>
        <?php endif; ?>
        <span class="dots"><?php foreach (array_slice($c['events'], 0, 3) as $ev): ?><i class="dot c<?= (int)$colorOf($ev) ?>"></i><?php endforeach; ?></span>
      <?php endif; ?>
    </div>
  <?php endforeach; endforeach; ?>
</div>
