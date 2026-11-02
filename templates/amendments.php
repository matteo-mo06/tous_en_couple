<h1>Amendements</h1>
<?php if (!$year): ?>
  <div class="card"><p class="muted">Aucune année ouverte.</p></div>
<?php else: ?>
<div class="card">
  <p>
    <?php if (!$window['known']): ?>
      La date de la réunion n'est pas encore fixée : les amendements sont ouverts en attendant.
    <?php elseif ($window['open']): ?>
      <strong>Ouvert</strong> du <?= fr_date($window['from']) ?> au <?= fr_date($window['to']) ?> (1 semaine avant la réunion).
    <?php else: ?>
      <strong>Fermé.</strong> Les amendements s'ouvrent 4 mois avant la réunion (du <?= fr_date($window['from']) ?>) et se ferment 1 semaine avant (le <?= fr_date($window['to']) ?>).
    <?php endif; ?>
  </p>
  <p class="small muted">Majorité simple des votes exprimés ; en cas d'égalité, l'amendement est rejeté. Les résultats sont annoncés à la soirée et s'appliquent à l'année suivante. Avant de proposer, lis le document « Amendements de l'année 0 ».</p>
</div>

<?php if ($window['open']): ?>
<div class="card">
  <h2>Proposer un amendement</h2>
  <form method="post" action="<?= e(url('/amendements')) ?>">
    <?= TEC\Csrf::field() ?>
    <label for="title">Titre</label><input type="text" id="title" name="title" maxlength="190" required>
    <label for="body">Explication (pourquoi ce changement ?)</label><textarea id="body" name="body" required></textarea>
    <label for="param_key">Valeur du barème à changer (facultatif)</label>
    <select id="param_key" name="param_key">
      <option value="">— aucune : proposition de texte, à intégrer à la main —</option>
      <?php foreach ($labels as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?> (actuel : <?= e(json_encode(TEC\Scoring\Defaults::get($params, $k))) ?>)</option><?php endforeach; ?>
    </select>
    <label for="param_value">Nouvelle valeur</label><input type="text" id="param_value" name="param_value" inputmode="decimal" placeholder="ex. 2 ou −8 ou 0,8">
    <p class="small muted">Si tu choisis une valeur, l'amendement adopté la modifiera automatiquement pour l'année suivante.</p>
    <p><button class="btn primary" type="submit">Déposer</button></p>
  </form>
</div>
<?php endif; ?>

<h2>Amendements de l'année <?= (int)$year['year'] ?></h2>
<?php if (!$list): ?><div class="card"><p class="muted">Aucun amendement déposé.</p></div><?php endif; ?>
<?php foreach ($list as $a): ?>
  <div class="card">
    <div class="row between"><h3 style="margin:0"><?= e($a['title']) ?></h3><span class="badge <?= $a['status'] === 'adopted' ? 'approved' : ($a['status'] === 'rejected' ? 'rejected' : 'pending') ?>"><?= ['open' => 'En vote', 'adopted' => 'Adopté', 'rejected' => 'Rejeté'][$a['status']] ?></span></div>
    <p class="small muted">Proposé par <?= e($a['proposer']) ?> le <?= fr_date($a['created_at']) ?></p>
    <p><?= nl2br(e($a['body'])) ?></p>
    <?php if ($a['param_key']): ?>
      <p class="small"><strong><?= e($a['label']) ?></strong> : <?= e(json_encode($a['current'])) ?> → <strong><?= e($a['param_value']) ?></strong></p>
    <?php endif; ?>
    <?php if ($a['status'] === 'open'): ?>
      <form method="post" action="<?= e(url('/amendements/' . (int)$a['id'] . '/vote')) ?>" class="row">
        <?= TEC\Csrf::field() ?>
        <button class="btn small <?= $a['my_vote'] === 'yes' ? 'on' : '' ?>" name="choice" value="yes" <?= $window['open'] ? '' : 'disabled' ?>>Pour</button>
        <button class="btn small <?= $a['my_vote'] === 'no' ? 'on' : '' ?>" name="choice" value="no" <?= $window['open'] ? '' : 'disabled' ?>>Contre</button>
        <span class="small muted"><?= (int)$a['cast'] ?> vote(s) exprimé(s)<?= $showTally ? ' — pour ' . (int)$a['yes'] . ', contre ' . (int)$a['no'] : '' ?></span>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($past): ?>
<h2>Décisions précédentes</h2>
<div class="card"><table>
  <tr><th>Année</th><th>Amendement</th><th>Décision</th></tr>
  <?php foreach ($past as $a): ?>
    <tr><td><?= (int)$a['year'] ?></td><td><?= e($a['title']) ?><div class="small muted">par <?= e($a['proposer']) ?><?= $a['param_key'] ? ' — ' . e($labels[$a['param_key']] ?? $a['param_key']) . ' → ' . e($a['param_value']) : '' ?></div></td>
      <td><span class="badge <?= $a['status'] === 'adopted' ? 'approved' : 'rejected' ?>"><?= $a['status'] === 'adopted' ? 'Adopté' : 'Rejeté' ?></span></td></tr>
  <?php endforeach; ?>
</table></div>
<?php endif; ?>
