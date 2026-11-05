/* Avertit l'agent 2 minutes avant la fin de session et lui permet de la prolonger.
   Le serveur ferme la session après "duree" minutes sans requête : ce script envoie un signal
   discret quand l'agent travaille (frappe, clic, défilement), au plus une fois par minute. */
(function () {
  var script = document.currentScript || document.querySelector('script[data-duree]');
  if (!script) return;

  var duree = parseInt(script.dataset.duree, 10) * 60;      // secondes
  var avertir = parseInt(script.dataset.avertir, 10) * 60;  // secondes avant la fin
  if (!duree || duree <= avertir + 30) return;               // durée trop courte : on ne gêne pas

  var alerte = document.getElementById('session-alerte');
  var compteur = document.getElementById('session-secondes');
  var bouton = document.getElementById('session-prolonger');
  if (!alerte) return;

  var derniereActivite = Date.now();
  var dernierPing = Date.now();

  function ping() {
    dernierPing = Date.now();
    return fetch(script.dataset.ping, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': script.dataset.jeton, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (r) {
      if (r.status === 401 || r.status === 419) sortir();
    }).catch(function () { /* réseau coupé : on réessaiera */ });
  }

  function sortir() {
    window.location.href = script.dataset.sortie + '?expiree=1';
  }

  function activite() {
    if (!alerte.hidden) return;                 // pendant l'alerte, seul le bouton prolonge
    derniereActivite = Date.now();
    if (Date.now() - dernierPing > 60000) ping();
  }
  ['keydown', 'click', 'scroll', 'touchstart'].forEach(function (e) {
    document.addEventListener(e, activite, { passive: true });
  });

  bouton.addEventListener('click', function () {
    ping().then(function () {
      derniereActivite = Date.now();
      alerte.hidden = true;
    });
  });

  setInterval(function () {
    var restant = duree - Math.floor((Date.now() - derniereActivite) / 1000);
    if (restant <= 0) { sortir(); return; }
    if (restant <= avertir) {
      alerte.hidden = false;
      compteur.textContent = restant;
    }
  }, 1000);
})();
