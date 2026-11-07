(function () {
  // Aperçu de la photo choisie avant l'envoi, et contrôle du poids côté navigateur.
  var MAX = 5 * 1024 * 1024;

  document.querySelectorAll('[data-photo-form]').forEach(function (formulaire) {
    var champ = formulaire.querySelector('input[type="file"]');
    var apercu = formulaire.querySelector('.photo-apercu');
    var image = apercu && apercu.querySelector('img');
    var message = formulaire.querySelector('[data-photo-message]');
    if (!champ || !apercu || !image) return;

    champ.addEventListener('change', function () {
      var fichier = champ.files && champ.files[0];
      if (message) message.textContent = '';
      if (!fichier) { apercu.hidden = true; return; }

      if (fichier.size > MAX) {
        champ.value = '';
        apercu.hidden = true;
        if (message) message.textContent = 'Cette photo dépasse 5 Mo : choisissez-en une plus légère.';
        return;
      }

      if (image.dataset.url) URL.revokeObjectURL(image.dataset.url);
      image.dataset.url = URL.createObjectURL(fichier);
      image.src = image.dataset.url;
      apercu.hidden = false;
    });
  });
})();
