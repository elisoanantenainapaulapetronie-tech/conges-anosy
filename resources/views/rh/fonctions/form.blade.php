@extends('layouts.app')
@php $existe = isset($fonction->id); @endphp
@section('titre', $existe ? 'Modifier une fonction' : 'Nouvelle fonction')
@section('contenu')
    <header class="entete">
        <h1>{{ $existe ? 'Modifier la fonction' : 'Nouvelle fonction' }}</h1>
        <p class="poste">Rattachez la fonction à sa direction ou son service habituel et à son autorité : ces choix sont proposés à la saisie des fiches.</p>
    </header>

    @include('rh._onglets', ['actif' => 'fonctions'])

    <form method="POST" data-envoi action="{{ $existe ? route('rh.fonctions.update', $fonction->id) : route('rh.fonctions.store') }}">
        @csrf
        @if ($existe) @method('PUT') @endif
        <section class="panneau">
            <div class="grille-form">
                <x-rh-champ nom="libelle" label="Nom de la fonction" :requis="true" :valeur="$fonction->libelle ?? null" classe="grille-form__large"
                            aide="Ex. Chef de service, Assistant(e), Expert, Consultant, Chauffeur…" />
                <x-rh-liste nom="categorie" label="Catégorie" :requis="true" :valeur="$fonction->categorie ?? 'autre'" vide="— Choisir —" :options="$categories" />
                <x-rh-liste nom="structure_id" label="Direction ou service habituel" :valeur="$fonction->structure_id ?? null" vide="— Aucun (toute la Région) —"
                            :options="$structures" aide="Facultatif. Proposé comme service de l’agent quand la fiche n’en a pas encore." />
                <x-rh-liste nom="rattachement" label="Autorité décisionnaire habituelle" :valeur="$fonction->rattachement ?? null" vide="— Selon le service —"
                            :options="\App\Services\CircuitRegles::CIRCUITS"
                            aide="Facultatif. « Selon le service » : l’autorité du service choisi dans la fiche s’applique." />
            </div>
        </section>
        <div class="actions-formulaire">
            <button type="submit" class="bouton bouton--ligne">{{ $existe ? 'Enregistrer' : 'Ajouter la fonction' }}</button>
            <a class="bouton bouton--ligne bouton--discret" href="{{ route('rh.fonctions.index') }}">Annuler</a>
        </div>
    </form>
@endsection
