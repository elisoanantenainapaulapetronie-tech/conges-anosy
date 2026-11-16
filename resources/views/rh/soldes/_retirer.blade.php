{{-- Retrait d'une proposition par son auteur : motif obligatoire. Variable : $p --}}
<details class="retrait">
    <summary class="discret">Retirer cette proposition</summary>
    <form method="POST" action="{{ route('rh.soldes.retirer', $p->id) }}" data-envoi>
        @csrf
        <div class="champ">
            <label for="retrait-{{ $p->id }}">Pourquoi la retirez-vous ? <span class="requis" aria-hidden="true">*</span></label>
            <input id="retrait-{{ $p->id }}" name="motif_retrait" type="text" required minlength="3" maxlength="500">
            <p class="champ__aide">Le solde n’a pas changé. La proposition reste dans l’historique avec votre motif.</p>
        </div>
        <button type="submit" class="bouton bouton--ligne bouton--danger">Retirer la proposition</button>
    </form>
</details>
