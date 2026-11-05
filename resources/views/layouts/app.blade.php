<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') — Congés Anosy</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600;8..60,700&display=swap">
    <link rel="stylesheet" href="{{ asset('css/anosy.css') }}">
</head>
<body>
@php
    $moi = auth()->user()->agent;
    $initiales = mb_strtoupper(mb_substr($moi->prenoms ?: $moi->nom, 0, 1).mb_substr($moi->nom, 0, 1));
@endphp
<a class="saut" href="#contenu">Aller au contenu</a>

<div class="coque">
    <aside class="menu" id="menu">
        <div class="menu__marque">
            <span class="plaque"><img src="{{ asset('images/logo-anosy.png') }}" alt="Armoiries de la Région Anosy"></span>
            <div><strong>Congés Anosy</strong><span>Région Anosy</span></div>
        </div>

        {{-- Menu : ajouter ici une entrée par espace au fur et à mesure du développement --}}
        <nav aria-label="Navigation principale">
            <ul>
                <li>
                    <a href="{{ route('accueil') }}" @if (request()->routeIs('accueil')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></svg>
                        Accueil
                    </a>
                </li>
                @if ($moi->aLeRole('rh_gestionnaire', 'rh_responsable'))
                    <li>
                        <a href="{{ route('rh') }}" @if (request()->routeIs('rh')) aria-current="page" @endif>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            Espace RH
                        </a>
                    </li>
                @endif
            </ul>
        </nav>

        <p class="menu__pied">Déconnexion automatique après {{ config('session.lifetime') }} minutes d’inactivité.</p>
    </aside>
    <div class="rideau" id="rideau"></div>

    <div class="coque__corps">
        <header class="barre">
            <button type="button" class="barre__menu" aria-controls="menu" aria-expanded="false">Menu</button>
            <div class="barre__utilisateur">
                <div class="barre__nom">
                    <strong>{{ $moi->nom_complet }}</strong>
                    <span>{{ $moi->fonction?->libelle ?? $moi->identifiant }}</span>
                </div>
                <span class="avatar" aria-hidden="true">{{ $initiales }}</span>
                <form method="POST" action="{{ route('deconnexion') }}">
                    @csrf
                    <button type="submit" class="bouton bouton--discret">Se déconnecter</button>
                </form>
            </div>
        </header>

        <main id="contenu" class="contenu">
            @if (session('statut')) <div class="alerte alerte--ok" role="status">{{ session('statut') }}</div> @endif
            @yield('contenu')
        </main>
    </div>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
