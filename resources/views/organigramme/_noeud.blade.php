{{-- Une structure du détail (récursif). Ouverte si elle contient la structure de l'agent connecté ou si elle est de niveau 1. --}}
@php $ouvert = $n['contient_moi'] || $niveau <= 1; @endphp
<li class="noeud @unless ($n['actif']) noeud--off @endunless @if ($n['moi']) noeud--moi @endif"
    id="s{{ $n['id'] }}" data-texte="{{ $n['recherche'] }}">
    <details @if ($ouvert) open @endif>
        <summary>
            <span class="noeud__nom">{{ $n['nom'] }}@if ($n['code']) <span class="discret">({{ $n['code'] }})</span>@endif</span>
            <span class="noeud__marques">
                <span class="pastille pastille--off">{{ $n['type_libelle'] }}</span>
                <span class="pastille circuit circuit--{{ $n['circuit'] }}">{{ $n['circuit_libelle'] }}</span>
                <span class="pastille">{{ $n['effectif_total'] }} agent{{ $n['effectif_total'] > 1 ? 's' : '' }}</span>
                @if ($n['moi']) <span class="pastille pastille--attente">Vous êtes ici</span> @endif
                @unless ($n['actif']) <span class="pastille pastille--off">Désactivée</span> @endunless
                @if ($estRh)
                    @foreach ($n['alertes'] as $alerte) <span class="pastille pastille--alerte">{{ $alerte }}</span> @endforeach
                    @if ($n['postes_vacants'] > 0)
                        <span class="pastille pastille--attente">{{ $n['postes_vacants'] }} poste{{ $n['postes_vacants'] > 1 ? 's' : '' }} vacant{{ $n['postes_vacants'] > 1 ? 's' : '' }}</span>
                    @endif
                @endif
            </span>
        </summary>

        <div class="noeud__corps">
            <div class="personnes">
                @forelse ($n['responsables'] as $r)
                    <div class="personne">
                        <x-avatar :agent="$r['agent']" taille="md" />
                        <div>
                            <strong>{{ $r['agent']->nom_complet }}</strong>
                            <span>{{ $r['titre'] }}@if ($r['agent']->fonction) · {{ $r['agent']->fonction->libelle }}@endif</span>
                        </div>
                    </div>
                @empty
                    <p class="vide-resp">Aucun responsable désigné pour cette structure.</p>
                @endforelse
            </div>

            @if ($n['postes'])
                <ul class="postes" aria-label="Postes de la structure">
                    @foreach ($n['postes'] as $p)
                        <li class="poste-ligne @if ($p['vacant']) poste-ligne--vacant @endif">
                            <div>
                                <strong>{{ $p['intitule'] }}</strong>
                                @if ($p['titulaire'])
                                    <span>{{ $p['titulaire']->nom_complet }}</span>
                                @else
                                    <span class="poste-ligne__vacant">Poste vacant</span>
                                @endif
                                @if ($p['note']) <span class="discret">{{ $p['note'] }}</span> @endif
                            </div>
                            @if ($peutModifier)
                                <div class="actions-cellule">
                                    <a href="{{ route('rh.postes.edit', $p['id']) }}">Modifier</a>
                                    <form method="POST" action="{{ route('rh.postes.retirer', $p['id']) }}"
                                          onsubmit="return confirm('Retirer ce poste de l’organigramme ?')">
                                        @csrf
                                        <button type="submit" class="lien-bouton">Retirer</button>
                                    </form>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($n['fonctions'])
                <p class="etiquettes" aria-label="Fonctions occupées dans cette structure">
                    @foreach ($n['fonctions'] as $libelle => $nombre)
                        <span class="etiquette">{{ $nombre }} × {{ $libelle }}</span>
                    @endforeach
                </p>
            @endif

            @if ($peutModifier)
                <p class="noeud__actions">
                    <a href="{{ route('rh.postes.create', ['structure' => $n['id']]) }}">Ajouter un poste</a>
                    <a href="{{ route('rh.structures.create', ['parent' => $n['id']]) }}">Ajouter une sous-structure</a>
                    <a href="{{ route('rh.structures.edit', $n['id']) }}">Modifier la structure</a>
                    @if ($n['effectif'] > 0) <a href="{{ route('rh.agents.index', ['structure' => $n['id']]) }}">Voir les agents</a> @endif
                </p>
            @elseif ($estRh && $n['effectif'] > 0)
                <p class="noeud__actions"><a href="{{ route('rh.agents.index', ['structure' => $n['id']]) }}">Voir les agents</a></p>
            @endif
        </div>

        @if ($n['enfants'])
            <ul class="noeuds">
                @foreach ($n['enfants'] as $e)
                    @include('organigramme._noeud', ['n' => $e, 'niveau' => $niveau + 1])
                @endforeach
            </ul>
        @endif
    </details>
</li>
