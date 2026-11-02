// Messages (toasts) : disparaissent seuls (animation CSS), clic sur × ou sur le message pour fermer plus tôt.
(function () {
  var box = document.querySelector('.toasts');
  if (!box) { return; }
  box.addEventListener('click', function (ev) {
    var t = ev.target.closest('.toast');
    if (t) { t.remove(); }
  });
  box.addEventListener('animationend', function (ev) {
    if (ev.animationName === 'toast-life' && ev.target.remove) { ev.target.remove(); }
  });
})();

// Rafraîchit la zone #live toutes les 5 secondes (soirée « en direct », sans connexion permanente).
(function () {
  var live = document.getElementById('live');
  if (!live) { return; }
  var busy = false;
  setInterval(function () {
    if (busy || document.hidden) { return; }
    // On ne rafraîchit pas pendant qu'on écrit dans un champ
    var a = document.activeElement;
    if (a && (a.tagName === 'INPUT' || a.tagName === 'TEXTAREA' || a.tagName === 'SELECT')) { return; }
    busy = true;
    fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var fresh = doc.getElementById('live');
        if (fresh) { live.innerHTML = fresh.innerHTML; }
      })
      .catch(function () {})
      .then(function () { busy = false; });
  }, 5000);
})();
