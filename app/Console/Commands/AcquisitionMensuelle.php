<?php

namespace App\Console\Commands;

use App\Services\AcquisitionService;
use App\Services\DecompteService;
use Illuminate\Console\Command;

/**
 * php artisan conges:acquisition                    crédite jusqu'au mois en cours (planifié le 1er de chaque mois)
 * php artisan conges:acquisition --jusqua=2026-06   rattrape les mois manquants jusqu'à juin 2026 inclus
 * php artisan conges:acquisition --agent=5          un seul agent
 *
 * Crédite 2,5 jours par mois (droit annuel / 12) aux agents actifs. Sans danger si relancée : un mois déjà crédité, une
 * année fixée en bloc par la RH (solde initial) ou un mois non acquis (entrée en cours de mois, sortie) est ignoré.
 */
class AcquisitionMensuelle extends Command
{
    protected $signature = 'conges:acquisition {--jusqua= : Dernier mois à créditer (AAAA-MM), par défaut le mois en cours} {--agent= : Identifiant interne d\'un seul agent}';

    protected $description = 'Crédite l\'acquisition mensuelle des congés (rattrape les mois manquants sans rien doubler)';

    public function handle(): int
    {
        $jusqua = $this->option('jusqua') ?: date('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $jusqua)) {
            $this->error('Le mois doit être au format AAAA-MM, par exemple 2026-06.');

            return self::INVALID;
        }

        $agent = $this->option('agent') !== null ? (int) $this->option('agent') : null;
        $r = AcquisitionService::rattraper((string) $jusqua, $agent);
        $this->info("Acquisition jusqu'à {$r['jusqua']} : {$r['mois']} mois crédité(s) pour {$r['agents']} agent(s), soit ".DecompteService::libelle($r['jours']).'.');

        return self::SUCCESS;
    }
}
