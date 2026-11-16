{{-- Une proposition traitée (validée, rejetée ou retirée). Variables : $p (SoldeService::historique), $liste (pièces) --}}
@php
    $classes = ['valide' => 'pastille--ok', 'rejete' => 'pastille--alerte', 'retiree' => 'pastille--off'];
    $libelles = ['valide' => 'Validée', 'rejete' => 'Rejetée', 'retiree' => 'Retirée'];
@endphp
<li>
    <span class="pastille {{ $classes[$p->statut] ?? 'pastille--off' }}">{{ $libelles[$p->statut] ?? $p->statut }}</span>
    {{ \App\Services\SoldeService::NATURES[$p->nature] ?? $p->nature }}
    <strong>{{ $p->jours > 0 ? '+' : '−' }}{{ \App\Services\DecompteService::libelle(abs((float) $p->jours)) }}</strong> ({{ $p->annee }})
    — {{ $p->motif }}
    <span class="discret">
        · proposé par {{ trim($p->auteur_prenoms.' '.mb_strtoupper($p->auteur_nom)) }}
        @if ($p->statut === 'retiree')
            · retiré le {{ \Carbon\Carbon::parse($p->retire_le)->format('d/m/Y') }} : « {{ $p->motif_retrait }} »
        @else
            · {{ $p->statut === 'valide' ? 'validé' : 'rejeté' }} le {{ \Carbon\Carbon::parse($p->controle_le)->format('d/m/Y') }} par {{ trim($p->controleur_prenoms.' '.mb_strtoupper($p->controleur_nom)) }}
            @if ($p->commentaire_controle) · observation : « {{ $p->commentaire_controle }} » @endif
        @endif
    </span>
    @include('rh.soldes._justificatifs', ['liste' => $liste])
</li>
