/* Lot 5 : simulation de durée d'une absence (aucun enregistrement). Sans JavaScript, le formulaire n'est simplement pas utilisable. */
(function () {
    'use strict';
    var form = document.getElementById('simulateur');
    var sortie = document.getElementById('simulation-resultat');
    if (!form || !sortie) { return; }

    function el(tag, classe, texte) {
        var n = document.createElement(tag);
        if (classe) { n.className = classe; }
        if (texte !== undefined) { n.textContent = texte; }
        return n;
    }

    function afficherErreur(message) {
        sortie.hidden = false;
        sortie.replaceChildren(el('p', 'simulation__erreur', message));
    }

    function date(iso) {
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var donnees = new FormData(form);
        if (!donnees.get('debut') || !donnees.get('fin')) {
            afficherErreur('Choisissez la date de début et la date de fin.');
            return;
        }
        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': donnees.get('_token') },
            body: donnees,
            credentials: 'same-origin'
        }).then(function (r) {
            return r.json().then(function (corps) { return { ok: r.ok, corps: corps }; });
        }).then(function (r) {
            if (!r.ok) {
                var erreurs = r.corps && r.corps.errors ? Object.keys(r.corps.errors).map(function (k) { return r.corps.errors[k][0]; }).join(' ') : '';
                afficherErreur(erreurs || (r.corps && r.corps.message) || 'Calcul impossible.');
                return;
            }
            rendre(r.corps);
        }).catch(function () {
            afficherErreur('Le calcul n’a pas pu être fait. Vérifiez votre connexion et réessayez.');
        });
    });

    function rendre(d) {
        var racine = document.createDocumentFragment();
        racine.appendChild(el('div', 'simulation__total', d.total_libelle));
        racine.appendChild(el('p', null, 'décomptés sur ' + d.calendaires + ' jour(s) du calendrier (week-ends et jours fériés exclus).'));

        if (d.soldes.length) {
            var liste = el('ul', 'simulation__soldes');
            d.soldes.forEach(function (s) {
                var texte = s.enregistre
                    ? 'Solde prévisionnel ' + s.annee + ' : ' + s.previsionnel + ' j → ' + s.apres + ' j après cette absence' + (s.apres < 0 ? ' (insuffisant)' : '') + '.'
                    : 'Aucun solde enregistré pour ' + s.annee + '.';
                liste.appendChild(el('li', s.apres < 0 && s.enregistre ? 'simulation__erreur' : null, texte));
            });
            racine.appendChild(liste);
        }
        d.avertissements.forEach(function (a) { racine.appendChild(el('p', 'simulation__avert', a)); });

        var tableau = el('table', 'simulation__jours');
        var tete = el('tr');
        ['Date', 'Jour', 'Décompté', 'Remarque'].forEach(function (t) { tete.appendChild(el('th', null, t)); });
        tableau.appendChild(tete);
        d.lignes.forEach(function (l) {
            var tr = el('tr', l.valeur === 0 ? 'jour--non' : (l.valeur < 1 ? 'jour--demi' : null));
            tr.appendChild(el('td', null, date(l.date)));
            tr.appendChild(el('td', null, l.jour));
            tr.appendChild(el('td', null, String(l.valeur).replace('.', ',')));
            tr.appendChild(el('td', null, l.motif || ''));
            tableau.appendChild(tr);
        });
        racine.appendChild(tableau);

        sortie.hidden = false;
        sortie.replaceChildren(racine);
    }
})();
