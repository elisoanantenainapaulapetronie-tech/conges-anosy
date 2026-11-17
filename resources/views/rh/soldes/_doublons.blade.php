{{-- Avertissement permanent « d'autres propositions attendent déjà » (en plus du message affiché juste après un envoi).
     $autres : nombre de propositions concernées ; $annee : année (null = toutes années, pour la reprise). --}}
@if (($autres ?? 0) > 0)
    <div class="alerte alerte--info" role="status">
        @if ($annee)
            Attention : {{ $autres }} autre(s) proposition(s) attendent déjà leur contrôle pour cet agent et l’année {{ $annee }}. Vérifiez qu’il ne s’agit pas d’un doublon ; vous pouvez retirer l’une d’elles.
        @else
            Attention : {{ $autres }} proposition(s) attendent déjà leur contrôle pour cet agent. Vérifiez qu’il ne s’agit pas d’un doublon.
        @endif
    </div>
@endif
