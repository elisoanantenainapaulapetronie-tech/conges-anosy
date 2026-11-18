<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Compte;

/**
 * Émission d'un code d'activation (compte créé ou régénéré). Le code est renvoyé UNE fois, jamais stocké en clair ni journalisé.
 * Partagé par la RH (CompteController) et par l'installation (conges:installer).
 */
class CodeActivationService
{
    /** @return array{code:string, expire:\Illuminate\Support\Carbon, nouveau:bool, avant:?array} */
    public static function emettre(Agent $agent): array
    {
        $code = OrganisationService::codeActivation();
        $expire = now()->addDays(Parametres::entier('duree_code_activation_jours', 5))->endOfDay();

        $compte = Compte::firstOrNew(['agent_id' => $agent->id]);
        $nouveau = ! $compte->exists;
        $avant = $nouveau ? null : ['statut' => $compte->statut];

        $compte->forceFill([
            'statut' => 'en_attente_activation',
            'mot_de_passe_hash' => null,
            'code_activation_hash' => bcrypt($code),
            'code_activation_expire_le' => $expire,
            'echecs_connexion' => 0,
            'bloque_jusqua' => null,
        ])->save();

        return ['code' => $code, 'expire' => $expire, 'nouveau' => $nouveau, 'avant' => $avant];
    }
}
