@extends('layouts.app')
@section('titre', 'Mes demandes')
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>Mes demandes</h1>
            <p class="poste">Déposez une demande, suivez son avancement et retrouvez vos brouillons.</p>
        </div>
        @can('creer', App\Models\Demande::class)
            <a href="{{ route('demandes.create') }}" class="bouton bouton--ligne">Nouvelle demande</a>
        @endcan
    </header>

    <section class="panneau" aria-labelledby="t-liste">
        <h2 id="t-liste">Historique de mes demandes</h2>

        @forelse ($demandes as $d)
            <p>
                <a href="{{ route('demandes.show', $d) }}"><strong>{{ $d->reference ?? 'Brouillon n° '.$d->id }}</strong></a>
                · {{ $d->typeDemande->libelle }}
                · du {{ $d->date_debut->format('d/m/Y') }} au {{ $d->date_fin->format('d/m/Y') }}
                ({{ rtrim(rtrim(number_format((float) $d->duree_jours, 1, ',', ''), '0'), ',') }} j)
                · {{ ucfirst(str_replace('_', ' ', $d->statut)) }}
            </p>
        @empty
            <p class="panneau__note">Vous n’avez encore déposé aucune demande.</p>
        @endforelse
    </section>
@endsection