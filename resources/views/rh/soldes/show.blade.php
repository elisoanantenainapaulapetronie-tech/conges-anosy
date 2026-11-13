@extends('layouts.app')
@section('titre', 'Solde de '.$agent->nom_complet)
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>Solde de {{ $agent->nom_complet }}</h1>
            <p class="poste">{{ $agent->fonction?->libelle ?? 'Sans fonction' }} · {{ $agent->structure?->nom ?? '—' }} · {{ $agent->identifiant }}</p>
        </div>
        <form method="GET" action="{{ route('rh.soldes.show', $agent->id) }}" class="selecteur-annee">
            <label for="annee">Année</label>
            <select id="annee" name="annee" onchange="this.form.submit()">
                @foreach ($annees as $a) <option value="{{ $a }}" @selected($a === $annee)>{{ $a }}</option> @endforeach
            </select>
        </form>
    </header>

    @include('rh._onglets', ['actif' => 'soldes'])
    <p><a href="{{ route('rh.soldes.index', ['annee' => $annee]) }}">← Tous les soldes</a> · <a href="{{ route('rh.agents.show', $agent->id) }}">Fiche de l’agent</a></p>

    @include('partials.solde-lots', ['lots' => $lots])

    @include('partials.solde-situation', ['situation' => $situation, 'registre' => $registre, 'annee' => $annee])

    @if ($enAttente->isNotEmpty())
        <section class="panneau" aria-labelledby="t-attente">
            <h2 id="t-attente">Propositions en attente de contrôle</h2>
            <ul class="liste-simple">
                @foreach ($enAttente as $p)
                    <li>
                        <span class="pastille pastille--attente">{{ \App\Services\SoldeService::NATURES[$p->nature] ?? $p->nature }}</span>
                        <strong>{{ $p->jours > 0 ? '+' : '−' }}{{ \App\Services\DecompteService::libelle(abs((float) $p->jours)) }}</strong> sur {{ $p->annee }}
                        — {{ $p->motif }} <span class="discret">(proposé par {{ trim($p->auteur_prenoms.' '.mb_strtoupper($p->auteur_nom)) }}, à contrôler par {{ $controleur }})</span>
                    </li>
                @endforeach
            </ul>
            <p class="panneau__note"><a href="{{ route('rh.soldes.propositions') }}">Ouvrir la file de contrôle</a></p>
        </section>
    @endif

    @if ($peutProposer)
        <section class="panneau" aria-labelledby="t-proposer">
            <h2 id="t-proposer">Proposer un mouvement</h2>
            <p class="panneau__note">Le solde ne change pas tout de suite : la proposition est contrôlée par <strong>{{ $controleur }}</strong>. Vous ne pouvez pas contrôler votre propre proposition.</p>
            @if ($errors->has('mouvement')) <div class="alerte alerte--erreur" role="alert">{{ $errors->first('mouvement') }}</div> @endif
            <form method="POST" action="{{ route('rh.soldes.proposer', $agent->id) }}" data-envoi>
                @csrf
                <div class="grille-form">
                    <x-rh-liste nom="nature" label="Type de mouvement" :requis="true" :valeur="old('nature')" vide="— Choisir —" :options="$natures"
                                aide="Solde initial : jours acquis avant la mise en service (y compris les reliquats des années passées). Correction : ajuste un solde par erreur constatée." />
                    <x-rh-liste nom="annee" label="Année concernée" :requis="true" :valeur="old('annee', $annee)" :options="array_combine($annees, $annees)" />
                    <x-rh-champ nom="jours" label="Nombre de jours" type="text" inputmode="decimal" :requis="true"
                                aide="Par pas de 0,5. Virgule ou point acceptés (2,5 ou 2.5). Solde initial : nombre positif ; correction : +3 ajoute, -3 retire." />
                    <x-rh-champ nom="reference_piece" label="Document officiel (référence)" :requis="false"
                                aide="Obligatoire pour un solde initial, un report et une correction. Ex. décision n° 12/2025, registre des congés." />
                    <x-rh-champ nom="motif" label="Motif" :requis="true" classe="grille-form__large" aide="Expliquez la raison en une phrase." />
                </div>
                <button type="submit" class="bouton bouton--ligne">Envoyer pour contrôle</button>
            </form>
        </section>
    @else
        <p class="panneau__note">Vous ne pouvez pas proposer de mouvement sur ce solde (c’est le vôtre, ou son niveau de contrôle ne vous est pas ouvert).</p>
    @endif

    @if ($historique->isNotEmpty())
        <section class="panneau" aria-labelledby="t-histo">
            <h2 id="t-histo">Dernières propositions contrôlées</h2>
            <ul class="liste-simple">
                @foreach ($historique as $p)
                    <li>
                        <span class="pastille {{ $p->statut === 'valide' ? 'pastille--ok' : 'pastille--alerte' }}">{{ $p->statut === 'valide' ? 'Validée' : 'Rejetée' }}</span>
                        {{ \App\Services\SoldeService::NATURES[$p->nature] ?? $p->nature }} {{ $p->jours > 0 ? '+' : '−' }}{{ \App\Services\DecompteService::libelle(abs((float) $p->jours)) }} ({{ $p->annee }})
                        — {{ $p->motif }}
                        @if ($p->commentaire_controle) <span class="discret">· « {{ $p->commentaire_controle }} »</span> @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endsection
