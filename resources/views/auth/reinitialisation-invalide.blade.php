@extends('layouts.auth')
@section('titre', 'Lien invalide')
@section('photo', 'siege-region.jpg')
@section('lieu', 'Siège de la Région Anosy')
@section('accroche', 'Ce lien ne fonctionne plus, mais la solution est simple')
@section('accroche_sous', 'Demandez un nouveau lien : il remplacera tous les précédents.')
@section('contenu')
    <div class="etat etat--alerte" aria-hidden="true">!</div>
    <h1 class="auth__titre">Lien invalide ou expiré</h1>
    <p class="auth__intro">Ce lien a déjà servi, a expiré après 60 minutes, ou a été remplacé par un lien plus récent.</p>

    <a class="bouton" href="{{ route('mot-de-passe-oublie') }}">Demander un nouveau lien</a>
    <div class="liens"><a href="{{ route('connexion') }}">Retour à la connexion</a></div>
@endsection
