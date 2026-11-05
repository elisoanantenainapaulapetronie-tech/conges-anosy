<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Point d'entrée unique du journal d'audit (table audit_logs, non modifiable).
 * L'auteur et l'adresse IP sont repris automatiquement de la requête en cours.
 *
 *   AuditService::enregistrer('demande_soumise', $demande, null, ['statut' => 'soumise']);
 *   AuditService::refus('decision_refusee', $demande, ['raison' => 'auto-validation']);
 */
class AuditService
{
    public static function enregistrer(
        string $action,
        ?Model $objet = null,
        ?array $avant = null,
        ?array $apres = null,
        bool $refuse = false,
    ): AuditLog {
        return AuditLog::create([
            'auteur_id' => auth()->user()?->agent_id,
            'action' => $action,
            'objet_type' => $objet ? class_basename($objet) : null,
            'objet_id' => $objet?->getKey(),
            'valeur_avant' => $avant,
            'valeur_apres' => $apres,
            'refuse' => $refuse,
            'adresse_ip' => request()->ip(),
        ]);
    }

    /** Action refusée par le serveur : inscrite avec refuse = 1. */
    public static function refus(string $action, ?Model $objet = null, array $details = []): AuditLog
    {
        return static::enregistrer($action, $objet, null, $details ?: null, true);
    }
}
