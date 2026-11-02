// Applique le thème choisi (clair / sombre) avant l'affichage, pour éviter un clignotement.
(function () {
  try {
    var t = localStorage.getItem('tec_theme');
    if (t === 'light' || t === 'dark') { document.documentElement.setAttribute('data-theme', t); }
  } catch (e) {}
})();
