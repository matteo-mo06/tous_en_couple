<?php
/** @var array $cal @var array $inMonth @var ?array $edit @var string $day @var ?array $year @var int $me */
$kinds = TEC\Repo\Journal::KINDS;
$colorOf = fn($e) => $kinds[$e['kind']][2] ?? 8;
$mini = false;
$dayUrl = fn($d) => url('/journal?mois=' . $cal['month'] . '&jour=' . $d . '#form');
$eventUrl = fn($e) => TEC\Repo\Journal::canEdit($e, $me) ? url('/journal?modifier=' . (int)$e['id'] . '#form') : null;
$editId = $edit ? (int)$edit['id'] : 0;
$prefill = $edit ?: ['kind' => 'date', 'title' => '', 'note' => '', 'start_date' => $day, 'end_date' => $dayEnd, 'shared' => 1];
$startVal = $prefill['start_date'] ?: (date('Y-m') === $cal['month'] ? date('Y-m-d') : $cal['month'] . '-01');
$selStart = $startVal; $selEnd = $prefill['end_date'] ?: '';
$periode = (bool)$selEnd || TEC\Http::get('mode') === 'periode';
?>
<h1>Mon espace</h1>
<?php require __DIR__ . '/partials/me_nav.php'; ?>
<p class="muted">Note les moments qui comptent pour toi (soirée double date, crush, situationship…), même sans effet sur les points. Rien n'est visible des autres, administrateur compris, tant que la soirée n'a pas commencé : ensuite, ceux que tu as laissés « partagés » apparaissent dans le rewind de l'année.</p>

<div class="grid top journal-grid">
  <div>
    <div class="card">
      <div class="cal-bar">
        <a class="btn small" data-nav href="<?= e(url('/journal?mois=' . $cal['prev'] . ($edit ? '&modifier=' . (int)$edit['id'] : ''))) ?>" aria-label="Mois précédent"><?= icon('chev-left') ?></a>
        <h2><?= e(fr_month($cal['month'])) ?></h2>
        <a class="btn small" data-nav href="<?= e(url('/journal?mois=' . $cal['next'] . ($edit ? '&modifier=' . (int)$edit['id'] : ''))) ?>" aria-label="Mois suivant"><?= icon('chev-right') ?></a>
        <?php if ($cal['month'] !== date('Y-m')): ?><a class="btn small" data-nav href="<?= e(url('/journal' . ($edit ? '?modifier=' . (int)$edit['id'] : ''))) ?>">Aujourd'hui</a><?php endif; ?>
      </div>
      <?php require __DIR__ . '/partials/calendar.php'; ?>
      <p class="selection small" id="selection" aria-live="polite"></p>
      <div class="legend">
        <?php foreach ($kinds as $k => [$label, $ico, $c]): ?><span class="lg"><i class="dot c<?= $c ?>"></i><?= e($label) ?></span><?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <h2><?= icon('clock') ?>Ce mois-ci</h2>
      <?php if (!$inMonth): ?>
        <p class="muted">Rien pour <?= e(fr_month($cal['month'])) ?>. Clique sur un jour du calendrier pour ajouter quelque chose.</p>
      <?php else: ?>
        <ul class="events">
          <?php foreach ($inMonth as $ev): [$label, $ico, $c] = $kinds[$ev['kind']] ?? $kinds['other']; ?>
            <li class="ev c<?= $c ?>">
              <span class="ev-ico"><?= icon($ico) ?></span>
              <div class="ev-body">
                <div class="ev-title"><?= e($ev['title']) ?></div>
                <div class="small muted"><?= e(fr_period($ev['start_date'], $ev['end_date'], true)) ?> · <?= e($label) ?> ·
                  <?= (int)$ev['shared'] ? 'partagé au rewind' : '<span title="Jamais montré aux autres">' . icon('lock', 'inline') . ' privé</span>' ?></div>
                <?php if ($ev['note']): ?><div class="ev-note"><?= nl2br(e($ev['note'])) ?></div><?php endif; ?>
              </div>
              <?php if (TEC\Repo\Journal::canEdit($ev, $me)): ?>
                <div class="ev-actions">
                  <a class="btn small" href="<?= e(url('/journal?modifier=' . (int)$ev['id'] . '#form')) ?>" aria-label="Modifier"><?= icon('pencil') ?></a>
                  <form method="post" action="<?= e(url('/journal/' . (int)$ev['id'] . '/supprimer')) ?>" data-confirm="Supprimer cet événement ?">
                    <?= TEC\Csrf::field() ?><input type="hidden" name="mois" value="<?= e($cal['month']) ?>">
                    <button class="btn small danger" type="submit" aria-label="Supprimer"><?= icon('trash') ?></button>
                  </form>
                </div>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" id="form">
    <h2><?= icon($edit ? 'pencil' : 'plus') ?><?= $edit ? 'Modifier l\'événement' : 'Ajouter un événement' ?></h2>
    <?php if (!$year): ?>
      <p class="muted">Aucune année ouverte pour l'instant.</p>
    <?php else: ?>
    <form method="post" id="jform" data-edit="<?= $edit ? 1 : 0 ?>" action="<?= e(url($edit ? '/journal/' . (int)$edit['id'] : '/journal')) ?>">
      <?= TEC\Csrf::field() ?>
      <label for="j_kind">Type</label>
      <select id="j_kind" name="kind"><?php foreach ($kinds as $k => [$label]): ?><option value="<?= e($k) ?>" <?= $prefill['kind'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
      <label for="j_title">Titre</label>
      <input type="text" id="j_title" name="title" maxlength="120" required value="<?= e($prefill['title']) ?>" placeholder="Ex. : double date avec Léa et Tom">
      <div class="range-box">
        <label>Quand ?</label>
        <div class="seg" role="radiogroup" aria-label="Date ou période">
          <label><input type="radio" name="mode" value="date" id="mode_d" <?= $periode ? '' : 'checked' ?>><?= icon('calendar') ?>Date</label>
          <label><input type="radio" name="mode" value="periode" id="mode_p" <?= $periode ? 'checked' : '' ?>><?= icon('clock') ?>Période</label>
        </div>
        <div class="two">
          <div><label class="when-date" for="j_start">Date</label><label class="when-period" for="j_start">Du</label>
            <input type="date" id="j_start" name="start_date" required value="<?= e($startVal) ?>"></div>
          <div class="when-period"><label for="j_end">Au</label><input type="date" id="j_end" name="end_date" value="<?= e($prefill['end_date']) ?>"></div>
        </div>
        <p class="small muted when-date">Clique sur un jour du calendrier pour choisir la date.</p>
        <p class="small muted when-period">Clique sur un premier jour du calendrier, puis sur un second : s'il est avant, il devient le début ; après, la fin.</p>
      </div>
      <label for="j_note">Note (facultatif)</label>
      <textarea id="j_note" name="note" maxlength="1000"><?= e($prefill['note']) ?></textarea>
      <label class="check"><input type="checkbox" name="shared" value="1" <?= (int)$prefill['shared'] ? 'checked' : '' ?>> Montrer aux autres dans le rewind (à la soirée)</label>
      <p class="small muted">Décoche pour le garder privé pour toujours : toi seul le verras. Un événement partagé n'est plus modifiable une fois la soirée ouverte.</p>
      <div class="actions">
        <button class="btn primary" type="submit"><?= $edit ? 'Enregistrer' : 'Ajouter' ?></button>
        <?php if ($edit): ?><a class="btn" href="<?= e(url('/journal?mois=' . $cal['month'])) ?>">Annuler</a><?php endif; ?>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
