/* Avertit l'agent 2 minutes avant la fin de session et lui permet de la prolonger.
   Le serveur ferme la session après "duree" minutes sans requête : ce script envoie un signal
   discret quand l'agent travaille (frappe, clic, souris, défilement), au plus une fois par minute.
   Le délai est celui de l'INACTIVITÉ : chaque action le repousse (30 minutes, voir config/conges.php). */
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
    // On ferme réellement la session côté serveur, sinon la page de connexion renverrait vers l'accueil.
    var fin = function () { window.location.href = script.dataset.sortie + '?expiree=1'; };
    if (!script.dataset.deconnexion) { fin(); return; }
    fetch(script.dataset.deconnexion, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': script.dataset.jeton, 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(fin, fin);
  }

  function activite() {
    if (!alerte.hidden) return;                 // pendant l'alerte, seul le bouton prolonge
    derniereActivite = Date.now();
    if (Date.now() - dernierPing > 60000) ping();
  }
  ['keydown', 'click', 'scroll', 'touchstart', 'mousemove', 'pointerdown'].forEach(function (e) {
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
