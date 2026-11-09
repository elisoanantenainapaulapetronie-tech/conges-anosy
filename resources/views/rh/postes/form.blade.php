@extends('layouts.app')
@php $existe = isset($poste->id); @endphp
@section('titre', $existe ? 'Modifier un poste' : 'Nouveau poste')
@section('contenu')
    <header class="entete">
        <h1>{{ $existe ? 'Modifier le poste' : 'Nouveau poste' }}</h1>
        <p class="poste">Un poste sans titulaire apparaît comme « vacant » dans l’organigramme. Il ne donne aucun droit : les rôles se gèrent dans la fiche de l’agent.</p>
    </header>

    @include('rh._onglets', ['actif' => 'organigramme'])

    <form method="POST" data-envoi
          action="{{ $existe ? route('rh.postes.update', $poste->id) : route('rh.postes.store') }}">
        @csrf
        @if ($existe) @method('PUT') @endif

        <section class="panneau">
            <div class="grille-form">
                <x-rh-liste nom="structure_id" label="Structure" :requis="true" :valeur="$poste->structure_id ?? null"
                            vide="— Choisir —" :options="$structures" classe="grille-form__large"
                            aide="Direction, service ou cabinet où se trouve le poste." />
                <x-rh-champ nom="intitule" label="Intitulé du poste" :requis="true" :valeur="$poste->intitule ?? null" classe="grille-form__large"
                            aide="Ex. Directeur de cabinet, Chef de protocole, Gestionnaire de paie." />
                <x-rh-liste nom="fonction_id" label="Fonction correspondante" :valeur="$poste->fonction_id ?? null"
                            vide="— Aucune —" :options="$fonctions"
                            aide="Facultatif. La fonction est un simple libellé, elle ne donne aucun droit." />
                <x-rh-liste nom="titulaire_id" label="Titulaire" :valeur="$poste->titulaire_id ?? null"
                            vide="— Poste vacant —" :options="$agents"
                            aide="Laissez vide si personne n’occupe encore ce poste." />
                <x-rh-champ nom="note" label="Note" :valeur="$poste->note ?? null" classe="grille-form__large"
                            aide="Facultatif : référence de la nomination attendue, remarque…" />
            </div>
        </section>

        <div class="actions-formulaire">
            <button type="submit" class="bouton bouton--ligne">{{ $existe ? 'Enregistrer' : 'Ajouter le poste' }}</button>
            <a class="bouton bouton--ligne bouton--discret" href="{{ route('organigramme') }}">Annuler</a>
        </div>
    </form>
@endsection
