{{-- Liens vers les justificatifs d'une proposition. Variable : $liste (pièces de la proposition). Droit vérifié à l'ouverture. --}}
@foreach ($liste as $piece)
    <a class="discret" href="{{ route('rh.soldes.justificatif', $piece->id) }}" target="_blank" rel="noopener">
        📎 {{ $piece->nom_original }} ({{ \App\Services\SoldeJustificatifService::taille((int) $piece->taille_octets) }})
    </a>
@endforeach
