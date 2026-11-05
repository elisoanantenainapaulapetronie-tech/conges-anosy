<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Demande;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan lot0:verifier
 * Contrôle que le lot 0 est bien installé : schéma, déclencheurs, paramètres et droits des agents de démonstration.
 * Ne modifie rien dans la base.
 */
class Lot0Verifier extends Command
{
    protected $signature = 'lot0:verifier';

    protected $description = 'Vérifie l\'installation du lot 0 (schéma, droits) sans rien modifier';

    private int $reussis = 0;

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('--- Base de données ---');
        $this->controle('Vue v_soldes_agents : colonne identifiant', Schema::hasColumn('v_soldes_agents', 'identifiant'));
        $this->controle('Table demande_versions', Schema::hasTable('demande_versions'));
        $this->controle('Demandes : demi-journées', Schema::hasColumns('demandes', ['demi_journee_debut', 'demi_journee_fin']));
        $this->controle('Avis : demi-journées', Schema::hasColumns('avis_prealables', ['demi_journee_debut', 'demi_journee_fin']));
        $this->controle('Indisponibilités : proposition_id', Schema::hasColumn('indisponibilites', 'proposition_id'));
        $this->controle('Déclencheurs d\'écriture seule (10 attendus)', count(DB::select('SHOW TRIGGERS')) >= 10);
        $this->controle('Paramètres ajoutés (2)', DB::table('parametres')
            ->whereIn('cle', ['duree_code_activation_jours', 'seuil_alerte_absences_multiples'])->count() === 2);

        $this->info('--- Droits (agents de démonstration) ---');
        $this->droit('AGT-0231', 'creer', Demande::class, true, 'Hery (agent) peut déposer une demande');
        $this->droit('SG-0001', 'creer', Demande::class, false, 'Le SG ne dépose pas de demande');
        $this->droit('CR-0001', 'creer', Demande::class, false, 'Le Chef de Région ne dépose pas de demande');
        $this->droit('AGT-0231', 'voirListe', Agent::class, false, 'Hery ne voit pas la liste des agents');
        $this->droit('AGT-0188', 'voirListe', Agent::class, true, 'Tahina (RH gestionnaire) voit la liste des agents');
        $this->droit('AGT-0188', 'attribuerRole', 'AGT-0231', false, 'Tahina (RH gestionnaire) n\'attribue pas de rôle');
        $this->droit('AGT-0050', 'attribuerRole', 'AGT-0231', true, 'Soa (RH responsable) attribue un rôle');
        $this->droit('AGT-0050', 'attribuerRole', 'AGT-0050', false, 'Personne ne s\'attribue un rôle à soi-même');
        $this->droit('AGT-0188', 'solde.proposer', 'AGT-0231', true, 'Tahina propose un solde pour Hery');
        $this->droit('AGT-0188', 'solde.proposer', 'AGT-0188', false, 'Personne ne propose son propre solde');
        $this->droit('AGT-0188', 'solde.proposer', 'AGT-0104', false, 'Solde d\'une cheffe de service : RH responsable seulement');
        $this->droit('AGT-0050', 'solde.proposer', 'AGT-0104', true, 'Soa propose le solde d\'une cheffe de service');
        $this->droit('AGT-0231', 'solde.voir', 'AGT-0188', false, 'Hery ne voit pas le solde de Tahina');
        $this->droit('AGT-0231', 'solde.voir', 'AGT-0231', true, 'Hery voit son propre solde');

        $this->newLine();
        $this->line("Réussis : {$this->reussis}   Échecs : {$this->echecs}");

        return $this->echecs === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function controle(string $libelle, bool $ok): void
    {
        $ok ? $this->reussis++ : $this->echecs++;
        $this->line(($ok ? '  [OK]     ' : '  [ECHEC]  ').$libelle);
    }

    /** $objet : une classe (ex. Demande::class) ou l'identifiant d'un agent cible (ex. 'AGT-0231'). */
    private function droit(string $identifiant, string $droit, string $objet, bool $attendu, string $libelle): void
    {
        $compte = Agent::where('identifiant', $identifiant)->first()?->compte;
        if (! $compte) {
            $this->line("  [IGNORE] {$libelle} (compte {$identifiant} introuvable : lancez les seeders de démonstration)");

            return;
        }

        $argument = class_exists($objet) ? $objet : Agent::where('identifiant', $objet)->first();
        if ($argument === null) {
            $this->line("  [IGNORE] {$libelle} (agent {$objet} introuvable)");

            return;
        }

        $this->controle($libelle, Gate::forUser($compte)->allows($droit, $argument) === $attendu);
    }
}
