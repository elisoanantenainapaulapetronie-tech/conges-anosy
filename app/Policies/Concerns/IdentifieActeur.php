<?php

namespace App\Policies\Concerns;

use App\Models\Agent;
use App\Models\Compte;
use Illuminate\Support\Facades\DB;

/** Outils communs aux policies : qui est la personne, pour qui peut-elle agir, quel rôle a-t-elle. */
trait IdentifieActeur
{
    /**
     * Identifiants des personnes POUR LESQUELLES $moi peut agir aujourd'hui :
     * lui-même, plus les délégants dont il est le délégataire (délégation en cours).
     */
    protected function identites(Agent $moi): array
    {
        $delegants = DB::table('delegations')
            ->where('delegataire_id', $moi->id)
            ->where('statut', 'active')
            ->whereDate('date_debut', '<=', today())
            ->whereDate('date_fin', '>=', today())
            ->pluck('delegant_id')->map(fn ($v) => (int) $v)->all();

        return array_values(array_unique([(int) $moi->id, ...$delegants]));
    }

    protected function estRh(Agent $a): bool
    {
        return $a->aLeRole('rh_gestionnaire', 'rh_responsable');
    }

    /** Responsable hiérarchique, SG ou Chef : leurs demandes suivent un contrôle renforcé. */
    protected function estResponsable(Agent $a): bool
    {
        return $a->aLeRole('responsable_hierarchique', 'secretaire_general', 'chef_region');
    }

    /** Une étape du circuit attend-elle l'une de ces personnes ? (étapes figées à la soumission) */
    protected function etapeEnAttente(int $demandeId, array $roleEtapes, array $identites): bool
    {
        return DB::table('demande_etapes')
            ->where('demande_id', $demandeId)
            ->where('statut', 'en_attente')
            ->whereIn('role_etape', $roleEtapes)
            ->whereIn('acteur_id', $identites)
            ->exists();
    }

    protected function moi(Compte $compte): Agent
    {
        return $compte->agent;
    }
}
