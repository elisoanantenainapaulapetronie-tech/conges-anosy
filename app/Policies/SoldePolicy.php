<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\Compte;
use App\Models\SoldeProposition;
use App\Policies\Concerns\IdentifieActeur;
use Illuminate\Support\Facades\DB;

/**
 * Soldes. Principe : personne n'écrit ni ne contrôle son propre solde.
 * Qui propose et qui contre-valide dépend de la personne concernée (dossier, partie 8.6).
 * Le double contrôle en deux temps (agent RH) sera orchestré par SoldeService ; ici : séparation et niveau.
 */
class SoldePolicy
{
    use IdentifieActeur;

    /** Voir un solde : le sien, tous pour la RH, ou celui d'un agent dont une demande est à l'étude chez moi. */
    public function voir(Compte $compte, Agent $cible): bool
    {
        $moi = $this->moi($compte);

        if ((int) $cible->id === (int) $moi->id || $this->estRh($moi)) {
            return true;
        }

        $ids = $this->identites($moi);

        return DB::table('demandes as d')
            ->join('demande_etapes as e', 'e.demande_id', '=', 'd.id')
            ->where('d.agent_id', $cible->id)
            ->whereIn('d.statut', ['soumise', 'en_controle_rh', 'a_completer', 'en_attente_decision'])
            ->where('e.statut', 'en_attente')
            ->whereIn('e.acteur_id', $ids)
            ->exists();
    }

    /** Proposer un solde initial, une correction, un report... pour $cible. */
    public function proposer(Compte $compte, Agent $cible): bool
    {
        $moi = $this->moi($compte);

        if (! $this->estRh($moi) || (int) $cible->id === (int) $moi->id) {
            return false;
        }

        if ($cible->aLeRole('rh_responsable')) {
            return $moi->aLeRole('rh_gestionnaire');          // Chef du service RH : un RH gestionnaire désigné
        }
        if ($this->estResponsable($cible)) {
            return $moi->aLeRole('rh_responsable');           // Directeurs, SG, Chef, Directeur de cabinet
        }

        return true;                                          // agent ordinaire ou agent RH : un autre agent RH
    }

    /** Contre-valider une proposition (ni l'auteur, ni la personne concernée). */
    public function controler(Compte $compte, SoldeProposition $p): bool
    {
        $moi = $this->moi($compte);

        if ($p->statut !== 'en_attente'
            || (int) $p->propose_par === (int) $moi->id
            || (int) $p->agent_id === (int) $moi->id) {
            return false;
        }

        $cible = Agent::findOrFail($p->agent_id);

        return match ($this->controleurAttendu($cible)) {
            'rh' => $this->estRh($moi),
            'rh_responsable' => $moi->aLeRole('rh_responsable'),
            'directeur_affaires_generales' => $this->estDirecteurAffairesGenerales($moi),
            'secretaire_general' => $moi->aLeRole('secretaire_general'),
            'chef_region' => $moi->aLeRole('chef_region'),
            default => false,    // autorité extérieure : décision externe enregistrée par la RH responsable
        };
    }

    /** Qui doit contre-valider un mouvement concernant $cible (utile aussi pour l'affichage). */
    public function controleurAttendu(Agent $cible): string
    {
        if ($cible->aLeRole('chef_region')) {
            return 'autorite_externe';                        // à définir par l'administration
        }
        if ($cible->aLeRole('secretaire_general')) {
            return 'chef_region';
        }
        if ($cible->aLeRole('rh_responsable')) {
            return 'directeur_affaires_generales';
        }
        if ($cible->fonction?->libelle === 'Directeur de cabinet') {
            return 'chef_region';
        }
        if ($this->estResponsable($cible)) {
            return 'secretaire_general';
        }
        if ($cible->aLeRole('rh_gestionnaire')) {
            return 'rh_responsable';                          // agent RH : un autre agent RH, puis la RH responsable
        }

        return 'rh';                                          // agent ordinaire : un autre agent RH
    }

    private function estDirecteurAffairesGenerales(Agent $a): bool
    {
        return $a->structure?->code === 'DAG' && $a->fonction?->libelle === 'Directeur';
    }
}
