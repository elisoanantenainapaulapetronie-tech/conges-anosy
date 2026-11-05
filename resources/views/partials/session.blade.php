{{-- Avertissement avant la déconnexion automatique. À placer dans layouts/app.blade.php, juste avant </body>. --}}
<div id="session-alerte" class="session-alerte" role="alertdialog" aria-live="assertive" aria-labelledby="session-titre" hidden>
    <p id="session-titre"><strong>Votre session va se terminer.</strong>
        Vous serez déconnecté dans <span id="session-secondes">120</span> secondes.</p>
    <button type="button" class="bouton bouton--discret" id="session-prolonger">Rester connecté</button>
</div>
<script src="{{ asset('js/session.js') }}"
        data-duree="{{ (int) config('session.lifetime') }}"
        data-avertir="2"
        data-ping="{{ route('session.prolonger') }}"
        data-sortie="{{ route('connexion') }}"
        data-jeton="{{ csrf_token() }}" defer></script>
<link rel="stylesheet" href="{{ asset('css/session.css') }}">
