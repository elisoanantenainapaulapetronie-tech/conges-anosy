@extends('layouts.app')
@section('titre', 'Demande')
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>{{ $demande->reference ?? 'Brouillon n° '.$demande->id }}</h1>
            <p class="poste">{{ $demande->typeDemande->libelle }} · {{ ucfirst(str_replace('_', ' ', $demande->statut)) }}</p>
        </div>
        @can('modifier', $demande)
            <a href="{{ route('demandes.edit', $demande) }}" class="bouton bouton--ligne">Modifier</a>
        @endcan
    </header>

    @error('soumission')
        <div class="alerte" role="alert">{{ $message }}</div>
    @enderror

    <section class="panneau" aria-labelledby="t-detail">
        <h2 id="t-detail">Détail</h2>
        <p><strong>Période :</strong> du {{ $demande->date_debut->format('d/m/Y') }} au {{ $demande->date_fin->format('d/m/Y') }}</p>
        <p><strong>Durée :</strong> {{ rtrim(rtrim(number_format((float) $demande->duree_jours, 1, ',', ''), '0'), ',') }} jour(s) ouvré(s)</p>
        @if ($demande->motif) <p><strong>Motif :</strong> {{ $demande->motif }}</p> @endif
        @if ($demande->contact_pendant_absence) <p><strong>Contact :</strong> {{ $demande->contact_pendant_absence }}</p> @endif
        @if ($demande->urgence) <p><strong>Demande urgente</strong></p> @endif
    </section>

    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:16px;">
        <a href="{{ route('demandes.index') }}">← Retour à mes demandes</a>

        @can('soumettre', $demande)
            <form method="POST" action="{{ route('demandes.soumettre', $demande) }}">
                @csrf
                <button type="submit" class="bouton bouton--ligne">Envoyer la demande</button>
            </form>
        @endcan
    </div>
@endsection