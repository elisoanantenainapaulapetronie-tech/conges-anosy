@extends('layouts.app')
@section('titre', 'Mon profil')
@section('contenu')
    <header class="entete entete--profil">
        <x-avatar :agent="$agent" taille="lg" alt="Ma photo de profil" />
        <div>
            <h1>{{ $agent->nom_complet }}</h1>
            <p class="poste">{{ $agent->fonction?->libelle ?? 'Sans fonction' }} · {{ $agent->structure?->nom }}</p>
        </div>
    </header>

    <section class="panneau" aria-labelledby="p-photo">
        <h2 id="p-photo">Ma photo de profil</h2>
        @if ($modifiable)
            <p class="panneau__note">Elle apparaît en haut de chaque page et dans les listes vues par la RH, votre responsable et l’autorité qui décide de vos demandes.</p>
            @include('partials.photo-formulaire', [
                'agent' => $agent,
                'action' => route('profil.photo.enregistrer'),
                'supprimer' => route('profil.photo.supprimer'),
            ])
        @else
            <p class="panneau__note" style="margin:0">La photo de profil est gérée par la RH. Pour la changer, adressez-vous à votre service des ressources humaines.</p>
        @endif
    </section>

    <section class="panneau" aria-labelledby="p-infos">
        <h2 id="p-infos">Mes informations</h2>
        <p class="panneau__note">Ces informations sont tenues par la RH : pour toute correction, adressez-vous à elle.</p>
        <dl class="fiche">
            <dt>Identifiant</dt><dd>{{ $agent->identifiant }}</dd>
            <dt>Fonction</dt><dd>{{ $agent->fonction?->libelle ?? '—' }}</dd>
            <dt>Service</dt><dd>{{ $agent->structure?->nom ?? '—' }}</dd>
            <dt>Responsable qui donne l’avis</dt><dd>{{ $agent->responsable?->nom_complet ?? '—' }}</dd>
            <dt>Autorité décisionnaire</dt><dd>{{ \App\Services\CircuitRegles::libelleCourt($agent->rattachement) }}</dd>
            <dt>Rôles</dt>
            <dd>
                Agent
                @foreach ($roles as $r)
                    · {{ $r->libelle }}@if ($r->structure) ({{ $r->structure }})@endif
                @endforeach
            </dd>
        </dl>
    </section>
@endsection
