@extends('layouts.app')
@section('titre', 'Mon solde')
@section('contenu')
    <header class="entete entete--action">
        <div>
            <h1>Mon solde de congé</h1>
            <p class="poste">Votre solde est la somme d’un registre de mouvements : chaque jour ajouté ou retiré y est inscrit, avec sa date et son auteur.</p>
        </div>
        <form method="GET" action="{{ route('solde') }}" class="selecteur-annee">
            <label for="annee">Année</label>
            <select id="annee" name="annee" onchange="this.form.submit()">
                @foreach ($annees as $a) <option value="{{ $a }}" @selected($a === $annee)>{{ $a }}</option> @endforeach
            </select>
            <noscript><button type="submit" class="bouton bouton--ligne">Afficher</button></noscript>
        </form>
    </header>

    @include('partials.solde-situation', ['situation' => $situation, 'registre' => $registre, 'annee' => $annee])

    <section class="panneau" aria-labelledby="t-simul">
        <h2 id="t-simul">Combien de jours vais-je décompter ?</h2>
        <p class="panneau__note">Simulation : rien n’est enregistré. Les samedis, dimanches et jours fériés ne sont pas décomptés.</p>
        <form id="simulateur" method="POST" action="{{ route('decompte.simuler') }}" novalidate>
            @csrf
            <div class="grille-form">
                <div class="champ"><label for="sim-debut">Du</label><input id="sim-debut" name="debut" type="date" required></div>
                <div class="champ"><label for="sim-demi-debut">Premier jour</label>
                    <select id="sim-demi-debut" name="demi_debut"><option value="journee">Journée entière</option><option value="apres_midi">À partir de l’après-midi</option></select></div>
                <div class="champ"><label for="sim-fin">Au (inclus)</label><input id="sim-fin" name="fin" type="date" required></div>
                <div class="champ"><label for="sim-demi-fin">Dernier jour</label>
                    <select id="sim-demi-fin" name="demi_fin"><option value="journee">Journée entière</option><option value="matin">Jusqu’au matin</option></select></div>
            </div>
            <button type="submit" class="bouton bouton--ligne">Calculer</button>
        </form>
        <div id="simulation-resultat" class="simulation" aria-live="polite" hidden></div>
    </section>
    <script src="{{ asset('js/solde.js') }}" defer></script>
@endsection
