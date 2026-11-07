@extends('layouts.app')
@php $existe = isset($structure->id); @endphp
@section('titre', $existe ? 'Modifier une structure' : 'Nouvelle structure')
@section('contenu')
    <header class="entete">
        <h1>{{ $existe ? 'Modifier la structure' : 'Nouvelle structure' }}</h1>
        <p class="poste">Le circuit de décision de la structure est proposé aux agents qui y sont affectés.</p>
    </header>

    @include('rh._onglets', ['actif' => 'structures'])
    @include('rh.structures._aide')

    <form method="POST" data-envoi
          action="{{ $existe ? route('rh.structures.update', $structure->id) : route('rh.structures.store') }}">
        @csrf
        @if ($existe) @method('PUT') @endif

        <section class="panneau">
            <div class="grille-form">
                <x-rh-champ nom="nom" label="Nom" :requis="true" :valeur="$structure->nom ?? null" classe="grille-form__large" />
                <x-rh-champ nom="code" label="Code" :valeur="$structure->code ?? null" aide="Court et unique (ex. DAG). Facultatif." />
                <x-rh-liste nom="type" label="Type" :requis="true" :valeur="$structure->type ?? null" vide="— Choisir —" :options="$types"
                            libre-champ="type_precision" libre-valeur="autre" libre-label="Précisez le type" :libre-valeur-actuelle="$structure->type_precision ?? null"
                            aide="Nature de la structure. « Autre » : précisez-la (Mission, Projet, Antenne…)." />
                <x-rh-liste nom="parent_id" label="Dépend de" :valeur="$structure->parent_id ?? null" vide="— Rien (niveau le plus haut) —" :options="$parents"
                            libre-champ="parent_autre" libre-option="Autre structure (la saisir)…" libre-label="Nom de la structure dont celle-ci dépend"
                            aide="La structure juste au-dessus dans l’organigramme. Elle détermine le rattachement hiérarchique et le responsable proposé." />
                <x-rh-liste nom="rattachement" label="Circuit de décision" :requis="true" :valeur="$structure->rattachement ?? 'sg'"
                            :options="\App\Services\CircuitRegles::CIRCUITS"
                            aide="Autorité qui décide des demandes des agents de cette structure. Proposé dans leur fiche ; la RH peut le changer agent par agent." />
            </div>
        </section>

        <div class="actions-formulaire">
            <button type="submit" class="bouton bouton--ligne">{{ $existe ? 'Enregistrer' : 'Ajouter la structure' }}</button>
            <a class="bouton bouton--ligne bouton--discret" href="{{ route('rh.structures.index') }}">Annuler</a>
        </div>
    </form>
@endsection
