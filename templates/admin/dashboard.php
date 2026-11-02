<h1>Administration</h1>
<?php require __DIR__ . '/nav.php'; ?>
<div class="grid">
  <div class="card">
    <h2>À traiter</h2>
    <p class="hero"><?= (int)$nameRequests ?></p>
    <p>demande(s) de changement de nom</p>
    <p><a class="btn primary" href="<?= e(url('/admin/joueurs')) ?>">Ouvrir</a></p>
  </div>
  <div class="card">
    <h2>Année en cours</h2>
    <?php if ($year): ?>
      <p class="hero"><?= (int)$year['year'] ?></p>
      <p><?= e(TEC\Labels::YEAR_STATUS[$year['status']]) ?> · réunion <?= fr_date($year['meeting_date']) ?></p>
      <p><?= (int)$players ?> joueur(s) actif(s) · <?= (int)$amendments ?> amendement(s)</p>
    <?php else: ?>
      <p class="muted">Aucune année ouverte.</p>
    <?php endif; ?>
  </div>
</div>
<div class="card">
  <h2>Ton rôle</h2>
  <p>L'administrateur porte la gestion du site, rien de plus : il ne voit ni ne valide les déclarations des joueurs, et n'a aucun accès anticipé aux statuts, ruptures ou bluffs. Tout se révèle à la soirée.</p>
  <h3>Déroulé d'une année</h3>
  <ol>
    <li>Fixer la date et le lieu, désigner l'<a href="<?= e(url('/admin/annee')) ?>">hôte</a> (volontaire, sinon tirage).</li>
    <li>Le jour J : ouvrir la soirée (ferme les déclarations), noter les <a href="<?= e(url('/admin/soiree')) ?>">présences</a>, créer les prix.</li>
    <li>Fin de soirée : désigner les gagnants des prix, puis <a href="<?= e(url('/admin/cloture')) ?>">clôturer</a> (aperçu puis confirmation).</li>
  </ol>
</div>
