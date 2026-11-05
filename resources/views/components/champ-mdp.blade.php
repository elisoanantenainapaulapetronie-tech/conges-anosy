@props(['nom', 'label', 'autocomplete' => 'current-password', 'aide' => null])
@php
    $erreur = $errors->first($nom);
    $decrit = trim(($aide ? $nom.'-aide ' : '').($erreur ? $nom.'-erreur' : ''));
@endphp
<div class="champ {{ $erreur ? 'champ--erreur' : '' }}">
    <label for="{{ $nom }}">{{ $label }}</label>
    <div class="champ__mdp">
        <input id="{{ $nom }}" name="{{ $nom }}" type="password" autocomplete="{{ $autocomplete }}"
               @if ($erreur) aria-invalid="true" @endif
               @if ($decrit) aria-describedby="{{ $decrit }}" @endif
               {{ $attributes }}>
        <button type="button" class="champ__bascule" data-basculer="{{ $nom }}" aria-pressed="false">Afficher</button>
    </div>
    @if ($aide) <p class="champ__aide" id="{{ $nom }}-aide">{{ $aide }}</p> @endif
    @if ($erreur) <p class="champ__erreur" id="{{ $nom }}-erreur" role="alert">{{ $erreur }}</p> @endif
</div>
