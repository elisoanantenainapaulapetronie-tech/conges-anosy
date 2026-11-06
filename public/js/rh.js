(function () {
  // Proposer le circuit de décision d'après le service choisi (la RH peut le changer).
  document.querySelectorAll('select[data-synchro]').forEach(function (liste) {
    var cible = document.getElementById(liste.getAttribute('data-synchro'));
    if (!cible) return;
    liste.addEventListener('change', function () {
      var option = liste.options[liste.selectedIndex];
      var valeur = option && option.getAttribute('data-rattachement');
      if (valeur) cible.value = valeur;
    });
  });

  // Proposer le responsable qui donne l'avis d'après le service choisi (aide : la RH décide toujours).
  document.querySelectorAll('select[data-suggerer]').forEach(function (service) {
    var cible = document.getElementById(service.getAttribute('data-suggerer'));
    if (!cible) return;
    var suggestions = {};
    try { suggestions = JSON.parse(cible.getAttribute('data-suggestions') || '{}'); } catch (e) { suggestions = {}; }

    var note = document.createElement('p');
    note.className = 'champ__aide';
    note.setAttribute('aria-live', 'polite');
    cible.parentNode.appendChild(note);

    var pose = null; // dernière valeur posée automatiquement : on ne l'écrase que si la RH n'a rien choisi d'autre

    function maj(initial) {
      note.className = 'champ__aide';
      if (!service.value) { note.textContent = ''; return; }
      var s = suggestions[service.value];
      if (!s) {
        note.textContent = '\u26A0 Aucun responsable n\u2019est d\u00E9sign\u00E9 pour ce service ni pour les structures dont il d\u00E9pend : sans responsable, l\u2019agent ne pourrait pas obtenir d\u2019avis. Choisissez-en un (pour un directeur : le Secr\u00E9taire G\u00E9n\u00E9ral).';
        note.className = 'champ__aide champ__aide--alerte';
        return;
      }
      var existe = cible.querySelector('option[value="' + s.id + '"]');
      if (!initial && existe && (cible.value === '' || cible.value === pose)) {
        cible.value = String(s.id);
        pose = String(s.id);
      }
      note.textContent = 'Propos\u00E9 d\u2019apr\u00E8s le service : ' + s.nom + '.';
    }

    service.addEventListener('change', function () { maj(false); });
    maj(true);
  });

  // Copier le code d'activation affiché une seule fois.
  document.querySelectorAll('[data-copier]').forEach(function (bouton) {
    bouton.addEventListener('click', function () {
      var source = document.getElementById(bouton.getAttribute('data-copier'));
      if (!source || !navigator.clipboard) return;
      navigator.clipboard.writeText(source.textContent.trim()).then(function () {
        bouton.textContent = 'Code copié';
      });
    });
  });
})();
