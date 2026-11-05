@extends('layouts.auth')
@section('titre', 'Nouveau mot de passe')
@section('photo', 'fort-dauphin-promontoire.jpg')
@section('lieu', 'Fort-Dauphin, Anosy')
@section('accroche', 'Choisissez un mot de passe que vous seul connaissez')
@section('accroche_sous', 'Ne le communiquez à personne, pas même à la RH : elle ne vous le demandera jamais.')
@section('contenu')
    <h1 class="auth__titre">Nouveau mot de passe</h1>
    <p class="auth__intro">Compte concerné : <strong>{{ $agent->identifiant }}</strong></p>

    <form method="POST" action="{{ route('reinitialisation.appliquer', ['jeton' => $jeton]) }}" data-envoi novalidate>
        @csrf
        <x-champ-mdp nom="mot_de_passe" label="Nouveau mot de passe" autocomplete="new-password" autofocus />
        <x-champ-mdp nom="mot_de_passe_confirmation" label="Confirmer le mot de passe" autocomplete="new-password" />
        <x-regles-mdp />

        <button type="submit" class="bouton">Enregistrer le mot de passe</button>
    </form>
@endsection
