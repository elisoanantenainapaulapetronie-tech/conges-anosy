<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\Compte;
use App\Policies\Concerns\IdentifieActeur;

/** Fiches, comptes et habilitations. La fonction n'est jamais un droit : seuls les rôles comptent. */
class AgentPolicy
{
    use IdentifieActeur;

    /** Lire une fiche : la sienne, celle de ses agents (limité), son circuit (SG, Chef), ou tout pour la RH. */
    public function voir(Compte $compte, Agent $cible): bool
    {
        $moi = $this->moi($compte);

        return (int) $cible->id === (int) $moi->id
            || $this->estRh($moi)
            || in_array((int) $cible->responsable_id, $this->identites($moi), true)
            || ($moi->aLeRole('secretaire_general') && $cible->rattachement === 'sg')
            || ($moi->aLeRole('chef_region') && $cible->rattachement === 'region');
    }

    public function voirListe(Compte $compte): bool
    {
        return $this->estRh($this->moi($compte));
    }

    public function creer(Compte $compte): bool
    {
        return $this->estRh($this->moi($compte));
    }

    /** Modifier une fiche : la RH, jamais sa propre fiche (rattachement et responsable décident du circuit). */
    public function modifier(Compte $compte, Agent $cible): bool
    {
        $moi = $this->moi($compte);

        return $this->estRh($moi) && (int) $cible->id !== (int) $moi->id;
    }

    public function genererCodeActivation(Compte $compte, Agent $cible): bool
    {
        $moi = $this->moi($compte);

        return $this->estRh($moi)
            && (int) $cible->id !== (int) $moi->id
            && $cible->compte?->statut !== 'actif';
    }

    /** Lien de réinitialisation remis par la RH (agent sans e-mail ni téléphone). */
    public function genererLienReinitialisation(Compte $compte, Agent $cible): bool
    {
        $moi = $this->moi($compte);

        return $this->estRh($moi) && (int) $cible->id !== (int) $moi->id && $cible->compte?->statut === 'actif';
    }

    public function changerEtatCompte(Compte $compte, Agent $cible): bool
    {
        return $this->modifier($compte, $cible);
    }

    /** Attribuer ou retirer un rôle : RH responsable uniquement, jamais à soi-même. */
    public function attribuerRole(Compte $compte, Agent $cible): bool
    {
        $moi = $this->moi($compte);

        return $moi->aLeRole('rh_responsable') && (int) $cible->id !== (int) $moi->id;
    }
}
