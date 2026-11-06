@extends('layouts.app')
@section('titre', 'Organisation')
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>Organisation</h1>
            <p class="poste">Cabinet, secrétariat général, directions et services.</p>
        </div>
        @if ($peutModifier)
            <a class="bouton bouton--ligne" href="{{ route('rh.structures.create') }}">Nouvelle structure</a>
        @endif
    </header>

    @include('rh._onglets', ['actif' => 'structures'])

    <section class="panneau panneau--table">
        <div class="table-enveloppe">
            <table class="tableau">
                <caption class="sr-only">Structures de la Région</caption>
                <thead>
                    <tr><th scope="col">Structure</th><th scope="col">Type</th><th scope="col">Circuit</th><th scope="col">Agents actifs</th><th scope="col">État</th>@if ($peutModifier)<th scope="col"><span class="sr-only">Actions</span></th>@endif</tr>
                </thead>
                <tbody>
                @foreach ($lignes as $l)
                    @php $s = $l['structure']; @endphp
                    <tr class="{{ $s->actif ? '' : 'ligne-passee' }}">
                        <td>
                            <span class="retrait" style="--niveau:{{ $l['niveau'] }}">
                                {{ $s->nom }}@if ($s->code) <span class="discret">({{ $s->code }})</span>@endif
                            </span>
                        </td>
                        <td>{{ $types[$s->type] ?? $s->type }}</td>
                        <td>{{ $s->rattachement === 'region' ? 'Chef de Région' : 'Secrétaire Général' }}</td>
                        <td>
                            @if ($l['effectif'] > 0)
                                <a href="{{ route('rh.agents.index', ['structure' => $s->id]) }}">{{ $l['effectif'] }}</a>
                            @else 0 @endif
                        </td>
                        <td><span class="pastille {{ $s->actif ? 'pastille--ok' : 'pastille--off' }}">{{ $s->actif ? 'Active' : 'Désactivée' }}</span></td>
                        @if ($peutModifier)
                            <td class="actions-cellule">
                                <a href="{{ route('rh.structures.edit', $s->id) }}">Modifier</a>
                                <form method="POST" action="{{ route('rh.structures.etat', $s->id) }}"
                                      onsubmit="return confirm('{{ $s->actif ? 'Désactiver' : 'Réactiver' }} cette structure ?')">
                                    @csrf
                                    <button type="submit" class="lien-bouton">{{ $s->actif ? 'Désactiver' : 'Réactiver' }}</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @unless ($peutModifier)
        <p class="panneau__note">Seul le RH responsable modifie l’organisation, car elle détermine les circuits de décision.</p>
    @endunless
@endsection
