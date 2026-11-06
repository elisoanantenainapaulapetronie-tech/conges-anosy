@props(['nom', 'label', 'options', 'valeur' => null, 'vide' => null, 'aide' => null, 'requis' => false, 'donnees' => [], 'classe' => ''])
@php
    $erreur = $errors->first($nom);
    $decrit = trim(($aide ? $nom.'-aide ' : '').($erreur ? $nom.'-erreur' : ''));
    $choisi = (string) old($nom, $valeur);
@endphp
<div class="champ {{ $classe }} {{ $erreur ? 'champ--erreur' : '' }}">
    <label for="{{ $nom }}">{{ $label }}@if ($requis) <span class="requis" aria-hidden="true">*</span>@endif</label>
    <select id="{{ $nom }}" name="{{ $nom }}"
            @if ($requis) required @endif
            @if ($erreur) aria-invalid="true" @endif
            @if ($decrit) aria-describedby="{{ $decrit }}" @endif
            {{ $attributes }}>
        @if ($vide !== null) <option value="">{{ $vide }}</option> @endif
        @foreach ($options as $cle => $texte)
            <option value="{{ $cle }}" @selected($choisi === (string) $cle) @if (isset($donnees[$cle])) data-rattachement="{{ $donnees[$cle] }}" @endif>{{ $texte }}</option>
        @endforeach
    </select>
    @if ($aide) <p class="champ__aide" id="{{ $nom }}-aide">{{ $aide }}</p> @endif
    @if ($erreur) <p class="champ__erreur" id="{{ $nom }}-erreur" role="alert">{{ $erreur }}</p> @endif
</div>
