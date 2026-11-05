<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Seul endroit à modifier pour brancher un vrai opérateur SMS.
 * Pour l'instant le message est écrit dans storage/logs/laravel.log (« [SMS simulé] »).
 */
class PasserelleSms
{
    public function envoyer(string $telephone, string $texte): bool
    {
        Log::info("[SMS simulé] vers {$telephone} : {$texte}");

        return true;
    }
}
