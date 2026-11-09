<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titre') — Congés Anosy</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    @include('partials.polices')
    <link rel="stylesheet" href="{{ asset('css/anosy.css') }}">
    <link rel="stylesheet" href="{{ asset('css/identite.css') }}">
    {{-- Vue de Fort-Dauphin du bandeau d'en-tête : déclarée ici, une seule fois, pour tous les espaces --}}
    <style>:root{--img-region:url('{{ asset('images/fort-dauphin-bandeau.jpg') }}')}</style>
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
                <li>
                    <a href="{{ route('organigramme') }}" @if (request()->routeIs('organigramme')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="3" width="6" height="4" rx="1"/><rect x="2" y="17" width="6" height="4" rx="1"/><rect x="16" y="17" width="6" height="4" rx="1"/><path d="M12 7v5M5 17v-5h14v5"/></svg>
                        Organigramme
                    </a>
                </li>
                <li>
                    <a href="{{ route('solde') }}" @if (request()->routeIs('solde')) aria-current="page" @endif>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 15h3"/></svg>
                        Mon solde
                    </a>
                </li>
                @if ($moi->aLeRole('rh_gestionnaire', 'rh_responsable'))
                    <li>
                        <a href="{{ route('rh') }}" @if (request()->routeIs('rh', 'rh.*')) aria-current="page" @endif>
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
                <a href="{{ route('profil') }}" class="barre__profil" title="Mon profil" aria-label="Mon profil">
                    <x-avatar :agent="$moi" />
                </a>
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
@include('partials.session')
</body>
</html>
