@props(['agent', 'taille' => 'sm', 'alt' => ''])
@php
    $px = ['sm' => 38, 'md' => 56, 'lg' => 112][$taille] ?? 38;
    $initiales = mb_strtoupper(mb_substr($agent->prenoms ?: $agent->nom, 0, 1).mb_substr($agent->nom, 0, 1));
    $version = $agent->photo_mise_a_jour_le ? \Illuminate\Support\Carbon::parse($agent->photo_mise_a_jour_le)->timestamp : 0;
@endphp
@once
    <link rel="stylesheet" href="{{ asset('css/avatar.css') }}">
@endonce
@if ($agent->photo_chemin)
    <img {{ $attributes->class(['avatar', 'avatar--'.$taille]) }}
         src="{{ route('photo.afficher', $agent) }}?v={{ $version }}" alt="{{ $alt }}"
         width="{{ $px }}" height="{{ $px }}" loading="lazy" decoding="async">
@else
    <span {{ $attributes->class(['avatar', 'avatar--'.$taille]) }}
          @if ($alt !== '') role="img" aria-label="{{ $alt }}" @else aria-hidden="true" @endif>{{ $initiales }}</span>
@endif
