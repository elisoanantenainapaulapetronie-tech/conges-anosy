<?php

namespace App\Policies;

use App\Models\AvisPrealable;
use App\Models\Compte;
use App\Policies\Concerns\IdentifieActeur;
use Illuminate\Support\Carbon;

/** Avis préalable du responsable direct (consultatif) et autorisation de soumettre (7 jours). */
class AvisPolicy
{
    use IdentifieActeur;

    public function voir(Compte $compte, AvisPrealable $avis): bool
    {
        $moi = $this->moi($compte);

        return (int) $avis->agent_id === (int) $moi->id
            || in_array((int) $avis->responsable_id, $this->identites($moi), true)
            || $this->estRh($moi);
    }

    /** Le SG et le Chef ne demandent pas d'avis (ils déclarent une indisponibilité). */
    public function creer(Compte $compte): bool
    {
        $moi = $this->moi($compte);

        return $moi->situation === 'actif' && ! $moi->aLeRole('secretaire_general', 'chef_region');
    }

    /** L'agent peut retirer sa demande d'avis tant qu'elle n'est pas traitée. */
    public function retirer(Compte $compte, AvisPrealable $avis): bool
    {
        return (int) $avis->agent_id === (int) $compte->agent_id && $avis->statut === 'en_attente';
    }

    /** Donner l'avis : la personne désignée (ou son délégant), jamais pour sa propre demande. */
    public function repondre(Compte $compte, AvisPrealable $avis): bool
    {
        $ids = $this->identites($this->moi($compte));

        return $avis->statut === 'en_attente'
            && in_array((int) $avis->responsable_id, $ids, true)
            && ! in_array((int) $avis->agent_id, $ids, true);
    }

    /** Déposer la demande officielle à partir de l'avis : autorisation rendue, valable, non utilisée. */
    public function utiliser(Compte $compte, AvisPrealable $avis): bool
    {
        return (int) $avis->agent_id === (int) $compte->agent_id
            && $avis->statut === 'rendu'
            && $avis->autorisation_utilisee_le === null
            && $avis->autorisation_expire_le !== null
            && Carbon::parse($avis->autorisation_expire_le)->isFuture();
    }
}
