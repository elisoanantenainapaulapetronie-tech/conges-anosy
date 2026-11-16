@extends('layouts.app')
@section('titre', 'Historique des corrections')
@section('contenu')
    <header class="entete">
        <h1>Historique des corrections de solde</h1>
        <p class="poste">Toutes les propositions déjà traitées : validées, rejetées ou retirées, avec la personne qui a proposé, celle qui a contrôlé, son observation et le justificatif. Rien n’est supprimé ni modifié ici : c’est une consultation.</p>
    </header>

    @if ($estRh) @include('rh._onglets', ['actif' => 'historique']) @endif
    <link rel="stylesheet" href="{{ asset('css/rh.css') }}">
    <link rel="stylesheet" href="{{ asset('css/solde.css') }}">

    <section class="panneau" aria-label="Filtres">
        <form method="GET" action="{{ route('rh.historique-corrections') }}" class="grille-form">
            <div class="champ">
                <label for="f-agent">Agent</label>
                <input id="f-agent" name="agent" type="search" value="{{ $filtres['agent'] }}" placeholder="Nom, prénom ou identifiant">
            </div>
            <div class="champ">
                <label for="f-statut">Décision</label>
                <select id="f-statut" name="statut">
                    <option value="">Toutes</option>
                    <option value="valide" @selected($filtres['statut'] === 'valide')>Validées</option>
                    <option value="rejete" @selected($filtres['statut'] === 'rejete')>Rejetées</option>
                    <option value="retiree" @selected($filtres['statut'] === 'retiree')>Retirées</option>
                </select>
            </div>
            <div class="champ">
                <label for="f-nature">Type</label>
                <select id="f-nature" name="nature">
                    <option value="">Tous</option>
                    @foreach ($natures as $cle => $libelle) <option value="{{ $cle }}" @selected($filtres['nature'] === $cle)>{{ $libelle }}</option> @endforeach
                </select>
            </div>
            <div class="champ">
                <label for="f-annee">Année d’acquisition</label>
                <select id="f-annee" name="annee">
                    <option value="">Toutes</option>
                    @foreach ($annees as $a) <option value="{{ $a }}" @selected((string) $filtres['annee'] === (string) $a)>{{ $a }}</option> @endforeach
                </select>
            </div>
            <div class="champ"><button type="submit" class="bouton bouton--ligne">Filtrer</button> <a href="{{ route('rh.historique-corrections') }}">Réinitialiser</a></div>
        </form>
    </section>

    <section class="panneau" aria-labelledby="t-liste">
        <h2 id="t-liste">{{ $liste->total() }} proposition(s) traitée(s)</h2>
        <ul class="liste-simple">
            @forelse ($liste as $p)
                <li class="historique__agent">
                    <a href="{{ route('rh.soldes.show', ['agent' => $p->agent_id, 'annee' => $p->annee]) }}">{{ trim($p->cible_prenoms.' '.mb_strtoupper($p->cible_nom)) }}</a>
                    <span class="discret">({{ $p->cible_identifiant }})</span>
                    <ul class="liste-simple">
                        @include('rh.soldes._ligne-historique', ['p' => $p, 'liste' => $pieces[$p->id] ?? []])
                    </ul>
                </li>
            @empty
                <li class="discret">Aucune proposition traitée ne correspond à ces filtres.</li>
            @endforelse
        </ul>
    </section>

    {{ $liste->links() }}
@endsection
