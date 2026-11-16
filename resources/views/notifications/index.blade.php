@extends('layouts.app')
@section('titre', 'Mes notifications')
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>Mes notifications</h1>
            <p class="poste">{{ $nonLues > 0 ? $nonLues.' non lue'.($nonLues > 1 ? 's' : '') : 'Tout est lu.' }} Les notifications sont automatiques : elles vous préviennent quand une décision ou une action vous concerne.</p>
        </div>
        @if ($nonLues > 0)
            <form method="POST" action="{{ route('notifications.tout-lire') }}">
                @csrf
                <button type="submit" class="bouton bouton--ligne">Tout marquer comme lu</button>
            </form>
        @endif
    </header>

    <nav aria-label="Filtre des notifications">
        <ul class="rh-onglets">
            <li><a href="{{ route('notifications') }}" @if ($filtre === 'toutes') aria-current="page" @endif>Toutes</a></li>
            <li><a href="{{ route('notifications', ['filtre' => 'non_lues']) }}" @if ($filtre === 'non_lues') aria-current="page" @endif>Non lues @if ($nonLues > 0) <span class="pastille pastille--attente">{{ $nonLues }}</span> @endif</a></li>
        </ul>
    </nav>
    <link rel="stylesheet" href="{{ asset('css/rh.css') }}">
    <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">

    <section class="panneau" aria-label="Liste des notifications">
        @forelse ($notifications as $n)
            <article class="notif {{ $n->lue_le ? '' : 'notif--nonlue' }}">
                <div class="notif__corps">
                    <p class="notif__titre">
                        @unless ($n->lue_le) <span class="notif__point" aria-label="Non lue"></span> @endunless
                        <strong>{{ $n->titre }}</strong>
                        <span class="pastille pastille--off">{{ $types[$n->type_evenement] ?? 'Information' }}</span>
                        @if ($n->importance === 'importante') <span class="pastille pastille--alerte">Important</span> @endif
                    </p>
                    @if ($n->message) <p class="notif__message">{{ $n->message }}</p> @endif
                    <p class="notif__date discret">{{ \Carbon\Carbon::parse($n->created_at)->format('d/m/Y à H:i') }}@if ($n->lue_le) · lue le {{ \Carbon\Carbon::parse($n->lue_le)->format('d/m/Y à H:i') }} @endif</p>
                </div>
                <div class="notif__actions">
                    @if ($n->lien) <a class="bouton bouton--ligne" href="{{ route('notifications.ouvrir', $n->id) }}">Ouvrir</a> @endif
                    @unless ($n->lue_le)
                        <form method="POST" action="{{ route('notifications.lue', $n->id) }}">@csrf<button type="submit" class="bouton bouton--discret">Marquer lue</button></form>
                    @endunless
                </div>
            </article>
        @empty
            <p class="panneau__note" style="margin:0">{{ $filtre === 'non_lues' ? 'Aucune notification non lue.' : 'Aucune notification pour le moment.' }}</p>
        @endforelse
    </section>

    {{ $notifications->links() }}
@endsection
