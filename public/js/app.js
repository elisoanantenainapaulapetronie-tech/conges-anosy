(function () {
  var menu = document.getElementById('menu');
  var rideau = document.getElementById('rideau');
  var bouton = document.querySelector('.barre__menu');
  if (!menu || !bouton) return;
  function basculer(ouvrir) {
    menu.classList.toggle('ouvert', ouvrir);
    rideau.classList.toggle('ouvert', ouvrir);
    bouton.setAttribute('aria-expanded', ouvrir ? 'true' : 'false');
  }
  bouton.addEventListener('click', function () { basculer(!menu.classList.contains('ouvert')); });
  rideau.addEventListener('click', function () { basculer(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') basculer(false); });
})();
