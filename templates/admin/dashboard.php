<h1>Administration</h1>
<?php require __DIR__ . '/nav.php'; ?>
<div class="grid">
  <div class="card">
    <h2>À traiter</h2>
    <p class="hero"><?= (int)$pending ?></p>
    <p>déclaration(s) en attente de validation</p>
    <?php if ($nameRequests): ?><p><?= (int)$nameRequests ?> demande(s) de changement de nom · <a href="<?= e(url('/admin/joueurs')) ?>">voir</a></p><?php endif; ?>
    <p><a class="btn primary" href="<?= e(url('/admin/entrees')) ?>">Ouvrir</a></p>
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
  <h2>Déroulé d'une année</h2>
  <ol>
    <li>Toute l'année : valider les <a href="<?= e(url('/admin/entrees')) ?>">déclarations</a> des joueurs.</li>
    <li>Fixer la date et le lieu, désigner l'<a href="<?= e(url('/admin/annee')) ?>">hôte</a> (volontaire, sinon tirage).</li>
    <li>Le jour J : ouvrir la soirée, noter les <a href="<?= e(url('/admin/soiree')) ?>">présences</a>, créer les prix.</li>
    <li>Fin de soirée : désigner les gagnants des prix, puis <a href="<?= e(url('/admin/cloture')) ?>">clôturer</a> (aperçu puis confirmation).</li>
  </ol>
</div>
