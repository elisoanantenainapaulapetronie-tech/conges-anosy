{{-- Cloche de la barre du haut : nombre de notifications non lues de la personne connectée. --}}
@php
    $nonLues = (int) \Illuminate\Support\Facades\DB::table('notifications')
        ->where('destinataire_id', auth()->user()->agent_id)->whereNull('lue_le')->count();
@endphp
<a href="{{ route('notifications') }}" class="cloche" data-compteur-url="{{ route('notifications.compteur') }}" data-non-lues="{{ $nonLues }}" title="Mes notifications"
   aria-label="Mes notifications{{ $nonLues > 0 ? ' : '.$nonLues.' non lue'.($nonLues > 1 ? 's' : '') : '' }}"
   @if (request()->routeIs('notifications*')) aria-current="page" @endif>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
    @if ($nonLues > 0) <span class="cloche__compteur">{{ $nonLues > 99 ? '99+' : $nonLues }}</span> @endif
</a>
@once
    <script src="{{ asset('js/notifications.js') }}" defer></script>
@endonce
