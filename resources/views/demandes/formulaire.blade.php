@extends('layouts.app')
@section('titre', $demande->exists ? 'Modifier le brouillon' : 'Nouvelle demande')
@section('contenu')
    <header class="entete">
        <h1>{{ $demande->exists ? 'Modifier le brouillon' : 'Nouvelle demande' }}</h1>
        <p class="poste">La durée est calculée automatiquement : les week-ends et les jours fériés ne sont pas décomptés.</p>
    </header>

    <section class="panneau" aria-labelledby="t-form">
        <h2 id="t-form">Informations de la demande</h2>

        <form method="POST"
              action="{{ $demande->exists ? route('demandes.update', $demande) : route('demandes.store') }}"
              novalidate>
            @csrf
            @if ($demande->exists) @method('PUT') @endif

            <div class="grille-form">
                <div class="champ @error('date_debut') champ--erreur @enderror">
                    <label for="type">Type de demande</label>
                    <select id="type" name="type_demande_id" required>
                        <option value="">Choisir…</option>
                        @foreach ($types as $t)
                            <option value="{{ $t->id }}" @selected((int) old('type_demande_id', $demande->type_demande_id) === $t->id)>{{ $t->libelle }}</option>
                        @endforeach
                    </select>
                    @error('type_demande_id') <p class="champ__erreur" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="champ @error('date_debut') champ--erreur @enderror">
                    <label for="debut">Du</label>
                    <input id="debut" name="date_debut" type="date" required
                           value="{{ old('date_debut', $demande->date_debut?->format('Y-m-d')) }}">
                    @error('date_debut') <p class="champ__erreur" role="alert">{{ $message }}</p> @enderror
                </div>

                <div class="champ @error('date_debut') champ--erreur @enderror">
                    <label for="fin">Au (inclus)</label>
                    <input id="fin" name="date_fin" type="date" required
                           value="{{ old('date_fin', $demande->date_fin?->format('Y-m-d')) }}">
                    @error('date_fin') <p class="champ__erreur" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="champ @error('date_debut') champ--erreur @enderror">
                <label for="motif">Motif</label>
                <textarea id="motif" name="motif" rows="3" maxlength="2000">{{ old('motif', $demande->motif) }}</textarea>
                @error('motif') <p class="champ__erreur" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="champ @error('date_debut') champ--erreur @enderror">
                <label for="contact">Contact pendant l’absence</label>
                <input id="contact" name="contact_pendant_absence" type="text" maxlength="255"
                       value="{{ old('contact_pendant_absence', $demande->contact_pendant_absence) }}">
                @error('contact_pendant_absence') <p class="champ__erreur" role="alert">{{ $message }}</p> @enderror
            </div>

            <div class="champ">
                <label for="urgence">
                    <input id="urgence" name="urgence" type="checkbox" value="1" @checked(old('urgence', $demande->urgence))>
                    Demande urgente (prioritaire dans les listes)
                </label>
            </div>

            <button type="submit" class="bouton bouton--ligne">Enregistrer le brouillon</button>
            <a href="{{ route('demandes.index') }}" class="bouton bouton--discret">Annuler</a>
        </form>
    </section>
@endsection