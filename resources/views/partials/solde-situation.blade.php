{{--
  Situation et registre d'un solde (lecture seule). Utilisé par « Mon solde » et par la fiche solde côté RH.
  Variables : $situation (SoldeService::situation), $registre (SoldeService::registre), $annee.
--}}
@php
    $nb = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ',');
    $signe = fn (float $v) => ($v > 0 ? '+' : ($v < 0 ? '−' : '')).$nb(abs($v));
@endphp
<link rel="stylesheet" href="{{ asset('css/solde.css') }}">

@unless ($situation['a_des_mouvements'])
    <div class="alerte alerte--info" role="status">Aucun solde n’est encore enregistré pour {{ $annee }}. La RH l’inscrit au registre (solde initial ou droit annuel).</div>
@endunless

<div class="tuiles tuiles--solde">
    <div class="tuile tuile--principale {{ $situation['disponible'] <= 0 ? 'tuile--alerte' : '' }}">
        <span class="tuile__chiffre">{{ $nb($situation['disponible']) }}</span>
        <span class="tuile__libelle">Solde disponible (jours)</span>
        <span class="tuile__detail">Droits + report − réservés − consommés</span>
    </div>
    <div class="tuile tuile--info">
        <span class="tuile__chiffre">{{ $nb($situation['en_attente']) }}</span>
        <span class="tuile__libelle">En attente de décision</span>
        <span class="tuile__detail">Demandes déposées, pas encore décidées</span>
    </div>
    <div class="tuile {{ $situation['previsionnel'] < 0 ? 'tuile--alerte' : '' }}">
        <span class="tuile__chiffre">{{ $nb($situation['previsionnel']) }}</span>
        <span class="tuile__libelle">Solde prévisionnel</span>
        <span class="tuile__detail">Disponible − en attente</span>
    </div>
    <div class="tuile">
        <span class="tuile__chiffre">{{ $nb($situation['reserves']) }}</span>
        <span class="tuile__libelle">Jours réservés</span>
        <span class="tuile__detail">Congés validés, pas encore pris</span>
    </div>
    <div class="tuile">
        <span class="tuile__chiffre">{{ $nb($situation['consommes']) }}</span>
        <span class="tuile__libelle">Jours consommés</span>
        <span class="tuile__detail">Congés déjà pris</span>
    </div>
</div>

<p class="solde-formule">
    {{ $annee }} : droit acquis <strong>{{ $nb($situation['acquis']) }}</strong>
    + report <strong>{{ $nb($situation['report']) }}</strong>
    @if ($situation['ajustements'] != 0) + ajustements <strong>{{ $signe($situation['ajustements']) }}</strong> @endif
    − réservés <strong>{{ $nb($situation['reserves']) }}</strong>
    − consommés <strong>{{ $nb($situation['consommes']) }}</strong>
    = <strong>{{ $nb($situation['disponible']) }} jours</strong>
</p>

<section class="panneau panneau--table" aria-labelledby="t-registre">
    <h2 id="t-registre" class="panneau__titre">Registre des mouvements {{ $annee }}</h2>
    <div class="table-enveloppe">
        <table class="tableau tableau--registre">
            <caption class="sr-only">Registre des mouvements de solde, avec le solde disponible après chaque ligne</caption>
            <thead><tr>
                <th scope="col">Date</th><th scope="col">Mouvement</th><th scope="col">Détail</th>
                <th scope="col" class="num">Jours</th><th scope="col" class="num">Solde après</th>
            </tr></thead>
            <tbody>
            @forelse ($registre as $l)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($l['date'])->format('d/m/Y') }}</td>
                    <td><span class="pastille {{ in_array($l['type'], ['reservation', 'indemnisation', 'perte']) ? 'pastille--attente' : ($l['type'] === 'consommation' ? 'pastille--off' : 'pastille--ok') }}">{{ $l['libelle'] }}</span></td>
                    <td>
                        {{ $l['motif'] }}
                        @if ($l['auteur']) <span class="discret"> · saisi par {{ $l['auteur'] }}</span> @else <span class="discret"> · automatique</span> @endif
                    </td>
                    <td class="num {{ $l['jours'] > 0 ? 'montant--plus' : ($l['jours'] < 0 ? 'montant--moins' : 'montant--nul') }}">
                        @if ($l['jours'] != 0) {{ $signe($l['jours']) }} @else 0 <span class="discret">({{ $l['consomme'] > 0 ? 'pris' : '—' }})</span> @endif
                    </td>
                    <td class="num"><strong>{{ $nb($l['solde_apres']) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" class="discret">Aucun mouvement pour {{ $annee }}.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
