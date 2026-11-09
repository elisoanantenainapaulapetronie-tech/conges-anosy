@extends('layouts.app')
@section('titre', 'Organigramme')
@section('contenu')
    <link rel="stylesheet" href="{{ asset('css/organigramme.css') }}">
    <script src="{{ asset('js/organigramme.js') }}" defer></script>

    <header class="entete entete--action">
        <div>
            <h1>Organigramme</h1>
            <p class="poste">Organisation de la Région Anosy, mise à jour automatiquement à partir des structures, des rôles et des fiches des agents.</p>
        </div>
        <div class="actions-ligne org-actions">
            <button type="button" class="bouton bouton--ligne bouton--discret" data-imprimer>Imprimer / PDF</button>
            @if ($peutModifier)
                <a class="bouton bouton--ligne" href="{{ route('rh.structures.create') }}">Nouvelle structure</a>
            @endif
        </div>
    </header>

    @if ($estRh) @include('rh._onglets', ['actif' => 'organigramme']) @endif

    <div class="tuiles org-tuiles">
        <div class="tuile">
            <span class="tuile__chiffre">{{ $stats['structures'] }}</span>
            <span class="tuile__libelle">Structures actives</span>
        </div>
        <div class="tuile">
            <span class="tuile__chiffre">{{ $stats['agents'] }}</span>
            <span class="tuile__libelle">Agents en activité</span>
        </div>
        @if ($estRh)
            <div class="tuile {{ $stats['postes_vacants'] > 0 ? 'tuile--info' : '' }}">
                <span class="tuile__chiffre">{{ $stats['postes_vacants'] }}</span>
                <span class="tuile__libelle">Postes vacants</span>
            </div>
            <div class="tuile {{ $stats['sans_responsable'] > 0 ? 'tuile--alerte' : '' }}">
                <span class="tuile__chiffre">{{ $stats['sans_responsable'] }}</span>
                <span class="tuile__libelle">Structures sans responsable</span>
            </div>
        @endif
    </div>

    @if ($estRh && (count($anomalies) > 0 || count($orphelines) > 0))
        <section class="panneau" aria-labelledby="t-completer">
            <h2 id="t-completer">À compléter</h2>
            <p class="panneau__note">Sans responsable, la demande d’avis d’un agent de la structure n’aurait aucun destinataire.</p>
            <ul class="liste-simple">
                @foreach ($anomalies as $a)
                    <li>
                        <span>
                            <span class="pastille {{ $a['type'] === 'sans_responsable' ? 'pastille--alerte' : 'pastille--attente' }}">{{ $a['type'] === 'sans_responsable' ? 'Sans responsable' : 'Poste vacant' }}</span>
                            {{ $a['libelle'] }}
                        </span>
                        <a href="#s{{ $a['structure_id'] }}">Voir dans l’organigramme</a>
                    </li>
                @endforeach
                @foreach ($orphelines as $nom)
                    <li><span><span class="pastille pastille--alerte">Anomalie</span> « {{ $nom }} » n’est rattachée à aucun niveau (boucle dans la hiérarchie).</span></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="panneau org-schema" aria-labelledby="t-schema">
        <h2 id="t-schema">Vue d’ensemble</h2>
        <p class="discret">Touchez une structure pour ouvrir son détail plus bas. Sur téléphone, faites défiler le schéma de gauche à droite.</p>
        <div class="schema" tabindex="0" role="region" aria-label="Schéma de l’organigramme">
            <ul>
                <li>
                    <div class="carte carte--chef">
                        <span class="carte__nom">Chef de Région</span>
                        @forelse ($chefs as $c)
                            <span class="carte__resp">{{ $c['agent']->nom_complet }}</span>
                        @empty
                            <span class="carte__resp carte__resp--vide">Titulaire à désigner</span>
                        @endforelse
                    </div>
                    @if ($racines)
                        <ul>
                            @foreach ($racines as $r)
                                @include('organigramme._carte', ['n' => $r, 'profondeur' => 1])
                            @endforeach
                        </ul>
                    @endif
                </li>
            </ul>
        </div>
        <p class="legende" aria-label="Légende">
            <span><i class="legende__pastille legende__pastille--sg"></i> Décision du Secrétaire Général</span>
            <span><i class="legende__pastille legende__pastille--region"></i> Décision du Chef de Région</span>
            <span><i class="legende__pastille legende__pastille--sg_region"></i> Les deux, SG puis Chef</span>
        </p>
    </section>

    <section class="panneau" aria-labelledby="t-detail">
        <h2 id="t-detail">Détail par structure</h2>

        <div class="org-controles">
            <div class="org-controles__recherche">
                <label class="sr-only" for="org-recherche">Rechercher dans l’organigramme</label>
                <input type="search" id="org-recherche" placeholder="Rechercher une structure, une personne, une fonction…" autocomplete="off">
            </div>
            <button type="button" class="bouton bouton--ligne bouton--discret" data-org="deplier">Tout déplier</button>
            <button type="button" class="bouton bouton--ligne bouton--discret" data-org="replier">Tout replier</button>
            @if ($estRh)
                <a class="bouton bouton--ligne bouton--discret" href="{{ route('organigramme', $inactives ? [] : ['inactives' => 1]) }}">
                    {{ $inactives ? 'Masquer' : 'Afficher' }} les structures désactivées
                </a>
            @endif
        </div>
        <p id="org-resultat" class="discret" role="status" aria-live="polite"></p>

        <div class="noeud__chef">
            <strong>Chef de Région</strong>
            @forelse ($chefs as $c)
                <div class="personne">
                    <x-avatar :agent="$c['agent']" taille="md" />
                    <div><strong>{{ $c['agent']->nom_complet }}</strong><span>{{ $c['agent']->fonction?->libelle ?? $c['titre'] }}</span></div>
                </div>
            @empty
                <p class="vide-resp">Aucun Chef de Région en fonction : attribuez le rôle depuis la fiche de l’agent.</p>
            @endforelse
        </div>

        <div id="detail">
            <ul class="noeuds noeuds--racine">
                @foreach ($racines as $r)
                    @include('organigramme._noeud', ['n' => $r, 'niveau' => 1])
                @endforeach
            </ul>
        </div>
    </section>

    @unless ($peutModifier)
        <p class="panneau__note">L’organisation est modifiée par le RH responsable, car elle détermine les circuits de décision.</p>
    @endunless
@endsection
