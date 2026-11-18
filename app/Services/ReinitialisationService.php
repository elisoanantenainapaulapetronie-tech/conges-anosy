<?php

namespace App\Services;

use App\Models\Compte;
use App\Models\ReinitialisationMotDePasse;
use Illuminate\Support\Str;

/**
 * Création d'un lien de réinitialisation de mot de passe (à usage unique ; seule l'empreinte SHA-256 est stockée).
 * Un seul lien reste valable à la fois par compte. Utilisé par « mot de passe oublié » (60 min),
 * par le lien remis par la RH et par la commande de secours (durée choisie par l'appelant).
 */
class ReinitialisationService
{
    /** @return string le jeton en clair (à n'afficher qu'une fois) */
    public static function creerJeton(Compte $compte, int $validiteMinutes = 60): string
    {
        ReinitialisationMotDePasse::where('compte_id', $compte->id)->whereNull('utilise_le')
            ->where('expire_le', '>', now())->update(['expire_le' => now()]);

        $jeton = Str::random(64);
        ReinitialisationMotDePasse::create([
            'compte_id' => $compte->id,
            'jeton_hash' => hash('sha256', $jeton),
            'expire_le' => now()->addMinutes($validiteMinutes),
        ]);

        return $jeton;
    }
}
