{{-- Navigation de l'espace RH + messages d'erreur. À inclure en tête de chaque page RH. --}}
<link rel="stylesheet" href="{{ asset('css/rh.css') }}">
<script src="{{ asset('js/rh.js') }}" defer></script>

<nav aria-label="Espace RH">
    <ul class="rh-onglets">
        <li><a href="{{ route('rh') }}" @if (($actif ?? '') === 'tableau') aria-current="page" @endif>Tableau de bord</a></li>
        <li><a href="{{ route('rh.agents.index') }}" @if (($actif ?? '') === 'agents') aria-current="page" @endif>Agents</a></li>
        <li><a href="{{ route('rh.structures.index') }}" @if (($actif ?? '') === 'structures') aria-current="page" @endif>Organisation</a></li>
    </ul>
</nav>

@if (session('erreur')) <div class="alerte alerte--erreur" role="alert">{{ session('erreur') }}</div> @endif
