@props(['nom', 'label', 'type' => 'text', 'valeur' => null, 'aide' => null, 'requis' => false, 'classe' => ''])
@php
    $erreur = $errors->first($nom);
    $decrit = trim(($aide ? $nom.'-aide ' : '').($erreur ? $nom.'-erreur' : ''));
@endphp
<div class="champ {{ $classe }} {{ $erreur ? 'champ--erreur' : '' }}">
    <label for="{{ $nom }}">{{ $label }}@if ($requis) <span class="requis" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $nom }}" name="{{ $nom }}" type="{{ $type }}" value="{{ old($nom, $valeur) }}"
           @if ($requis) required @endif
           @if ($erreur) aria-invalid="true" @endif
           @if ($decrit) aria-describedby="{{ $decrit }}" @endif
           {{ $attributes }}>
    @if ($aide) <p class="champ__aide" id="{{ $nom }}-aide">{{ $aide }}</p> @endif
    @if ($erreur) <p class="champ__erreur" id="{{ $nom }}-erreur" role="alert">{{ $erreur }}</p> @endif
</div>
