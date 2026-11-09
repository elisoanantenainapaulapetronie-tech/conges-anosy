<?php

namespace App\Console\Commands;

use App\Services\DecompteService;
use App\Services\SoldeService;
use Illuminate\Console\Command;

/**
 * php artisan conges:crediter-droits 2026
 * Crédite le droit annuel (droit / 12 par mois entier travaillé) de tous les agents actifs. Peut être relancé sans danger :
 * un agent déjà crédité, ou qui a un solde initial pour l'année, est ignoré.
 */
class CrediterDroits extends Command
{
    protected $signature = 'conges:crediter-droits {annee? : Année à créditer (par défaut l\'année en cours)}';

    protected $description = 'Crédite le droit annuel de congé des agents actifs pour une année';

    public function handle(): int
    {
        $annee = (int) ($this->argument('annee') ?: date('Y'));
        $r = SoldeService::crediterTous($annee);
        $this->info("Droit annuel {$annee} : {$r['credites']} agent(s) crédité(s) pour ".DecompteService::libelle($r['jours']).", {$r['ignores']} ignoré(s).");

        return self::SUCCESS;
    }
}
