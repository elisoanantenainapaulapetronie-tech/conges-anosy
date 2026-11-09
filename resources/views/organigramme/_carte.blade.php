{{-- Une carte du schéma d'ensemble. Le schéma s'arrête au niveau 2 ; le détail complet est plus bas. --}}
@php $sous = \App\Services\OrganigrammeService::descendants($n); @endphp
<li>
    <a href="#s{{ $n['id'] }}"
       class="carte carte--{{ $n['circuit'] }} @if ($n['moi']) carte--moi @endif @unless ($n['actif']) carte--off @endunless">
        <span class="carte__nom">{{ $n['nom'] }}</span>
        @if ($n['responsables'])
            <span class="carte__resp">{{ $n['responsables'][0]['agent']->nom_complet }}</span>
        @else
            <span class="carte__resp carte__resp--vide">Responsable à désigner</span>
        @endif
        <span class="carte__pied">
            {{ $n['effectif_total'] }} agent{{ $n['effectif_total'] > 1 ? 's' : '' }}
            @if ($profondeur >= 2 && $sous > 0) · {{ $sous }} sous-structure{{ $sous > 1 ? 's' : '' }} @endif
        </span>
    </a>
    @if ($n['enfants'] && $profondeur < 2)
        <ul>
            @foreach ($n['enfants'] as $e)
                @include('organigramme._carte', ['n' => $e, 'profondeur' => $profondeur + 1])
            @endforeach
        </ul>
    @endif
</li>
