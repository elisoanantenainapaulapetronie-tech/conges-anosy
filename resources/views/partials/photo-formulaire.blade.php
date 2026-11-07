{{-- Formulaire de photo, partagé par « Mon profil » et la fiche RH. Variables : $agent, $action, $supprimer --}}
<script src="{{ asset('js/photo.js') }}" defer></script>

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="photo-formulaire" data-photo-form>
    @csrf
    <div class="champ {{ $errors->has('photo') ? 'champ--erreur' : '' }}">
        <label for="photo">Choisir une photo</label>
        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required
               aria-describedby="photo-aide photo-erreur">
        <p class="champ__aide" id="photo-aide">JPEG, PNG ou WebP, 5 Mo au plus. Photo de face, visage bien visible : elle sera recadrée en carré.</p>
        <p class="champ__erreur" id="photo-erreur" role="alert" data-photo-message>{{ $errors->first('photo') }}</p>
    </div>

    <div class="photo-apercu" hidden>
        <img alt="Aperçu de la nouvelle photo" src="">
        <span>Aperçu : voici comment elle apparaîtra.</span>
    </div>

    <div class="actions-photo">
        <button type="submit" class="bouton bouton--ligne">Enregistrer la photo</button>
    </div>
</form>

@if ($agent->photo_chemin)
    <form method="POST" action="{{ $supprimer }}" class="actions-photo"
          onsubmit="return confirm('Supprimer cette photo ? Les initiales seront affichées à la place.')">
        @csrf @method('DELETE')
        <button type="submit" class="bouton bouton--ligne bouton--danger">Supprimer la photo</button>
    </form>
@endif
