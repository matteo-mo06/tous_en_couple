<h1>Soirée</h1>
<?php if (!$open): ?>
  <div class="card"><p class="muted">La soirée n'est pas ouverte.
    <?php if ($year && $year['meeting_date']): ?>Prochaine réunion : <strong><?= fr_date($year['meeting_date']) ?></strong><?= $year['meeting_place'] ? ' — ' . e($year['meeting_place']) : '' ?>.<?php endif; ?></p></div>
<?php else: ?>
<div id="live">
  <p class="muted small">Cette page se met à jour toute seule. Année <?= (int)$year['year'] ?>.</p>

  <div class="card">
    <h2>Suspicions</h2>
    <p class="small muted">Désigne les joueurs dont tu penses que le partenaire présenté est un bluff. Bluff démasqué : −5 pour lui. Seul à avoir raison : +2 pour toi. Accusation à tort : −1. Tu peux changer d'avis jusqu'à la fin de la soirée ; personne ne voit tes choix avant le résultat.</p>
    <?php $others = array_filter($parts, fn($p) => (int)$p['player_id'] !== $me && (string)$p['present'] !== '0'); ?>
    <?php foreach ($others as $p): $on = in_array((int)$p['player_id'], $mine, true); ?>
      <form method="post" action="<?= e(url('/soiree/accuser')) ?>" style="display:inline-block;margin:4px 6px 4px 0">
        <?= TEC\Csrf::field() ?><input type="hidden" name="target" value="<?= (int)$p['player_id'] ?>">
        <button class="btn <?= $on ? 'on' : '' ?>" type="submit"><?= $on ? '🔎 Je soupçonne ' : '' ?><?= e($p['name']) ?></button>
      </form>
    <?php endforeach; ?>
    <p class="small"><?= count($mine) ?> accusation(s) en cours.</p>
  </div>

  <?php if ($absent): ?>
  <div class="card">
    <h2>Motifs d'absence</h2>
    <p class="small muted">Le motif est-il valable ? Majorité simple des autres joueurs.</p>
    <?php foreach ($absent as $e): ?>
      <div style="margin:10px 0">
        <strong><?= e($players[(int)$e['player_id']]['name'] ?? '?') ?></strong> : <?= e($e['data']['reason'] ?? '') ?>
        <?php if ((int)$e['player_id'] === $me): ?>
          <span class="muted small"> — c'est toi, tu ne votes pas.</span>
        <?php else: ?>
          <form method="post" action="<?= e(url('/soiree/vote')) ?>" class="row">
            <?= TEC\Csrf::field() ?><input type="hidden" name="kind" value="absence"><input type="hidden" name="ref" value="<?= (int)$e['id'] ?>">
            <button class="btn small <?= $e['my_vote'] === 'yes' ? 'on' : '' ?>" name="choice" value="yes">Motif valable</button>
            <button class="btn small <?= $e['my_vote'] === 'no' ? 'on' : '' ?>" name="choice" value="no">Pas valable</button>
            <span class="muted small"><?= (int)$e['votes_cast'] ?> vote(s)</span>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($terms): ?>
  <div class="card">
    <h2>Ruptures « en bons termes »</h2>
    <p class="small muted">L'ex a été appelé : la rupture s'est-elle faite en bons termes ? Si oui, le malus de rupture est annulé (la série reste cassée).</p>
    <?php foreach ($terms as $e): ?>
      <div style="margin:10px 0">
        <strong><?= e($players[(int)$e['player_id']]['name'] ?? '?') ?></strong> : <?= e(TEC\Repo\Entries::describe($e)) ?>
        <?php if ((int)$e['player_id'] !== $me): ?>
          <form method="post" action="<?= e(url('/soiree/vote')) ?>" class="row">
            <?= TEC\Csrf::field() ?><input type="hidden" name="kind" value="terms"><input type="hidden" name="ref" value="<?= (int)$e['id'] ?>">
            <button class="btn small <?= $e['my_vote'] === 'yes' ? 'on' : '' ?>" name="choice" value="yes">Bons termes</button>
            <button class="btn small <?= $e['my_vote'] === 'no' ? 'on' : '' ?>" name="choice" value="no">Non</button>
            <span class="muted small"><?= (int)$e['votes_cast'] ?> vote(s)</span>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($prizes): ?>
  <div class="card">
    <h2>Prix et mentions</h2>
    <p class="small muted">On ne vote pas pour soi. Un joueur ne peut recevoir qu'un prix par an.</p>
    <?php foreach ($prizes as $pz): ?>
      <form method="post" action="<?= e(url('/soiree/vote')) ?>" style="margin:10px 0">
        <?= TEC\Csrf::field() ?><input type="hidden" name="kind" value="prize"><input type="hidden" name="ref" value="<?= (int)$pz['id'] ?>">
        <label for="pz<?= (int)$pz['id'] ?>"><?= e($pz['label']) ?></label>
        <div class="row">
          <select id="pz<?= (int)$pz['id'] ?>" name="choice" style="max-width:260px">
            <option value="">— choisir —</option>
            <?php foreach ($parts as $p): if ((int)$p['player_id'] === $me) { continue; } ?>
              <option value="<?= (int)$p['player_id'] ?>" <?= (string)$pz['my_vote'] === (string)$p['player_id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn small" type="submit">Voter</button>
          <?php if ($pz['winner_id']): ?><span class="small">🏆 <?= e($players[(int)$pz['winner_id']]['name'] ?? '?') ?></span><?php endif; ?>
        </div>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
