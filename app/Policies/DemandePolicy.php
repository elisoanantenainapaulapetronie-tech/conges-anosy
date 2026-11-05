<?php

namespace App\Policies;

use App\Models\Compte;
use App\Models\Demande;
use App\Policies\Concerns\IdentifieActeur;
use App\Models\Agent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Droits sur les demandes officielles. Chaque règle repose sur le rôle, le périmètre ET l'étape :
 * la décision n'est possible que si une étape « decision » en attente désigne la personne
 * (ou son délégant), et jamais pour sa propre demande, délégataire compris.
 */
class DemandePolicy
{
    use IdentifieActeur;

    private const AVANT_DECISION = ['brouillon', 'soumise', 'en_controle_rh', 'a_completer', 'en_attente_decision'];

    public function voir(Compte $compte, Demande $d): bool
    {
        $moi = $this->moi($compte);
        $ids = $this->identites($moi);

        if ((int) $d->agent_id === (int) $moi->id || $this->estRh($moi)) {
            return true;
        }

        $responsable = (int) DB::table('agents')->where('id', $d->agent_id)->value('responsable_id');
        if ($responsable && in_array($responsable, $ids, true)) {
            return true;
        }

        // Une étape du circuit (même déjà accomplie) désigne cette personne.
        return DB::table('demande_etapes')
            ->where('demande_id', $d->id)
            ->where(fn ($q) => $q->whereIn('acteur_id', $ids)->orWhereIn('agent_effectif_id', $ids))
            ->exists();
    }

    /** Le SG et le Chef ne déposent pas de demande : ils déclarent une indisponibilité. */
    public function creer(Compte $compte): bool
    {
        $moi = $this->moi($compte);

        return $moi->situation === 'actif' && ! $moi->aLeRole('secretaire_general', 'chef_region');
    }

    public function modifier(Compte $compte, Demande $d): bool
    {
        return $this->estLeDemandeur($compte, $d) && $d->statut === 'brouillon';
    }

    public function soumettre(Compte $compte, Demande $d): bool
    {
        return $this->modifier($compte, $d) && $this->creer($compte);
    }

    /** Répondre à un « À compléter » : dans le délai de 5 jours, sinon la demande est caduque. */
    public function completer(Compte $compte, Demande $d): bool
    {
        return $this->estLeDemandeur($compte, $d)
            && $d->statut === 'a_completer'
            && $d->reponse_attendue_avant !== null
            && Carbon::parse($d->reponse_attendue_avant)->isFuture();
    }

    /** Annulation simple avant la décision. */
    public function annuler(Compte $compte, Demande $d): bool
    {
        return $this->estLeDemandeur($compte, $d) && in_array($d->statut, self::AVANT_DECISION, true);
    }

    /** Après validation et avant le début du congé : simple DEMANDE d'annulation, à approuver. */
    public function demanderAnnulation(Compte $compte, Demande $d): bool
    {
        return $this->estLeDemandeur($compte, $d)
            && in_array($d->statut, ['validee', 'enregistree', 'planifiee'], true)
            && $d->annulation_demandee_le === null;
    }

    /**
     * Contrôle RH. Règles de séparation :
     *  - jamais sa propre demande ;
     *  - demande d'un responsable (Directeur, SG, Directeur de cabinet...) : RH responsable (contrôle renforcé) ;
     *  - demande du Chef du service RH : un RH gestionnaire désigné ;
     *  - demande d'un agent RH : un autre agent RH.
     */
    public function controler(Compte $compte, Demande $d): bool
    {
        $moi = $this->moi($compte);

        if (! $this->estRh($moi) || $d->statut !== 'en_controle_rh' || (int) $d->agent_id === (int) $moi->id) {
            return false;
        }

        $demandeur = Agent::find($d->agent_id);

        if ($demandeur->aLeRole('rh_responsable')) {
            return $moi->aLeRole('rh_gestionnaire');
        }
        if ($this->estResponsable($demandeur)) {
            return $moi->aLeRole('rh_responsable');
        }

        return true;
    }

    /** Décider (approuver, refuser, retourner) : étape « decision » en attente, jamais sa propre demande. */
    public function decider(Compte $compte, Demande $d): bool
    {
        $moi = $this->moi($compte);
        $ids = $this->identites($moi);

        return $d->statut === 'en_attente_decision'
            && ! in_array((int) $d->agent_id, $ids, true)
            && $this->etapeEnAttente($d->id, ['decision'], $ids);
    }

    /** Absence d'un jour au plus : approuvée ou refusée par le responsable direct. */
    public function approuverAbsenceCourte(Compte $compte, Demande $d): bool
    {
        $ids = $this->identites($this->moi($compte));

        return ! in_array((int) $d->agent_id, $ids, true)
            && $this->etapeEnAttente($d->id, ['approbation_responsable'], $ids);
    }

    /** Bouton « Pris connaissance » (maladie, maternité, paternité) : sans détail médical. */
    public function prendreConnaissance(Compte $compte, Demande $d): bool
    {
        $ids = $this->identites($this->moi($compte));

        return ! in_array((int) $d->agent_id, $ids, true)
            && $this->etapeEnAttente($d->id, ['information'], $ids);
    }

    /** Cas exceptionnels seulement (l'enregistrement normal est automatique) : RH, jamais les siennes. */
    public function enregistrerManuellement(Compte $compte, Demande $d): bool
    {
        $moi = $this->moi($compte);

        return $this->estRh($moi) && (int) $d->agent_id !== (int) $moi->id;
    }

    private function estLeDemandeur(Compte $compte, Demande $d): bool
    {
        return (int) $d->agent_id === (int) $compte->agent_id;
    }
}
