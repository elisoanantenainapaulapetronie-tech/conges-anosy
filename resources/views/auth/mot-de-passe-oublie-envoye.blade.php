@extends('layouts.auth')
@section('titre', 'Demande enregistrée')
@section('photo', 'siege-region.jpg')
@section('lieu', 'Siège de la Région Anosy')
@section('accroche', 'Pas de panique, un nouveau mot de passe est vite choisi')
@section('accroche_sous', 'Nous envoyons un lien personnel à l’adresse e-mail ou au numéro de téléphone enregistrés par la RH.')
@section('contenu')
    <div class="etat" aria-hidden="true">✓</div>
    <h1 class="auth__titre">Demande enregistrée</h1>
    <p class="auth__intro">Si un compte actif correspond à cet identifiant, un lien vient d’être envoyé par e-mail ou par SMS. Il est valable 60 minutes et ne peut servir qu’une fois.</p>
    <p class="auth__intro">Rien reçu ? Regardez dans vos courriers indésirables. Si aucun e-mail ni téléphone n’est enregistré pour vous, la RH peut vous aider.</p>

    <a class="bouton" href="{{ route('connexion') }}">Retour à la connexion</a>
@endsection
