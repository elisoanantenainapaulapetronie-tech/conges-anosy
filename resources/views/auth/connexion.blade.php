@extends('layouts.auth')
@section('titre', 'Connexion')
@section('contenu')
    <h1 class="auth__titre">Connexion</h1>
    <p class="auth__intro">Accédez à vos demandes et à votre solde de congé.</p>

    @if (request()->boolean('expiree'))
        <div class="alerte alerte--info" role="status">Votre session a pris fin après {{ (int) config('conges.inactivite_minutes', 30) }} minutes d’inactivité. Reconnectez-vous pour continuer.</div>
    @endif
    @if (session('statut')) <div class="alerte alerte--ok" role="status">{{ session('statut') }}</div> @endif

    <form method="POST" action="{{ route('connexion') }}" data-envoi novalidate>
        @csrf
        <x-champ nom="identifiant" label="Identifiant" autocomplete="username" autofocus
                 aide="Votre matricule ou, si vous n’en avez pas, l’identifiant remis par la RH." />
        <x-champ-mdp nom="mot_de_passe" label="Mot de passe" autocomplete="current-password" />

        <button type="submit" class="bouton">Se connecter</button>
    </form>

    <div class="liens">
        <a href="{{ route('mot-de-passe-oublie') }}">Mot de passe oublié</a>
        <a href="{{ route('activation') }}">Première connexion : activer mon compte</a>
    </div>
@endsection
