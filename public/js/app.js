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

// Paramètres : choix du thème (automatique / clair / sombre), mémorisé dans ce navigateur.
(function () {
  var sel = document.getElementById('theme');
  if (!sel) { return; }
  try { sel.value = localStorage.getItem('tec_theme') || 'auto'; } catch (e) {}
  sel.addEventListener('change', function () {
    try {
      if (sel.value === 'auto') { localStorage.removeItem('tec_theme'); document.documentElement.removeAttribute('data-theme'); }
      else { localStorage.setItem('tec_theme', sel.value); document.documentElement.setAttribute('data-theme', sel.value); }
    } catch (e) {}
  });
})();

// Confirmation avant une action destructive : <form data-confirm="Message ?">
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute && ev.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) { ev.preventDefault(); }
});

// Journal : le calendrier remplit le formulaire (Date : un clic ; Période : deux clics, le second fixe l'autre borne).
(function () {
  var form = document.getElementById('jform');
  if (!form) { return; }
  var start = form.elements.start_date, end = form.elements.end_date, sel = document.getElementById('selection');
  var KEY = 'tec_journal_draft';
  var months = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
  function mode() { return form.elements.mode.value === 'periode' ? 'periode' : 'date'; }
  function setMode(m) { form.querySelector('input[name=mode][value=' + m + ']').checked = true; }
  function label(d) { var p = d.split('-'); return parseInt(p[2], 10) + ' ' + months[parseInt(p[1], 10) - 1] + ' ' + p[0]; }
  function paint() {
    var a = start.value, b = mode() === 'periode' ? end.value : '';
    var last = b || a;
    document.querySelectorAll('.cal-d[data-date]').forEach(function (c) {
      var d = c.getAttribute('data-date');
      c.classList.toggle('sel', !!a && (d === a || d === last));
      c.classList.toggle('range', !!a && !!b && d > a && d < b);
    });
    if (!sel) { return; }
    if (!a) { sel.textContent = ''; }
    else if (mode() === 'periode') {
      sel.innerHTML = b ? 'Période : <strong>' + label(a) + '</strong> → <strong>' + label(b) + '</strong>'
                        : 'Début : <strong>' + label(a) + '</strong> — clique sur le dernier jour (ou un jour avant pour le début).';
    } else { sel.innerHTML = 'Date : <strong>' + label(a) + '</strong>'; }
  }
  var anchor = null; // premier jour cliqué d'une période (en attente du second clic)
  function pick(date) {
    if (mode() === 'periode') {
      if (anchor !== null) {
        // second clic : avant le premier = nouveau début ; après = fin ; le même jour = jour unique
        if (date < anchor) { start.value = date; end.value = anchor; }
        else if (date > anchor) { start.value = anchor; end.value = date; }
        else { start.value = date; end.value = ''; }
        anchor = null;
      } else { anchor = date; start.value = date; end.value = ''; }
    } else { start.value = date; end.value = ''; }
    paint();
  }
  document.addEventListener('click', function (ev) {
    var hit = ev.target.closest && ev.target.closest('.cal-hit');
    if (!hit) { return; }
    ev.preventDefault();
    pick(hit.parentNode.getAttribute('data-date'));
  });
  form.querySelectorAll('input[name=mode]').forEach(function (r) {
    r.addEventListener('change', function () { anchor = null; if (mode() === 'date') { end.value = ''; } paint(); });
  });
  [start, end].forEach(function (i) { i.addEventListener('change', function () { anchor = null; paint(); }); });

  // Brouillon conservé quand on change de mois (le formulaire garde ce qui a été saisi)
  function draft() {
    var o = {};
    ['kind', 'title', 'start_date', 'end_date', 'note'].forEach(function (k) { o[k] = form.elements[k].value; });
    o.mode = mode(); o.shared = form.elements.shared.checked;
    return o;
  }
  document.querySelectorAll('a[data-nav]').forEach(function (a) {
    a.addEventListener('click', function () { try { sessionStorage.setItem(KEY, JSON.stringify(draft())); } catch (e) {} });
  });
  form.addEventListener('submit', function () { try { sessionStorage.removeItem(KEY); } catch (e) {} });
  if (form.getAttribute('data-edit') === '0') {
    try {
      var d = JSON.parse(sessionStorage.getItem(KEY) || 'null');
      if (d) {
        ['kind', 'title', 'start_date', 'end_date', 'note'].forEach(function (k) { form.elements[k].value = d[k] || ''; });
        form.elements.shared.checked = !!d.shared; setMode(d.mode === 'periode' ? 'periode' : 'date');
        if (d.mode === 'periode' && d.start_date && !d.end_date) { anchor = d.start_date; }
      }
    } catch (e) {}
  } else { try { sessionStorage.removeItem(KEY); } catch (e) {} }
  paint();
})();
