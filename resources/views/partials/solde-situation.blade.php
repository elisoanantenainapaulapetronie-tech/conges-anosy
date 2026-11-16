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
    <div class="alerte alerte--info" role="status">Aucun solde n’est encore enregistré pour {{ $annee }}. Les jours s’acquièrent chaque mois (le 1er du mois) ; la RH peut aussi inscrire un solde initial.</div>
@endunless

<div class="tuiles tuiles--solde">
    <div class="tuile tuile--principale {{ $situation['disponible'] <= 0 ? 'tuile--alerte' : '' }}">
        <span class="tuile__chiffre">{{ $nb($situation['disponible']) }}</span>
        <span class="tuile__libelle">Solde disponible (jours)</span>
        <span class="tuile__detail">Solde initial + acquisitions + corrections − réservés − consommés</span>
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

<section class="panneau" aria-labelledby="t-composition">
    <h2 id="t-composition" class="panneau__titre">Composition du lot {{ $annee }}</h2>
    <p class="panneau__note">Un lot rassemble les jours acquis pendant l’année {{ $annee }}. Il garde son année d’origine, même s’il est consommé plus tard : les lots les plus anciens sont consommés en premier.</p>
    <dl class="composition">
        <div><dt>Solde initial <span class="discret">(repris à la mise en service ou reliquat de cette année)</span></dt><dd>{{ $nb($situation['initial']) }}</dd></div>
        <div><dt>Acquisitions <span class="discret">(2,5 jours par mois)</span></dt><dd>{{ $nb($situation['acquisitions']) }}</dd></div>
        @if ($situation['report'] != 0)
            <div><dt>Reports <span class="discret">(ancien mode, conservé pour l’historique)</span></dt><dd>{{ $signe($situation['report']) }}</dd></div>
        @endif
        <div><dt>Corrections</dt><dd>{{ $situation['ajustements'] != 0 ? $signe($situation['ajustements']) : '0' }}</dd></div>
        <div><dt>Jours réservés</dt><dd>− {{ $nb($situation['reserves']) }}</dd></div>
        <div><dt>Jours consommés</dt><dd>− {{ $nb($situation['consommes']) }}</dd></div>
        <div class="composition__total"><dt>Disponible dans ce lot</dt><dd>{{ $nb($situation['disponible']) }} jours</dd></div>
    </dl>
</section>

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
