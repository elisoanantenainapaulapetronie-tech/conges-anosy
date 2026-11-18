<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Continuité de la RH responsable : l'application ne doit jamais se retrouver sans personne capable d'attribuer les rôles
 * et de contre-valider. « Joignable » = rôle en cours + agent actif + compte actif (un rôle sans compte utilisable ne sert à rien).
 */
class ContinuiteService
{
    /** Nombre de RH responsables joignables, en excluant éventuellement un agent. */
    public static function responsablesJoignables(int $sauf = 0): int
    {
        return DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->join('agents', 'agents.id', '=', 'habilitations.agent_id')
            ->join('comptes', 'comptes.agent_id', '=', 'agents.id')
            ->where('roles.code', 'rh_responsable')
            ->where('agents.situation', 'actif')
            ->where('comptes.statut', 'actif')
            ->where('habilitations.agent_id', '!=', $sauf)
            ->whereDate('habilitations.date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()))
            ->distinct()->count('habilitations.agent_id');
    }

    /** Cet agent est-il, aujourd'hui, la seule RH responsable joignable ? */
    public static function estLeSeulResponsable(int $agentId): bool
    {
        return OrganisationService::aRoleActif($agentId, 'rh_responsable') && self::responsablesJoignables($agentId) === 0;
    }
}
