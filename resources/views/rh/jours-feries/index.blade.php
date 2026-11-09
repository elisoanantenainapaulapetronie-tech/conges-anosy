@extends('layouts.app')
@section('titre', 'Jours fériés')
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>Jours fériés</h1>
            <p class="poste">Le décompte des jours d’absence ne compte ni les samedis, ni les dimanches, ni ces jours. Complétez le calendrier chaque année.</p>
        </div>
        <form method="GET" action="{{ route('rh.jours-feries.index') }}" class="selecteur-annee">
            <label for="annee">Année</label>
            <select id="annee" name="annee" onchange="this.form.submit()">
                @foreach ($annees as $a) <option value="{{ $a }}" @selected($a === $annee)>{{ $a }}</option> @endforeach
            </select>
        </form>
    </header>

    @include('rh._onglets', ['actif' => 'jours-feries'])
    <link rel="stylesheet" href="{{ asset('css/solde.css') }}">

    @if ($jours->isEmpty())
        <div class="alerte alerte--erreur" role="alert">Aucun jour férié enregistré pour {{ $annee }} : les décomptes de cette année seraient surestimés.</div>
    @endif

    @if (count($manquants) > 0)
        <section class="panneau" aria-labelledby="t-propose">
            <h2 id="t-propose">Dates proposées pour {{ $annee }}</h2>
            <p class="panneau__note">Fêtes à date fixe et fêtes mobiles (calculées à partir de Pâques). Vérifiez-les avec le calendrier officiel avant d’ajouter.</p>
            <ul class="liste-simple">
                @foreach ($manquants as $date => $libelle)
                    <li><strong>{{ \Carbon\Carbon::parse($date)->translatedFormat('l j F Y') }}</strong> — {{ $libelle }}</li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('rh.jours-feries.remplir') }}">
                @csrf
                <input type="hidden" name="annee" value="{{ $annee }}">
                <button type="submit" class="bouton bouton--ligne">Ajouter ces {{ count($manquants) }} jour(s)</button>
            </form>
        </section>
    @endif

    <section class="panneau panneau--table">
        <div class="table-enveloppe">
            <table class="tableau">
                <caption class="sr-only">Jours fériés {{ $annee }}</caption>
                <thead><tr><th scope="col">Date</th><th scope="col">Jour</th><th scope="col">Nom</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @forelse ($jours as $j)
                    <tr class="{{ $j->passe ? 'ligne-passee' : '' }}">
                        <td><strong>{{ \Carbon\Carbon::parse($j->date_ferie)->format('d/m/Y') }}</strong></td>
                        <td>{{ ucfirst($j->jour) }} @if ($j->week_end) <span class="pastille pastille--off">week-end : sans effet</span> @endif</td>
                        <td>{{ $j->libelle }}</td>
                        <td class="actions-cellule">
                            @unless ($j->passe)
                                <form method="POST" action="{{ route('rh.jours-feries.destroy', $j->id) }}" onsubmit="return confirm('Retirer ce jour férié du calendrier ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="lien-bouton">Retirer</button>
                                </form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="discret">Aucun jour férié enregistré pour {{ $annee }}.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="panneau" aria-labelledby="t-ajout">
        <h2 id="t-ajout">Ajouter un jour férié</h2>
        <p class="panneau__note">Pour un jour exceptionnel décrété par l’administration (pont, deuil national…).</p>
        <form method="POST" action="{{ route('rh.jours-feries.store') }}" data-envoi>
            @csrf
            <div class="grille-form">
                <x-rh-champ nom="date_ferie" label="Date" type="date" :requis="true" />
                <x-rh-champ nom="libelle" label="Nom du jour férié" :requis="true" />
            </div>
            <button type="submit" class="bouton bouton--ligne">Ajouter</button>
        </form>
    </section>
@endsection
