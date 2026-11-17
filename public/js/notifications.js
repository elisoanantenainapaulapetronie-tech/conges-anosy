/* Cloche en direct (correctif 5 sexies) : met à jour la pastille et, sur la page « Mes notifications », la liste,
   sans actualiser la page. Interroge /notifications/compteur toutes les 15 s tant que l'onglet est visible. */
(function () {
    'use strict';
    var cloche = document.querySelector('.cloche[data-compteur-url]');
    if (!cloche) { return; }
    var url = cloche.getAttribute('data-compteur-url');
    var derniere = null;
    var enCours = false;

    function afficher(n) {
        var pastille = cloche.querySelector('.cloche__compteur');
        if (n > 0) {
            if (!pastille) {
                pastille = document.createElement('span');
                pastille.className = 'cloche__compteur';
                cloche.appendChild(pastille);
            }
            pastille.textContent = n > 99 ? '99+' : String(n);
        } else if (pastille) {
            pastille.remove();
        }
        cloche.setAttribute('aria-label', 'Mes notifications' + (n > 0 ? ' : ' + n + ' non lue' + (n > 1 ? 's' : '') : ''));
        cloche.setAttribute('data-non-lues', String(n));
    }

    function rafraichirListe() {
        var zone = document.getElementById('zone-notifications');
        if (!zone) { return; }
        fetch(window.location.href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var neuve = doc.getElementById('zone-notifications');
                if (neuve) { zone.innerHTML = neuve.innerHTML; }
            })
            .catch(function () {});
    }

    function interroger() {
        if (enCours || document.hidden) { return; }
        enCours = true;
        fetch(url, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                enCours = false;
                if (!d) { return; }
                var avant = parseInt(cloche.getAttribute('data-non-lues') || '0', 10);
                afficher(d.non_lues);
                if (derniere !== null && (d.derniere !== derniere || d.non_lues !== avant)) { rafraichirListe(); }
                derniere = d.derniere;
            })
            .catch(function () { enCours = false; });
    }

    interroger();
    setInterval(interroger, 15000);
    document.addEventListener('visibilitychange', interroger);
    /* Retour arrière du navigateur (page restaurée du cache) : on remet la pastille à jour. */
    window.addEventListener('pageshow', function (e) { if (e.persisted) { interroger(); } });
})();
