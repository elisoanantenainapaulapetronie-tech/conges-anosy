@extends('layouts.app')
@section('titre', 'Accueil')
@php
    $libellesRoles = [
        'responsable_hierarchique' => 'Responsable hiérarchique',
        'secretaire_general' => 'Secrétaire Général',
        'chef_region' => 'Chef de Région',
        'rh_gestionnaire' => 'RH gestionnaire',
        'rh_responsable' => 'RH responsable',
        'admin_technique' => 'Administrateur technique',
    ];
@endphp
@section('contenu')
    <header class="entete">
        <p class="entete__date">{{ now()->translatedFormat('l j F Y') }}</p>
        <h1>Bonjour {{ $agent->prenoms ?: $agent->nom }}</h1>
        <p class="poste">{{ $agent->fonction?->libelle }}@if ($agent->fonction && $agent->structure), @endif{{ $agent->structure?->nom }}</p>
    </header>

    <section class="panneau" aria-labelledby="titre-solde">
        <h2 id="titre-solde">Mon solde de congé {{ $solde['annee'] }}</h2>

        @if ($solde['existe'])
            <div class="solde">
                <div>
                    <span class="solde__libelle">Solde disponible</span>
                    <span class="solde__chiffre">{{ $solde['disponible'] }}<small>jours</small></span>
                </div>
                <div>
                    <span class="solde__libelle">Après les demandes en attente</span>
                    <span class="solde__chiffre">{{ $solde['previsionnel'] }}<small>jours</small></span>
                </div>
            </div>

            <div class="barre-solde" role="img"
                 aria-label="Répartition de vos jours : {{ $solde['consommes'] }} pris, {{ $solde['reserves'] }} réservés, {{ $solde['attente'] }} en attente, {{ $solde['previsionnel'] }} encore libres">
                <span class="b-cons" style="width:{{ $solde['barre']['consommes'] }}%"></span>
                <span class="b-res" style="width:{{ $solde['barre']['reserves'] }}%"></span>
                <span class="b-att" style="width:{{ $solde['barre']['attente'] }}%"></span>
                <span class="b-prev" style="width:{{ $solde['barre']['previsionnel'] }}%"></span>
            </div>

            <ul class="legende">
                <li><i class="b-cons"></i>Jours pris <b>{{ $solde['consommes'] }}</b></li>
                <li><i class="b-res"></i>Jours réservés <b>{{ $solde['reserves'] }}</b></li>
                <li><i class="b-att"></i>En attente de décision <b>{{ $solde['attente'] }}</b></li>
                <li><i class="b-prev"></i>Encore libres <b>{{ $solde['previsionnel'] }}</b></li>
            </ul>
        @else
            <p class="vide">Aucun solde n’est enregistré pour {{ $solde['annee'] }}. La RH saisit le solde initial de chaque agent.</p>
        @endif
    </section>

    <section class="panneau" aria-labelledby="titre-fiche">
        <h2 id="titre-fiche">Ma fiche</h2>
        <dl class="fiche">
            <dt>Identifiant</dt><dd>{{ $agent->identifiant }}</dd>
            @if ($agent->matricule && $agent->matricule !== $agent->identifiant)
                <dt>Matricule</dt><dd>{{ $agent->matricule }}</dd>
            @endif
            <dt>Fonction</dt><dd>{{ $agent->fonction?->libelle ?? '—' }}</dd>
            <dt>Service</dt><dd>{{ $agent->structure?->nom ?? '—' }}</dd>
            <dt>Responsable direct</dt><dd>{{ $agent->responsable?->nom_complet ?? '—' }}</dd>
            <dt>Circuit de décision</dt><dd>{{ $agent->rattachement === 'region' ? 'Chef de Région' : 'Secrétaire Général' }}</dd>
            <dt>Mes rôles</dt>
            <dd>
                <span class="etiquette">Agent</span>
                @foreach ($roles as $code)
                    <span class="etiquette">{{ $libellesRoles[$code] ?? ucfirst(str_replace('_', ' ', $code)) }}</span>
                @endforeach
            </dd>
        </dl>
        <p class="panneau__note" style="margin:16px 0 0">Une erreur dans votre fiche ? Signalez-la à la RH : vous ne pouvez pas la modifier vous-même.</p>
    </section>
@endsection
