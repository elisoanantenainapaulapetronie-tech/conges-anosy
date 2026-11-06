<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Petits outils communs à l'espace RH : codes d'activation, détenteurs de rôles, hiérarchies.
 */
class OrganisationService
{
    /** Code d'activation de 8 caractères, sans lettres ambiguës (0/O, 1/I/L). */
    public static function codeActivation(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    /** Nombre d'AUTRES agents actifs qui détiennent aujourd'hui ce rôle. */
    public static function autresDetenteurs(string $codeRole, int $sauf): int
    {
        return DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->join('agents', 'agents.id', '=', 'habilitations.agent_id')
            ->where('roles.code', $codeRole)
            ->where('habilitations.agent_id', '!=', $sauf)
            ->where('agents.situation', 'actif')
            ->whereDate('habilitations.date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()))
            ->count();
    }

    /** Cet agent détient-il ce rôle aujourd'hui ? */
    public static function aRoleActif(int $agentId, string $codeRole): bool
    {
        return DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->where('habilitations.agent_id', $agentId)
            ->where('roles.code', $codeRole)
            ->whereDate('habilitations.date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()))
            ->exists();
    }

    /** Tous les agents placés (directement ou non) sous la responsabilité de cet agent. */
    public static function subordonnes(int $agentId): array
    {
        $trouves = [];
        $niveau = [$agentId];
        while ($niveau) {
            $enfants = DB::table('agents')->whereIn('responsable_id', $niveau)->pluck('id')->map(fn ($v) => (int) $v)->all();
            $enfants = array_values(array_diff($enfants, $trouves, [$agentId]));
            $trouves = array_merge($trouves, $enfants);
            $niveau = $enfants;
        }

        return $trouves;
    }

    /** Toutes les structures placées sous cette structure. */
    public static function structuresEnfants(int $structureId): array
    {
        $trouvees = [];
        $niveau = [$structureId];
        while ($niveau) {
            $enfants = DB::table('structures')->whereIn('parent_id', $niveau)->pluck('id')->map(fn ($v) => (int) $v)->all();
            $enfants = array_values(array_diff($enfants, $trouvees, [$structureId]));
            $trouvees = array_merge($trouvees, $enfants);
            $niveau = $enfants;
        }

        return $trouvees;
    }
}
