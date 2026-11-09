@extends('layouts.app')
@section('titre', 'Propositions de solde à contrôler')
@section('contenu')
    <header class="entete">
        <h1>Propositions de solde à contrôler</h1>
        <p class="poste">Personne ne contrôle son propre solde ni sa propre proposition. Valider inscrit le mouvement au registre ; rejeter ne change rien.</p>
    </header>

    @include('rh._onglets', ['actif' => 'soldes'])
    <link rel="stylesheet" href="{{ asset('css/solde.css') }}">

    @forelse ($propositions as $p)
        <section class="panneau proposition" aria-labelledby="p-{{ $p->id }}">
            <h2 id="p-{{ $p->id }}">
                {{ \App\Services\SoldeService::NATURES[$p->nature] ?? $p->nature }} :
                <span class="{{ $p->jours > 0 ? 'montant--plus' : 'montant--moins' }}">{{ $p->jours > 0 ? '+' : '−' }}{{ \App\Services\DecompteService::libelle(abs((float) $p->jours)) }}</span>
                sur {{ $p->annee }}
            </h2>
            <dl class="proposition__infos">
                <div><dt>Agent concerné</dt><dd><a href="{{ route('rh.soldes.show', ['agent' => $p->agent_id, 'annee' => $p->annee]) }}">{{ trim($p->cible_prenoms.' '.mb_strtoupper($p->cible_nom)) }}</a> ({{ $p->cible_identifiant }})</dd></div>
                <div><dt>Proposé par</dt><dd>{{ trim($p->auteur_prenoms.' '.mb_strtoupper($p->auteur_nom)) }}, le {{ \Carbon\Carbon::parse($p->created_at)->format('d/m/Y') }}</dd></div>
                <div><dt>Motif</dt><dd>{{ $p->motif }}</dd></div>
                @if ($p->reference_piece) <div><dt>Document officiel</dt><dd>{{ $p->reference_piece }}</dd></div> @endif
                <div><dt>À contrôler par</dt><dd>{{ $p->controleur_attendu }}</dd></div>
            </dl>

            @if ($p->peut_controler)
                <form method="POST" action="{{ route('rh.soldes.controler', $p->id) }}" data-envoi>
                    @csrf
                    <div class="champ">
                        <label for="c-{{ $p->id }}">Commentaire <span class="discret">(obligatoire pour rejeter)</span></label>
                        <input id="c-{{ $p->id }}" name="commentaire" type="text" maxlength="1000">
                    </div>
                    <div class="actions-formulaire">
                        <button type="submit" name="decision" value="valider" class="bouton bouton--ligne">Valider</button>
                        <button type="submit" name="decision" value="rejeter" class="bouton bouton--ligne bouton--danger">Rejeter</button>
                    </div>
                </form>
            @else
                <p class="panneau__note">Vous ne pouvez pas contrôler cette proposition (vous en êtes l’auteur, elle vous concerne, ou son contrôle revient à {{ $p->controleur_attendu }}).</p>
            @endif
        </section>
    @empty
        <section class="panneau"><p class="panneau__note" style="margin:0">Aucune proposition en attente.</p></section>
    @endforelse
@endsection
