@extends('layouts.app')
@section('titre', 'Fonctions et postes')
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>Fonctions et postes</h1>
            <p class="poste">Liste ouverte : ajoutez tout poste de la Région. Une fonction est un libellé, elle ne donne aucun droit.</p>
        </div>
        <a class="bouton bouton--ligne" href="{{ route('rh.fonctions.create') }}">Nouvelle fonction</a>
    </header>

    @include('rh._onglets', ['actif' => 'fonctions'])

    <form class="outils" method="GET" action="{{ route('rh.fonctions.index') }}" role="search">
        <div class="champ"><label for="q">Rechercher</label><input id="q" name="q" type="search" value="{{ $q }}" placeholder="Ex. chauffeur, comptable…"></div>
        <div class="champ"><label for="categorie">Catégorie</label>
            <select id="categorie" name="categorie">
                <option value="">Toutes</option>
                @foreach ($categories as $cle => $texte) <option value="{{ $cle }}" @selected($categorie === $cle)>{{ $texte }}</option> @endforeach
            </select>
        </div>
        <button type="submit" class="bouton bouton--ligne">Filtrer</button>
    </form>

    <section class="panneau panneau--table">
        <div class="table-enveloppe">
            <table class="tableau">
                <caption class="sr-only">Fonctions enregistrées</caption>
                <thead><tr>
                    <th scope="col">Fonction</th><th scope="col">Catégorie</th><th scope="col">Direction ou service habituel</th>
                    <th scope="col">Autorité habituelle</th><th scope="col">Agents actifs</th><th scope="col">État</th><th scope="col"><span class="sr-only">Actions</span></th>
                </tr></thead>
                <tbody>
                @forelse ($fonctions as $f)
                    <tr class="{{ $f->actif ? '' : 'ligne-passee' }}">
                        <td><strong>{{ $f->libelle }}</strong></td>
                        <td>{{ $categories[$f->categorie] ?? '—' }}</td>
                        <td>{{ $f->structure_nom ?? '—' }}</td>
                        <td>{{ $f->rattachement ? \App\Services\CircuitRegles::libelleCourt($f->rattachement) : '—' }}</td>
                        <td>{{ $f->agents }}</td>
                        <td><span class="pastille {{ $f->actif ? 'pastille--ok' : 'pastille--off' }}">{{ $f->actif ? 'Active' : 'Désactivée' }}</span></td>
                        <td class="actions-cellule">
                            <a href="{{ route('rh.fonctions.edit', $f->id) }}">Modifier</a>
                            <form method="POST" action="{{ route('rh.fonctions.etat', $f->id) }}"
                                  onsubmit="return confirm('{{ $f->actif ? 'Désactiver' : 'Réactiver' }} cette fonction ?')">
                                @csrf
                                <button type="submit" class="lien-bouton">{{ $f->actif ? 'Désactiver' : 'Réactiver' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="discret">Aucune fonction ne correspond.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <p class="panneau__note">Une fonction absente de la liste peut aussi être saisie directement dans la fiche d’un agent (« Autre fonction ») : elle est alors créée ici.</p>
@endsection
