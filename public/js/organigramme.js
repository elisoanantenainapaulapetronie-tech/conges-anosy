/* Organigramme (lot 4) : recherche, déplier/replier, ouverture depuis un lien, impression. Sans dépendance. */
(function () {
    'use strict';

    var detail = document.getElementById('detail');
    var champ = document.getElementById('org-recherche');
    var resultat = document.getElementById('org-resultat');
    if (!detail) { return; }

    var noeuds = Array.prototype.slice.call(detail.querySelectorAll('li.noeud'));

    function norme(texte) {
        return (texte || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }
    function details(li) { return li.querySelector(':scope > details'); }
    function parent(li) { return li.parentElement ? li.parentElement.closest('li.noeud') : null; }

    function ouvrirAscendants(li) {
        for (var p = li; p; p = parent(p)) {
            p.hidden = false;
            var d = details(p);
            if (d) { d.open = true; }
        }
    }

    /* Recherche : on garde les structures qui correspondent, leurs ascendantes et leurs sous-structures. */
    function filtrer() {
        var terme = norme(champ.value.trim());
        noeuds.forEach(function (li) { li.classList.remove('noeud--trouve'); li.hidden = terme !== ''; });

        if (terme === '') {
            resultat.textContent = '';
            return;
        }
        var trouves = 0;
        noeuds.forEach(function (li) {
            if (norme(li.getAttribute('data-texte')).indexOf(terme) === -1) { return; }
            trouves++;
            li.classList.add('noeud--trouve');
            ouvrirAscendants(li);
            Array.prototype.forEach.call(li.querySelectorAll('li.noeud'), function (enfant) { enfant.hidden = false; });
        });
        resultat.textContent = trouves === 0
            ? 'Aucun résultat.'
            : trouves + ' structure' + (trouves > 1 ? 's' : '') + ' correspondante' + (trouves > 1 ? 's' : '') + '.';
    }
    if (champ) { champ.addEventListener('input', filtrer); }

    /* Tout déplier / tout replier. */
    Array.prototype.forEach.call(document.querySelectorAll('[data-org]'), function (bouton) {
        bouton.addEventListener('click', function () {
            var ouvrir = bouton.getAttribute('data-org') === 'deplier';
            noeuds.forEach(function (li) { var d = details(li); if (d) { d.open = ouvrir; } });
        });
    });

    /* Lien #s12 (depuis le schéma, une alerte ou une redirection) : ouvre la structure et s'y rend. */
    function versCible() {
        var id = decodeURIComponent((window.location.hash || '').replace(/^#/, ''));
        if (!/^s\d+$/.test(id)) { return; }
        var li = document.getElementById(id);
        if (!li || !detail.contains(li)) { return; }
        if (champ && champ.value) { champ.value = ''; filtrer(); }
        ouvrirAscendants(li);
        noeuds.forEach(function (n) { n.classList.remove('noeud--cible'); });
        li.classList.add('noeud--cible');
        li.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }
    window.addEventListener('hashchange', versCible);
    versCible();

    /* Impression : tout déplier, puis rétablir. */
    var etats = null;
    function avantImpression() {
        etats = noeuds.map(function (li) { var d = details(li); return d ? d.open : false; });
        noeuds.forEach(function (li) { var d = details(li); if (d) { d.open = true; } li.hidden = false; });
    }
    function apresImpression() {
        if (!etats) { return; }
        noeuds.forEach(function (li, i) { var d = details(li); if (d) { d.open = etats[i]; } });
        etats = null;
        if (champ && champ.value) { filtrer(); }
    }
    window.addEventListener('beforeprint', avantImpression);
    window.addEventListener('afterprint', apresImpression);

    var imprimer = document.querySelector('[data-imprimer]');
    if (imprimer) { imprimer.addEventListener('click', function () { window.print(); }); }
})();
