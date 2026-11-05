@extends('layouts.auth')
@section('titre', 'Mot de passe oublié')
@section('photo', 'siege-region.jpg')
@section('lieu', 'Siège de la Région Anosy')
@section('accroche', 'Pas de panique, un nouveau mot de passe est vite choisi')
@section('accroche_sous', 'Nous envoyons un lien personnel à l’adresse e-mail ou au numéro de téléphone enregistrés par la RH.')
@section('contenu')
    <h1 class="auth__titre">Mot de passe oublié</h1>
    <p class="auth__intro">Saisissez votre identifiant. Si votre compte est actif, vous recevrez un lien valable 60 minutes.</p>

    <form method="POST" action="{{ route('mot-de-passe-oublie') }}" data-envoi novalidate>
        @csrf
        <x-champ nom="identifiant" label="Identifiant" autocomplete="username" autofocus
                 aide="Votre matricule ou l’identifiant remis par la RH." />
        <button type="submit" class="bouton">Envoyer le lien</button>
    </form>

    <div class="liens"><a href="{{ route('connexion') }}">Retour à la connexion</a></div>
@endsection
