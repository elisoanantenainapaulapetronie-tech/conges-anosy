(function () {
  // Afficher / masquer le mot de passe
  document.querySelectorAll('[data-basculer]').forEach(function (bouton) {
    var champ = document.getElementById(bouton.getAttribute('data-basculer'));
    if (!champ) return;
    bouton.addEventListener('click', function () {
      var visible = champ.type === 'text';
      champ.type = visible ? 'password' : 'text';
      bouton.textContent = visible ? 'Afficher' : 'Masquer';
      bouton.setAttribute('aria-pressed', visible ? 'false' : 'true');
    });
  });

  // Liste des règles du mot de passe (aide visuelle ; le serveur revérifie toujours)
  document.querySelectorAll('[data-regles]').forEach(function (liste) {
    var mdp = document.getElementById(liste.getAttribute('data-cible'));
    var conf = document.getElementById(liste.getAttribute('data-confirmation'));
    if (!mdp) return;
    function maj() {
      var v = mdp.value;
      var etat = {
        longueur: v.length >= 8,
        lettre: /[A-Za-z\u00C0-\u024F]/.test(v),
        chiffre: /\d/.test(v),
        identique: v.length > 0 && conf && conf.value === v
      };
      liste.querySelectorAll('[data-regle]').forEach(function (li) {
        li.classList.toggle('ok', !!etat[li.getAttribute('data-regle')]);
      });
    }
    mdp.addEventListener('input', maj);
    if (conf) conf.addEventListener('input', maj);
    maj();
  });

  // Évite le double envoi des formulaires
  document.querySelectorAll('form[data-envoi]').forEach(function (form) {
    form.addEventListener('submit', function () {
      var b = form.querySelector('button[type="submit"]');
      if (!b || b.getAttribute('aria-busy') === 'true') return;
      b.setAttribute('aria-busy', 'true');
      b.dataset.texte = b.textContent;
      b.textContent = 'Veuillez patienter…';
    });
  });
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('button[aria-busy="true"]').forEach(function (b) {
      b.removeAttribute('aria-busy');
      if (b.dataset.texte) b.textContent = b.dataset.texte;
    });
  });
})();
