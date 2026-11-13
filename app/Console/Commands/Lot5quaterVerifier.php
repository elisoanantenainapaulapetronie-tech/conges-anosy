<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\SoldeProposition;
use App\Policies\SoldePolicy;
use App\Support\NombreSaisi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/**
 * php artisan lot5quater:verifier
 * Contrôle le lot 5 quater : lecture des nombres saisis, règles « le gestionnaire propose, le responsable valide »,
 * soldes des agents RH validés par le Secrétaire Général, file de contrôle ouverte aux bonnes personnes.
 * Ne modifie rien dans la base.
 */
class Lot5quaterVerifier extends Command
{
    protected $signature = 'lot5quater:verifier';

    protected $description = 'Vérifie le lot 5 quater (saisie des jours, répartition gestionnaire/responsable, file de contrôle)';

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('--- Lecture des nombres de jours ---');
        foreach ([['2,5', '2.5'], ['2.5', '2.5'], ['-1,5', '-1.5'], ["\u{2212}1,5", '-1.5'], ['+3', '3'], [' 1 000 ', '1000'], ['0,5', '0.5']] as [$entree, $attendu]) {
            $this->controle("« {$entree} » est lu {$attendu}", NombreSaisi::normaliser($entree) === $attendu);
        }
        foreach (['abc', '', '1,2,3', '2,5,', '--1', '1e3', null] as $mauvais) {
            $this->controle('« '.var_export($mauvais, true).' » est refusé', NombreSaisi::normaliser($mauvais) === null);
        }

        $this->info('--- Routes ---');
        foreach (['rh.soldes.propositions', 'rh.soldes.controler', 'rh.soldes.proposer', 'rh.soldes.show'] as $nom) {
            $this->controle("Route {$nom}", Route::has($nom));
        }
        $mw = implode(',', Route::getRoutes()->getByName('rh.soldes.propositions')?->gatherMiddleware() ?? []);
        $this->controle('La file de contrôle est ouverte au SG et au Chef de Région', str_contains($mw, 'secretaire_general') && str_contains($mw, 'chef_region'));
        $mw = implode(',', Route::getRoutes()->getByName('rh.soldes.proposer')?->gatherMiddleware() ?? []);
        $this->controle('Proposer reste réservé aux profils RH', ! str_contains($mw, 'secretaire_general'));

        $this->info('--- Règles de proposition et de contrôle (comptes de démonstration) ---');
        $soa = Agent::where('identifiant', 'AGT-0050')->first();      // RH responsable
        $tahina = Agent::where('identifiant', 'AGT-0188')->first();   // RH gestionnaire
        $hery = Agent::where('identifiant', 'AGT-0231')->first();     // agent simple
        $jean = Agent::where('identifiant', 'DIR-0011')->first();     // directeur
        $sg = Agent::where('identifiant', 'SG-0001')->first();
        if (! $soa?->compte || ! $tahina?->compte || ! $hery || ! $jean || ! $sg?->compte) {
            $this->line('  [IGNORE] Comptes de démonstration absents (lancer les seeders).');
        } else {
            $p = new SoldePolicy();
            $this->controle('Le gestionnaire propose pour un agent simple', $p->proposer($tahina->compte, $hery));
            $this->controle('Le responsable ne propose pas pour un agent simple', ! $p->proposer($soa->compte, $hery));
            $this->controle('Le gestionnaire propose pour la RH responsable', $p->proposer($tahina->compte, $soa));
            $this->controle('La RH responsable propose pour un gestionnaire', $p->proposer($soa->compte, $tahina));
            $this->controle('Personne ne propose sur son propre solde', ! $p->proposer($tahina->compte, $tahina) && ! $p->proposer($soa->compte, $soa));

            $this->controle('Solde d\'un agent simple : validé par la RH responsable', $p->controleurAttendu($hery) === 'rh_responsable');
            $this->controle('Solde de la RH responsable : validé par le Secrétaire Général', $p->controleurAttendu($soa) === 'secretaire_general');
            $this->controle('Solde d\'un gestionnaire : validé par le Secrétaire Général', $p->controleurAttendu($tahina) === 'secretaire_general');
            $this->controle('Solde d\'un directeur : validé par le Secrétaire Général', $p->controleurAttendu($jean) === 'secretaire_general');

            $propo = fn (int $cible, int $auteur) => (new SoldeProposition())->forceFill(['statut' => 'en_attente', 'agent_id' => $cible, 'propose_par' => $auteur]);
            $this->controle('La RH responsable valide la proposition du gestionnaire sur Hery', $p->controler($soa->compte, $propo($hery->id, $tahina->id)));
            $this->controle('Le gestionnaire ne valide pas sa propre proposition', ! $p->controler($tahina->compte, $propo($hery->id, $tahina->id)));
            $this->controle('Le gestionnaire ne valide pas à la place de la RH responsable', ! $p->controler($tahina->compte, $propo($hery->id, $soa->id)));
            $this->controle('Le SG valide le solde de la RH responsable proposé par le gestionnaire', $p->controler($sg->compte, $propo($soa->id, $tahina->id)));
            $this->controle('La RH responsable ne valide pas son propre solde', ! $p->controler($soa->compte, $propo($soa->id, $tahina->id)));
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
