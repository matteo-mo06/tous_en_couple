<h1>Soirée <?= (int)$year['year'] ?></h1>
<?php require __DIR__ . '/nav.php'; ?>
<?php if ($year['status'] !== 'soiree'): ?>
  <div class="flash info">La soirée n'est pas ouverte : les joueurs ne peuvent pas encore accuser ni voter. <a href="<?= e(url('/admin/annee')) ?>">Ouvrir la soirée</a></div>
<?php endif; ?>
<div class="card">
  <h2>Présences</h2>
  <form method="post" action="<?= e(url('/admin/soiree/presence')) ?>">
    <?= TEC\Csrf::field() ?>
    <div class="scroll"><table>
      <tr><th>Joueur</th><th>Présent</th><th>Retard &gt; 30 min</th><th>Partenaire</th><th>Distance jusqu'au lieu</th><th class="num">Accusations reçues</th></tr>
      <?php foreach ($parts as $p): $pid = (int)$p['player_id']; $pr = $p['present'] === null ? '' : (string)$p['present']; ?>
        <tr>
          <td><?= e($p['name']) ?><?= !empty($homes[$pid]) ? '<div class="small muted">' . e($homes[$pid]) . '</div>' : '' ?></td>
          <td><select name="p[<?= $pid ?>][present]"><option value="" <?= $pr === '' ? 'selected' : '' ?>>— ? —</option><option value="1" <?= $pr === '1' ? 'selected' : '' ?>>Présent</option><option value="0" <?= $pr === '0' ? 'selected' : '' ?>>Absent</option></select></td>
          <td><input type="checkbox" name="p[<?= $pid ?>][late]" value="1" <?= (int)$p['late'] ? 'checked' : '' ?> style="width:20px;height:20px"></td>
          <td><select name="p[<?= $pid ?>][partner]">
            <?php foreach (['present' => 'Présent', 'absent' => 'Absent', 'visio' => 'En visio'] as $k => $l): ?><option value="<?= $k ?>" <?= $p['partner_presence'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
          </select></td>
          <td><select name="p[<?= $pid ?>][band]"><?php foreach (TEC\Labels::DISTANCE as $k => $l): ?><option value="<?= $k ?>" <?= (int)$p['travel_band'] === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
          <td class="num"><?= (int)($accCount[$pid] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
    </table></div>
    <p class="small muted">« Partenaire » ne concerne que les joueurs en couple. La distance se compte depuis le lieu de l'hôte jusqu'au domicile du joueur.</p>
    <p><button class="btn primary" type="submit">Enregistrer les présences</button></p>
  </form>
</div>

<?php if ($absences): ?>
<div class="card">
  <h2>Motifs d'absence (votes des autres joueurs)</h2>
  <table><tr><th>Joueur</th><th>Motif</th><th>Déclarée le</th><th class="num">Pour</th><th class="num">Contre</th></tr>
    <?php foreach ($absences as $e): ?>
      <tr><td><?= e($e['player_name']) ?></td><td><?= e($e['data']['reason'] ?? '') ?></td><td><?= fr_date($e['created_at']) ?></td>
        <td class="num"><?= (int)($e['tally']['yes'] ?? 0) ?></td><td class="num"><?= (int)($e['tally']['no'] ?? 0) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>
<?php if ($terms): ?>
<div class="card">
  <h2>Clause « bons termes »</h2>
  <table><tr><th>Joueur</th><th>Rupture</th><th class="num">Oui</th><th class="num">Non</th></tr>
    <?php foreach ($terms as $e): ?>
      <tr><td><?= e($e['player_name']) ?></td><td><?= e(TEC\Repo\Entries::describe($e)) ?></td><td class="num"><?= (int)($e['tally']['yes'] ?? 0) ?></td><td class="num"><?= (int)($e['tally']['no'] ?? 0) ?></td></tr>
    <?php endforeach; ?>
  </table>
</div>
<?php endif; ?>

<div class="card">
  <h2>Prix et mentions (<?= count($prizes) ?>/<?= (int)$maxPrizes ?>)</h2>
  <?php foreach ($prizes as $pz): ?>
    <div style="border-top:1px solid var(--line);padding:10px 0">
      <strong><?= e($pz['label']) ?></strong>
      <div class="small muted">Votes : <?php
        $bits = []; foreach ($pz['tally'] as $cand => $n) { $bits[] = e($players[(int)$cand]['name'] ?? '?') . ' ' . (int)$n; } echo $bits ? implode(' · ', $bits) : 'aucun'; ?></div>
      <form method="post" action="<?= e(url('/admin/soiree/prix/' . (int)$pz['id'] . '/gagnant')) ?>" class="row">
        <?= TEC\Csrf::field() ?>
        <select name="winner" style="max-width:260px"><option value="">— pas de gagnant —</option>
          <?php foreach ($parts as $p): $pid = (int)$p['player_id']; $sel = $pz['winner_id'] ? (int)$pz['winner_id'] === $pid : (int)$pz['leader'] === $pid; ?>
            <option value="<?= $pid ?>" <?= $sel ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn small" type="submit">Valider le gagnant</button>
      </form>
    </div>
  <?php endforeach; ?>
  <?php if (count($prizes) < $maxPrizes): ?>
    <form method="post" action="<?= e(url('/admin/soiree/prix')) ?>" class="row" style="margin-top:10px">
      <?= TEC\Csrf::field() ?>
      <input type="text" name="label" placeholder="Nom du prix (ex. meilleure histoire)" maxlength="120" style="max-width:340px" required>
      <button class="btn small primary" type="submit">Ajouter un prix</button>
    </form>
  <?php endif; ?>
</div>
<p><a class="btn primary" href="<?= e(url('/admin/cloture')) ?>">Passer à la clôture</a></p>
