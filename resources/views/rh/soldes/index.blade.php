@extends('layouts.app')
@section('titre', 'Soldes de congé')
@section('contenu')
    @php $nb = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ','); @endphp
    <header class="entete entete--action">
        <div>
            <h1>Soldes de congé</h1>
            <p class="poste">Registre des soldes de tous les agents. Un mouvement manuel est d’abord une proposition, contrôlée par une autre personne.</p>
        </div>
        @if ($peutCrediter)
            <form method="POST" action="{{ route('rh.soldes.crediter') }}"
                  onsubmit="return confirm('Lancer l’acquisition mensuelle jusqu’au mois en cours pour tous les agents actifs ? Les mois déjà crédités sont ignorés.')">
                @csrf
                <input type="hidden" name="annee" value="{{ $annee }}">
                <button type="submit" class="bouton bouton--ligne">Lancer l’acquisition mensuelle</button>
            </form>
        @endif
    </header>

    @include('rh._onglets', ['actif' => 'soldes'])

    <div class="tuiles">
        <a class="tuile" href="{{ route('rh.soldes.index', ['annee' => $annee]) }}">
            <span class="tuile__chiffre">{{ $chiffres['agents'] }}</span><span class="tuile__libelle">Agents actifs</span>
        </a>
        <a class="tuile {{ $chiffres['sans_solde'] > 0 ? 'tuile--alerte' : '' }}" href="{{ route('rh.soldes.index', ['annee' => $annee, 'filtre' => 'sans_solde']) }}">
            <span class="tuile__chiffre">{{ $chiffres['sans_solde'] }}</span><span class="tuile__libelle">Sans solde {{ $annee }}</span>
        </a>
        <a class="tuile {{ $chiffres['propositions'] > 0 ? 'tuile--info' : '' }}" href="{{ route('rh.soldes.propositions') }}">
            <span class="tuile__chiffre">{{ $chiffres['propositions'] }}</span><span class="tuile__libelle">Propositions à contrôler</span>
        </a>
        <div class="tuile">
            <span class="tuile__chiffre">{{ $nb($chiffres['reserves']) }}</span><span class="tuile__libelle">Jours réservés {{ $annee }}</span>
        </div>
    </div>

    <form class="outils" method="GET" action="{{ route('rh.soldes.index') }}" role="search">
        <div class="champ"><label for="q">Rechercher un agent</label><input id="q" name="q" type="search" value="{{ $q }}" placeholder="Nom, prénom, identifiant…"></div>
        <div class="champ"><label for="annee">Année</label>
            <select id="annee" name="annee">@foreach ($annees as $a) <option value="{{ $a }}" @selected($a === $annee)>{{ $a }}</option> @endforeach</select></div>
        <div class="champ"><label for="filtre">Afficher</label>
            <select id="filtre" name="filtre">
                <option value="">Tous les agents</option>
                <option value="sans_solde" @selected($filtre === 'sans_solde')>Sans solde enregistré</option>
                <option value="a_controler" @selected($filtre === 'a_controler')>Avec une proposition à contrôler</option>
            </select></div>
        <button type="submit" class="bouton bouton--ligne">Filtrer</button>
    </form>

    <section class="panneau panneau--table">
        <div class="table-enveloppe">
            <table class="tableau">
                <caption class="sr-only">Soldes {{ $annee }} des agents actifs</caption>
                <thead><tr>
                    <th scope="col">Agent</th><th scope="col">Service</th>
                    <th scope="col" class="num">Disponible</th><th scope="col" class="num">Réservés</th><th scope="col" class="num">Consommés</th>
                    <th scope="col" class="num">En attente</th><th scope="col" class="num">Prévisionnel</th><th scope="col"><span class="sr-only">Actions</span></th>
                </tr></thead>
                <tbody>
                @forelse ($lignes as $a)
                    <tr>
                        <td><strong>{{ trim($a->prenoms.' '.mb_strtoupper($a->nom)) }}</strong><br><span class="discret">{{ $a->fonction ?? '—' }} · {{ $a->identifiant }}</span></td>
                        <td>{{ $a->structure ?? '—' }}</td>
                        @if ($a->enregistre)
                            <td class="num"><strong>{{ $nb($a->disponible) }}</strong></td>
                            <td class="num">{{ $nb($a->reserves) }}</td>
                            <td class="num">{{ $nb($a->consommes) }}</td>
                            <td class="num">{{ $nb($a->en_attente) }}</td>
                            <td class="num {{ $a->previsionnel < 0 ? 'montant--moins' : '' }}">{{ $nb($a->previsionnel) }}</td>
                        @else
                            <td colspan="5"><span class="pastille pastille--alerte">Aucun solde enregistré</span></td>
                        @endif
                        <td class="actions-cellule">
                            @if ($a->propositions > 0) <span class="pastille pastille--attente">{{ $a->propositions }} à contrôler</span> @endif
                            <a href="{{ route('rh.soldes.show', ['agent' => $a->id, 'annee' => $annee]) }}">Registre</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="discret">Aucun agent ne correspond.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
