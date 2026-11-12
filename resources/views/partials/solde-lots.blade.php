{{--
  Soldes par lot (année d'acquisition), du plus ancien au plus récent : les jours les plus anciens sont consommés en premier.
  Utilisé par « Mon solde » et par la fiche solde côté RH. Variable : $lots (ImputationService::tableau).
--}}
@php $nb = fn (float $v) => rtrim(rtrim(number_format($v, 2, ',', ' '), '0'), ','); @endphp
<link rel="stylesheet" href="{{ asset('css/solde.css') }}">

@foreach ($lots['alertes'] as $a)
    <div class="alerte {{ $a['niveau'] === 'attention' ? 'alerte--attention' : 'alerte--info' }}" role="status">{{ $a['texte'] }}</div>
@endforeach

<section class="panneau panneau--table" aria-labelledby="t-lots">
    <h2 id="t-lots" class="panneau__titre">Mes jours de congé par année d’acquisition</h2>
    <p class="panneau__note lots__note">Chaque jour pris est débité du lot le plus ancien qui a encore des jours. Un lot ne couvre jamais un jour d’une année antérieure à la sienne.</p>
    <div class="table-enveloppe">
        <table class="tableau tableau--lots">
            <caption class="sr-only">Soldes par lot : jours acquis, consommés, réservés et disponibles</caption>
            <thead><tr>
                <th scope="col">Lot</th><th scope="col" class="num">Droits acquis</th><th scope="col" class="num">Consommés</th>
                <th scope="col" class="num">Réservés</th><th scope="col" class="num">Disponible</th><th scope="col">Ordre</th>
            </tr></thead>
            <tbody>
            @foreach ($lots['lignes'] as $l)
                <tr class="{{ $lots['premier_lot'] === $l['annee'] ? 'lot--premier' : '' }}">
                    <th scope="row">{{ $l['annee'] }}</th>
                    <td class="num">{{ $nb($l['acquis']) }}</td>
                    <td class="num">{{ $nb($l['consommes']) }}</td>
                    <td class="num">{{ $nb($l['reserves']) }}</td>
                    <td class="num"><strong>{{ $nb($l['disponible']) }}</strong></td>
                    <td>@if ($lots['premier_lot'] === $l['annee'])<span class="pastille pastille--attente">Consommé en premier</span>@elseif (! $l['a_des_mouvements'])<span class="discret">Aucun mouvement</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr>
                <th scope="row">Total</th>
                <td class="num">{{ $nb($lots['total']['acquis']) }}</td><td class="num">{{ $nb($lots['total']['consommes']) }}</td>
                <td class="num">{{ $nb($lots['total']['reserves']) }}</td><td class="num"><strong>{{ $nb($lots['total']['disponible']) }}</strong></td><td></td>
            </tr></tfoot>
        </table>
    </div>
    <p class="lots__projection">
        <strong>À acquérir d’ici le 31/12/{{ date('Y') }} :</strong> {{ $nb($lots['a_acquerir']) }} jour(s)
        ({{ $lots['mois_a_acquerir'] }} mois × {{ $nb(\App\Services\AcquisitionService::mensualite()) }}). C’est un rappel, pas un solde.
        <br>Au 31/12/{{ date('Y') }}, si aucun congé n’est pris : <strong>{{ $nb($lots['fin_annee']) }} jours</strong>.
    </p>
</section>
