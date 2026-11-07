@extends('layouts.app')
@section('titre', 'Agents')
@php
    $libellesCompte = ['actif' => 'Compte actif', 'en_attente_activation' => 'En attente d’activation', 'desactive' => 'Désactivé'];
    $classesCompte = ['actif' => 'pastille--ok', 'en_attente_activation' => 'pastille--attente', 'desactive' => 'pastille--off'];
@endphp
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>Agents</h1>
            <p class="poste">Annuaire du personnel de la Région Anosy.</p>
        </div>
        @can('creer', \App\Models\Agent::class)
            <a class="bouton bouton--ligne" href="{{ route('rh.agents.create') }}">Nouvel agent</a>
        @endcan
    </header>

    @include('rh._onglets', ['actif' => 'agents'])

    <form class="outils" method="GET" action="{{ route('rh.agents.index') }}" role="search">
        <div class="champ">
            <label for="q">Rechercher</label>
            <input id="q" name="q" type="search" value="{{ $q }}" placeholder="Nom, prénom, matricule, identifiant" autocomplete="off">
        </div>
        <div class="champ">
            <label for="structure">Service</label>
            <select id="structure" name="structure">
                <option value="">Tous</option>
                @foreach ($structures as $id => $nom)
                    <option value="{{ $id }}" @selected((string) $structure === (string) $id)>{{ $nom }}</option>
                @endforeach
            </select>
        </div>
        <div class="champ">
            <label for="situation">Situation</label>
            <select id="situation" name="situation">
                <option value="actif" @selected($situation === 'actif')>Actifs</option>
                <option value="suspendu" @selected($situation === 'suspendu')>Suspendus</option>
                <option value="sorti" @selected($situation === 'sorti')>Sortis</option>
                <option value="tous" @selected($situation === 'tous')>Toutes</option>
            </select>
        </div>
        <div class="champ">
            <label for="compte">Compte</label>
            <select id="compte" name="compte">
                <option value="">Tous</option>
                <option value="actif" @selected($etat === 'actif')>Actif</option>
                <option value="en_attente_activation" @selected($etat === 'en_attente_activation')>En attente d’activation</option>
                <option value="desactive" @selected($etat === 'desactive')>Désactivé</option>
                <option value="sans" @selected($etat === 'sans')>Aucun compte</option>
            </select>
        </div>
        <button type="submit" class="bouton bouton--ligne">Filtrer</button>
    </form>

    <section class="panneau panneau--table">
        @if ($agents->isEmpty())
            <p class="vide">Aucun agent ne correspond à ces critères.</p>
        @else
            <div class="table-enveloppe">
                <table class="tableau">
                    <caption class="sr-only">Liste des agents</caption>
                    <thead>
                        <tr><th scope="col">Agent</th><th scope="col">Identifiant</th><th scope="col">Service</th><th scope="col">Rôles</th><th scope="col">Compte</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($agents as $a)
                            <tr>
                                <td>
                                    <div class="liste-agent">
                                        <x-avatar :agent="$a" taille="sm" />
                                        <div>
                                            <a href="{{ route('rh.agents.show', $a) }}"><strong>{{ $a->nom_complet }}</strong></a>
                                            <span class="discret bloc">{{ $a->fonction?->libelle ?? '—' }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $a->identifiant }}@if (! $a->matricule)<span class="discret bloc">sans matricule</span>@endif</td>
                                <td>{{ $a->structure?->nom }}</td>
                                <td>
                                    @forelse ($roles->get($a->id, collect()) as $r)
                                        <span class="etiquette">{{ $r->libelle }}</span>
                                    @empty
                                        <span class="discret">Agent</span>
                                    @endforelse
                                </td>
                                <td>
                                    @if ($a->situation !== 'actif')
                                        <span class="pastille pastille--off">{{ ucfirst($a->situation) }}</span>
                                    @elseif ($a->compte)
                                        <span class="pastille {{ $classesCompte[$a->compte->statut] ?? '' }}">{{ $libellesCompte[$a->compte->statut] ?? $a->compte->statut }}</span>
                                    @else
                                        <span class="pastille pastille--alerte">Aucun compte</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <nav class="pagination-simple" aria-label="Pagination">
                @if ($agents->previousPageUrl()) <a href="{{ $agents->previousPageUrl() }}">← Précédents</a> @else <span></span> @endif
                <span class="discret">Page {{ $agents->currentPage() }}</span>
                @if ($agents->hasMorePages()) <a href="{{ $agents->nextPageUrl() }}">Suivants →</a> @else <span></span> @endif
            </nav>
        @endif
    </section>
@endsection
