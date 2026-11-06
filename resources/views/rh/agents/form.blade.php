@extends('layouts.app')
@php $existe = $agent->exists; @endphp
@section('titre', $existe ? 'Modifier '.$agent->nom_complet : 'Nouvel agent')
@section('contenu')
    <header class="entete">
        <h1>{{ $existe ? 'Modifier la fiche' : 'Nouvel agent' }}</h1>
        <p class="poste">{{ $existe ? $agent->nom_complet.' · '.$agent->identifiant : 'La fiche est créée d’abord ; le compte de connexion se crée ensuite, depuis la fiche.' }}</p>
    </header>

    @include('rh._onglets', ['actif' => 'agents'])

    @if ($errors->any())
        <div class="alerte alerte--erreur" role="alert">Le formulaire contient {{ $errors->count() }} erreur(s) : corrigez les champs signalés.</div>
    @endif

    <form method="POST" data-envoi
          action="{{ $existe ? route('rh.agents.update', $agent) : route('rh.agents.store') }}">
        @csrf
        @if ($existe) @method('PUT') @endif

        <section class="panneau" aria-labelledby="f-identite">
            <h2 id="f-identite">Identité</h2>
            <div class="grille-form">
                <x-rh-champ nom="nom" label="Nom" :valeur="$agent->nom" :requis="true" autocomplete="off" />
                <x-rh-champ nom="prenoms" label="Prénoms" :valeur="$agent->prenoms" />
                <x-rh-liste nom="sexe" label="Sexe" :requis="true" :valeur="$agent->sexe" vide="— Choisir —"
                            :options="['M' => 'Masculin', 'F' => 'Féminin']" aide="Utile pour les congés de maternité et de paternité." />
                <x-rh-champ nom="date_naissance" label="Date de naissance" type="date" :valeur="$agent->date_naissance?->format('Y-m-d')" />
                <x-rh-champ nom="telephone" label="Téléphone" type="tel" :valeur="$agent->telephone" aide="Reçoit les SMS importants si l’agent n’a pas d’e-mail." />
                <x-rh-champ nom="email" label="Adresse e-mail" type="email" :valeur="$agent->email" />
            </div>
        </section>

        <section class="panneau" aria-labelledby="f-poste">
            <h2 id="f-poste">Poste et circuit de décision</h2>
            <div class="grille-form">
                <x-rh-champ nom="matricule" label="Matricule" :valeur="$agent->matricule"
                            aide="Facultatif. Sans matricule, un identifiant ANO-xxxxx est généré pour la connexion." />
                <x-rh-liste nom="fonction_id" label="Fonction" :valeur="$agent->fonction_id" vide="— Aucune —" :options="$liste['fonctions']"
                            aide="La fonction est un simple libellé : elle ne donne aucun droit." />
                <x-rh-champ nom="grade_categorie" label="Grade ou catégorie" :valeur="$agent->grade_categorie" />
                <x-rh-liste nom="structure_id" label="Service ou direction" :requis="true" :valeur="$agent->structure_id" vide="— Choisir —"
                            :options="$liste['structures']" :donnees="$liste['rattachements']" data-synchro="rattachement" data-suggerer="responsable_id" />
                <x-rh-liste nom="responsable_id" label="Responsable qui donne l’avis" :valeur="$agent->responsable_id" vide="— Aucun —"
                            :options="$liste['responsables']" :data-suggestions="json_encode($liste['suggestions'])"
                            aide="Un seul : en principe le Directeur de rattachement ; un chef de service seulement si vous le désignez. Pour un directeur : le Secrétaire Général. Il reçoit directement la demande d’avis de l’agent." />
                <x-rh-liste nom="rattachement" label="Autorité décisionnaire" :requis="true" :valeur="$agent->rattachement"
                            :options="['sg' => 'Secrétaire Général', 'region' => 'Chef de Région']"
                            aide="Désignée à l’avance par la RH ; l’agent ne peut pas la modifier. La demande lui est transmise après l’avis et le contrôle RH. Proposée d’après le service ; pour un directeur : Chef de Région." />
                <x-rh-champ nom="date_entree" label="Date d’entrée" type="date" :requis="true" :valeur="$agent->date_entree?->format('Y-m-d')" />
            </div>
        </section>

        @if ($existe)
            <section class="panneau" aria-labelledby="f-situation">
                <h2 id="f-situation">Situation</h2>
                <div class="grille-form">
                    <x-rh-liste nom="situation" label="Situation" :requis="true" :valeur="$agent->situation"
                                :options="['actif' => 'Actif', 'suspendu' => 'Suspendu', 'sorti' => 'Sorti']"
                                aide="Suspendu ou sorti : connexion impossible et rôles retirés. La fiche n’est jamais supprimée." />
                    <x-rh-champ nom="date_sortie" label="Date de sortie" type="date" :valeur="$agent->date_sortie?->format('Y-m-d')" aide="Obligatoire si la situation est « Sorti »." />
                    <x-rh-champ nom="motif_affectation" label="Motif du changement de poste" aide="Facultatif : inscrit dans l’historique si le service, le responsable ou le circuit change." classe="grille-form__large" />
                </div>
            </section>
        @endif

        <div class="actions-formulaire">
            <button type="submit" class="bouton bouton--ligne">{{ $existe ? 'Enregistrer' : 'Créer la fiche' }}</button>
            <a class="bouton bouton--ligne bouton--discret" href="{{ $existe ? route('rh.agents.show', $agent) : route('rh.agents.index') }}">Annuler</a>
        </div>
    </form>
@endsection
