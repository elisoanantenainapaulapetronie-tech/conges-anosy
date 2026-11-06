@extends('layouts.app')
@section('titre', 'Espace RH')
@section('contenu')
    <header class="entete">
        <h1>Espace RH</h1>
        <p class="poste">Agents, comptes de connexion, rôles et organisation.</p>
    </header>

    @include('rh._onglets', ['actif' => 'tableau'])

    <div class="tuiles">
        <a class="tuile" href="{{ route('rh.agents.index') }}">
            <span class="tuile__chiffre">{{ $chiffres['agents'] }}</span>
            <span class="tuile__libelle">Agents actifs</span>
        </a>
        <a class="tuile" href="{{ route('rh.agents.index', ['compte' => 'actif']) }}">
            <span class="tuile__chiffre">{{ $chiffres['actifs'] }}</span>
            <span class="tuile__libelle">Comptes actifs</span>
        </a>
        <a class="tuile {{ $chiffres['attente'] > 0 ? 'tuile--info' : '' }}" href="{{ route('rh.agents.index', ['compte' => 'en_attente_activation']) }}">
            <span class="tuile__chiffre">{{ $chiffres['attente'] }}</span>
            <span class="tuile__libelle">En attente d’activation</span>
        </a>
        <a class="tuile {{ $chiffres['sans_compte'] > 0 ? 'tuile--alerte' : '' }}" href="{{ route('rh.agents.index', ['compte' => 'sans']) }}">
            <span class="tuile__chiffre">{{ $chiffres['sans_compte'] }}</span>
            <span class="tuile__libelle">Agents sans compte</span>
        </a>
    </div>

    @if ($chiffres['expires'] > 0)
        <div class="alerte alerte--info" role="status">
            {{ $chiffres['expires'] }} code(s) d’activation ont expiré : ouvrez la fiche de l’agent pour en générer un nouveau.
        </div>
    @endif

    @if ($sansResponsable->isNotEmpty() || $responsableSansRole->isNotEmpty())
        <section class="panneau" aria-labelledby="t-circuit">
            <h2 id="t-circuit">Circuit à corriger</h2>
            <p class="panneau__note">Sans responsable valide, la demande d’avis d’un agent n’aurait aucun destinataire.</p>
            <ul class="liste-simple">
                @foreach ($sansResponsable as $a)
                    <li>
                        <span><strong>{{ $a->nom_complet }}</strong> <span class="discret">{{ $a->structure?->nom }}</span> — <span class="pastille pastille--alerte">aucun responsable</span></span>
                        <a href="{{ route('rh.agents.edit', $a) }}">Désigner</a>
                    </li>
                @endforeach
                @foreach ($responsableSansRole as $a)
                    <li>
                        <span><strong>{{ $a->nom_complet }}</strong> <span class="discret">{{ $a->structure?->nom }}</span> — <span class="pastille pastille--attente">{{ $a->responsable?->nom_complet }} ne peut plus donner l’avis</span></span>
                        <a href="{{ route('rh.agents.edit', $a) }}">Changer</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="panneau" aria-labelledby="t-traiter">
        <h2 id="t-traiter">Agents sans compte de connexion</h2>
        @if ($aTraiter->isEmpty())
            <p class="panneau__note" style="margin:0">Tous les agents actifs ont un compte.</p>
        @else
            <ul class="liste-simple">
                @foreach ($aTraiter as $a)
                    <li>
                        <span><strong>{{ $a->nom_complet }}</strong> <span class="discret">{{ $a->identifiant }} · {{ $a->structure?->nom }}</span></span>
                        <a href="{{ route('rh.agents.show', $a) }}">Ouvrir la fiche</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="panneau" aria-labelledby="t-roles">
        <h2 id="t-roles">Rôles en cours</h2>
        <ul class="liste-simple">
            @foreach ($roles as $r)
                <li><span>{{ $r->libelle }}</span><strong>{{ $r->total }}</strong></li>
            @endforeach
        </ul>
        <p class="panneau__note" style="margin:12px 0 0">Tout agent est « Agent » ; les rôles ci-dessus s’y ajoutent.</p>
    </section>
@endsection
