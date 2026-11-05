<?php

namespace App\Policies;

use App\Models\AvisPrealable;
use App\Models\Compte;
use App\Models\Demande;
use App\Models\PieceJointe;
use App\Policies\Concerns\IdentifieActeur;
use Illuminate\Support\Facades\Gate;

/**
 * Lecture des pièces jointes.
 *  - Pièce médicale : son propriétaire et la RH habilitée UNIQUEMENT (ni responsable, ni SG, ni Chef,
 *    ni administrateur technique). Chaque lecture doit être journalisée : PieceMedicaleService.
 *  - Autre pièce : mêmes personnes que celles qui peuvent voir la demande ou l'avis.
 */
class PieceMedicalePolicy
{
    use IdentifieActeur;

    public function lire(Compte $compte, PieceJointe $piece): bool
    {
        $moi = $this->moi($compte);
        $demande = $piece->demande_id ? Demande::find($piece->demande_id) : null;
        $avis = $piece->avis_id ? AvisPrealable::find($piece->avis_id) : null;

        $proprietaireId = $demande?->agent_id ?? $avis?->agent_id;

        if ($piece->medicale) {
            return ($proprietaireId !== null && (int) $proprietaireId === (int) $moi->id)
                || $this->estRh($moi);
        }

        if ($demande) {
            return Gate::forUser($compte)->allows('voir', $demande);
        }
        if ($avis) {
            return Gate::forUser($compte)->allows('voir', $avis);
        }

        return $this->estRh($moi);   // pièce d'une proposition de solde
    }
}
