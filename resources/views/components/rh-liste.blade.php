{{--
  Liste déroulante de l'espace RH.
  Saisie libre (facultative) : si $libreChamp est donné, un champ texte apparaît quand l'utilisateur choisit la valeur
  « autre » ; rh.js l'affiche ou le masque. Sans JavaScript, le champ reste visible : rien ne se perd.
    - $libreOption : texte d'une option ajoutée en fin de liste (ex. « Autre fonction (saisir…) »), valeur = $libreValeur ;
    - sans $libreOption, la liste contient déjà la valeur $libreValeur (ex. le type « autre » d'une structure).
--}}
@props(['nom', 'label', 'options', 'valeur' => null, 'vide' => null, 'aide' => null, 'requis' => false, 'donnees' => [], 'classe' => '',
        'libreChamp' => null, 'libreValeur' => '__autre__', 'libreOption' => null, 'libreLabel' => 'Précisez', 'libreValeurActuelle' => null,
        'parcours' => []])
@php
    $erreur = $errors->first($nom);
    $decrit = trim(($aide ? $nom.'-aide ' : '').($erreur ? $nom.'-erreur' : ''));
    $choisi = (string) old($nom, $valeur);
    $erreurLibre = $libreChamp ? $errors->first($libreChamp) : null;
    $libreVisible = $libreChamp && ($choisi === (string) $libreValeur);
@endphp
<div class="champ {{ $classe }} {{ $erreur ? 'champ--erreur' : '' }}">
    <label for="{{ $nom }}">{{ $label }}@if ($requis) <span class="requis" aria-hidden="true">*</span>@endif</label>
    <select id="{{ $nom }}" name="{{ $nom }}"
            @if ($requis) required @endif
            @if ($erreur) aria-invalid="true" @endif
            @if ($decrit) aria-describedby="{{ $decrit }}" @endif
            @if ($libreChamp) data-libre-champ="{{ $libreChamp }}" data-libre-valeur="{{ $libreValeur }}" @endif
            {{ $attributes }}>
        @if ($vide !== null) <option value="">{{ $vide }}</option> @endif
        @foreach ($options as $cle => $texte)
            @php $d = $donnees[$cle] ?? null; @endphp
            <option value="{{ $cle }}" @selected($choisi === (string) $cle)
                @if (is_array($d))
                    @foreach ($d as $attribut => $contenu) @if ($contenu !== null && $contenu !== '') data-{{ $attribut }}="{{ $contenu }}" @endif @endforeach
                @elseif ($d !== null) data-rattachement="{{ $d }}" @endif
                @if (isset($parcours[$cle])) data-parcours="{{ $parcours[$cle] }}" @endif>{{ $texte }}</option>
        @endforeach
        @if ($libreChamp && $libreOption)
            <option value="{{ $libreValeur }}" @selected($choisi === (string) $libreValeur)>{{ $libreOption }}</option>
        @endif
    </select>
    @if ($aide) <p class="champ__aide" id="{{ $nom }}-aide">{{ $aide }}</p> @endif
    @if ($erreur) <p class="champ__erreur" id="{{ $nom }}-erreur" role="alert">{{ $erreur }}</p> @endif

    @if ($libreChamp)
        <div class="champ__libre" id="{{ $libreChamp }}-zone" @unless ($libreVisible) hidden @endunless>
            <label for="{{ $libreChamp }}">{{ $libreLabel }}</label>
            <input id="{{ $libreChamp }}" name="{{ $libreChamp }}" type="text" maxlength="120" autocomplete="off"
                   value="{{ old($libreChamp, $libreValeurActuelle) }}" @if ($erreurLibre) aria-invalid="true" @endif>
            @if ($erreurLibre) <p class="champ__erreur" role="alert">{{ $erreurLibre }}</p> @endif
        </div>
    @endif
</div>
