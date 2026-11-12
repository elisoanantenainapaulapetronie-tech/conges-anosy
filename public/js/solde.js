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

    function nombre(v) {
        return Number(v).toLocaleString('fr-FR', { maximumFractionDigits: 2 });
    }

    function rendre(d) {
        var racine = document.createDocumentFragment();
        racine.appendChild(el('div', 'simulation__total', d.total_libelle));
        racine.appendChild(el('p', null, 'décomptés sur ' + d.calendaires + ' jour(s) du calendrier (week-ends et jours fériés exclus).'));

        if (d.soldes.length) {
            var civiles = el('p', 'simulation__civile', 'Par année civile : ' + d.soldes.map(function (s) { return s.jours_libelle + ' en ' + s.annee; }).join(', ') + '.');
            racine.appendChild(civiles);
        }

        var imp = d.imputation;
        if (imp) {
            racine.appendChild(el('h3', 'simulation__titre', 'Débit par lot (le plus ancien d’abord)'));
            var lots = el('table', 'simulation__lots');
            var teteLots = el('tr');
            ['Lot', 'Disponible avant', 'Débit', 'Dont provisoire', 'Disponible après'].forEach(function (t, i) { teteLots.appendChild(el('th', i ? 'num' : null, t)); });
            lots.appendChild(teteLots);
            imp.lots.forEach(function (l) {
                if (l.debit_reel === 0 && l.debit_provisoire === 0 && l.avant === 0) { return; }
                var tr = el('tr', l.debit_reel + l.debit_provisoire > 0 ? 'lot--debite' : null);
                tr.appendChild(el('th', null, String(l.annee)));
                tr.appendChild(el('td', 'num', nombre(l.avant)));
                tr.appendChild(el('td', 'num', l.debit_reel + l.debit_provisoire > 0 ? '− ' + nombre(l.debit_reel + l.debit_provisoire) : '0'));
                tr.appendChild(el('td', 'num', l.debit_provisoire > 0 ? nombre(l.debit_provisoire) : '—'));
                tr.appendChild(el('td', 'num', nombre(l.apres)));
                lots.appendChild(tr);
            });
            racine.appendChild(lots);

            if (imp.suffisant && imp.provisoire === 0) {
                racine.appendChild(el('p', 'simulation__ok', 'Votre solde couvre cette période.'));
            }
            imp.messages.forEach(function (m) {
                racine.appendChild(el('p', imp.suffisant ? 'simulation__avert' : 'simulation__erreur', m));
            });
            if (imp.issues.length) {
                racine.appendChild(el('p', 'simulation__issues-titre', 'Que faire ?'));
                var issues = el('ul', 'simulation__issues');
                imp.issues.forEach(function (t) { issues.appendChild(el('li', null, t)); });
                racine.appendChild(issues);
            }
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
