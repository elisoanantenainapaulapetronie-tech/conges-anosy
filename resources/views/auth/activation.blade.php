@extends('layouts.auth')
@section('titre', 'Activation du compte')
@section('photo', 'fort-dauphin-promontoire.jpg')
@section('lieu', 'Fort-Dauphin, Anosy')
@section('accroche', 'Bienvenue : activez votre compte en une minute')
@section('accroche_sous', 'La RH vous a remis un identifiant et un code. Choisissez maintenant un mot de passe que vous seul connaissez.')
@section('contenu')
    <h1 class="auth__titre">Activer mon compte</h1>
    <p class="auth__intro">Saisissez le code remis par la RH, puis choisissez votre mot de passe.</p>

    <form method="POST" action="{{ route('activation') }}" data-envoi novalidate>
        @csrf
        <x-champ nom="identifiant" label="Identifiant" autocomplete="username" autofocus
                 aide="Votre matricule ou l’identifiant remis par la RH." />
        <x-champ nom="code" label="Code d’activation" classe="champ--code" aide="Valable quelques jours après sa remise." />
        <x-champ-mdp nom="mot_de_passe" label="Nouveau mot de passe" autocomplete="new-password" />
        <x-champ-mdp nom="mot_de_passe_confirmation" label="Confirmer le mot de passe" autocomplete="new-password" />
        <x-regles-mdp />

        <button type="submit" class="bouton">Activer mon compte</button>
    </form>

    <div class="liens"><a href="{{ route('connexion') }}">Retour à la connexion</a></div>
@endsection
