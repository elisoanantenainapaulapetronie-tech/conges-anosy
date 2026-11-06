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
