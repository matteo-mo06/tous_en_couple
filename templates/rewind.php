<?php
/** @var array $years @var ?array $sel @var ?array $current @var int $me ; si $sel : $events $colors $authors $only $minis */
$kinds = TEC\Repo\Journal::KINDS;
?>
<h1>Historique</h1>
<?php require __DIR__ . '/partials/history_nav.php'; ?>
<?php if (!$sel): ?>
  <div class="card">
    <h2><?= icon('rewind') ?>Rewind de l'année</h2>
    <p class="muted">Le rewind s'ouvre quand la soirée commence : tu y retrouveras les événements que chacun a choisi de partager<?= $current ? ' pour ' . (int)$current['year'] : '' ?>. En attendant, tes propres événements sont dans <a href="<?= e(url('/journal')) ?>">ton journal</a>.</p>
  </div>
<?php else:
  $colorOf = fn($e) => $colors[(int)$e['player_id']] ?? 8;
  $mini = true; $dayUrl = null; $selected = '';
  $base = '/rewind?annee=' . (int)$sel['year'];
  $byMonth = [];
  foreach ($events as $ev) { $byMonth[substr($ev['start_date'], 0, 7)][] = $ev; }
  ksort($byMonth);
?>
  <?php if (count($years) > 1): ?>
    <div class="subnav">
      <?php foreach ($years as $y): ?><a class="<?= (int)$y['id'] === (int)$sel['id'] ? 'on' : '' ?>" href="<?= e(url('/rewind?annee=' . (int)$y['year'])) ?>"><?= (int)$y['year'] ?></a><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2><?= icon('rewind') ?>Rewind <?= (int)$sel['year'] ?></h2>
    <p class="muted"><?= count($events) ?> moment(s)<?= $only ? '' : ' partagé(s) par ' . count($authors) . ' joueur(s)' ?>. Tes événements privés ne sont visibles que de toi (<?= icon('lock', 'inline') ?>).</p>
    <?php if ($authors): ?>
      <div class="chips-row">
        <a class="pill <?= $only ? '' : 'on' ?>" href="<?= e(url($base)) ?>">Tous</a>
        <?php foreach ($authors as $pid => $name): ?>
          <a class="pill <?= $only === $pid ? 'on' : '' ?>" href="<?= e(url($base . '&joueur=' . $pid)) ?>"><i class="dot c<?= (int)($colors[$pid] ?? 8) ?>"></i><?= e($name) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$events): ?>
    <div class="card"><p class="muted">Aucun événement à montrer pour cette année.</p></div>
  <?php else: ?>
    <div class="minis">
      <?php foreach ($minis as $cal): ?>
        <a class="mini-card" href="#m-<?= e($cal['month']) ?>">
          <div class="mini-t"><?= e(fr_month($cal['month'])) ?></div>
          <?php require __DIR__ . '/partials/calendar.php'; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <?php foreach ($byMonth as $ym => $list): ?>
      <div class="card" id="m-<?= e($ym) ?>">
        <h2><?= e(fr_month($ym)) ?></h2>
        <ul class="events">
          <?php foreach ($list as $ev): [$label, $ico] = $kinds[$ev['kind']] ?? $kinds['other']; $pc = $colors[(int)$ev['player_id']] ?? 8; ?>
            <li class="ev c<?= $pc ?>">
              <span class="ev-ico"><?= icon($ico) ?></span>
              <div class="ev-body">
                <div class="ev-title"><?= e($ev['title']) ?></div>
                <div class="small muted">
                  <strong class="who-tag"><i class="dot c<?= $pc ?>"></i><?= e($ev['player_name']) ?></strong> ·
                  <?= e(fr_period($ev['start_date'], $ev['end_date'], true)) ?> · <?= e($label) ?>
                  <?php if (!(int)$ev['shared']): ?> · <span title="Visible de toi seul"><?= icon('lock', 'inline') ?> privé</span><?php endif; ?>
                </div>
                <?php if ($ev['note']): ?><div class="ev-note"><?= nl2br(e($ev['note'])) ?></div><?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endif; ?>
