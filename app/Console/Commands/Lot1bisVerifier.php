<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\CircuitRegles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * php artisan lot1bis:verifier
 * Contrôle l'installation du lot 1 bis (schéma, paramètres, règles de circuit). Ne modifie rien.
 */
class Lot1bisVerifier extends Command
{
    protected $signature = 'lot1bis:verifier';

    protected $description = 'Vérifie l\'installation du lot 1 bis (règles de circuit) sans rien modifier';

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('--- Base de données ---');
        $this->controle('demande_etapes.motif_ecart', Schema::hasColumn('demande_etapes', 'motif_ecart'));
        $this->controle('demandes.hors_region', Schema::hasColumn('demandes', 'hors_region'));
        $this->controle('avis_prealables.hors_region', Schema::hasColumn('avis_prealables', 'hors_region'));
        $this->controle('4 paramètres de circuit', DB::table('parametres')->whereIn('cle', [
            'motif_obligatoire_si_avis_defavorable', 'directeur_avis_par', 'directeur_decision_par', 'mission_hors_region_decision_par',
        ])->count() === 4);

        $this->info('--- Règles (calcul, sans toucher aux données) ---');
        $sg = new Agent(['rattachement' => 'sg']);
        $region = new Agent(['rattachement' => 'region']);
        $this->controle('Agent du circuit SG : décision du SG', CircuitRegles::autoriteDecision($sg, 'conge') === 'sg');
        $this->controle('Agent du circuit Région : décision du Chef', CircuitRegles::autoriteDecision($region, 'conge') === 'region');
        $this->controle('Mission dans la Région (circuit SG) : SG', CircuitRegles::autoriteDecision($sg, 'mission', false) === 'sg');
        $this->controle('Mission hors Région (circuit SG) : Chef de Région', CircuitRegles::autoriteDecision($sg, 'mission', true) === 'region');
        $this->controle('Congé hors_region sans effet (circuit SG) : SG', CircuitRegles::autoriteDecision($sg, 'conge', true) === 'sg');

        try {
            CircuitRegles::controlerMotifEcart(0, 'refuse', null);
            $ok = true; // un refus n'exige pas de motif d'écart
        } catch (ValidationException) {
            $ok = false;
        }
        $this->controle('Aucun motif d\'écart exigé pour un refus', $ok);

        $this->info('--- Données (si les seeders de démonstration ont été lancés) ---');
        $directeurs = Agent::actifs()->get()->filter(fn ($a) => CircuitRegles::estDirecteur($a->id));
        if ($directeurs->isEmpty()) {
            $this->line('  [IGNORE] aucun directeur trouvé (seeders de démonstration non lancés ?)');
        } else {
            foreach ($directeurs as $d) {
                $this->controle("Directeur {$d->identifiant} : circuit « Chef de Région »", $d->rattachement === 'region');
            }
        }

        $sans = CircuitRegles::agentsSansResponsable();
        $perdus = CircuitRegles::agentsResponsableSansRole();
        $this->line('  [INFO] agents sans responsable pour l\'avis : '.$sans->count().' ; responsable sans rôle : '.$perdus->count());
        foreach ($sans->take(5) as $a) {
            $this->line("         - {$a->identifiant} {$a->nom_complet}");
        }

        $this->newLine();
        $this->echecs === 0 ? $this->info('Tout est conforme.') : $this->error($this->echecs.' contrôle(s) en échec.');

        return $this->echecs === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function controle(string $libelle, bool $ok): void
    {
        $this->line(($ok ? '  [OK]     ' : '  [ECHEC]  ').$libelle);
        if (! $ok) {
            $this->echecs++;
        }
    }
}
