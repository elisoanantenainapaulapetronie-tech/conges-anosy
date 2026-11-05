<?php

namespace App\Services;

use App\Models\Compte;
use App\Models\PieceJointe;
use Illuminate\Support\Facades\DB;

/**
 * Chaque lecture d'une pièce médicale est journalisée dans consultations_pieces
 * (non modifiable, avec adresse IP). À appeler APRÈS le contrôle PieceMedicalePolicy::lire.
 */
class PieceMedicaleService
{
    public function enregistrerConsultation(PieceJointe $piece, Compte $compte): void
    {
        if (! $piece->medicale) {
            return;
        }

        DB::table('consultations_pieces')->insert([
            'piece_id' => $piece->id,
            'agent_id' => $compte->agent_id,
            'consulte_le' => now(),
            'adresse_ip' => request()->ip(),
        ]);
    }
}
