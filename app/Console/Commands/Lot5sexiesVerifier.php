<?php

namespace App\Console\Commands;

use App\Exceptions\SoldeException;
use App\Models\Agent;
use App\Services\ImputationService;
use App\Services\SoldeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * php artisan lot5sexies:verifier
 * Contrôle le lot 5 sexies : reprise de l'historique et paramètre anticipation_max_jours.
 * Le scénario s'exécute dans une transaction ANNULÉE : rien n'est conservé.
 */
class Lot5sexiesVerifier extends Command
{
    protected $signature = 'lot5sexies:verifier';

    protected $description = 'Vérifie le lot 5 sexies (reprise de l\'historique, anticipation_max_jours) sans rien conserver';

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('--- Base de données et routes ---');
        $enum = (string) (DB::selectOne("SHOW COLUMNS FROM soldes_propositions LIKE 'nature'")->Type ?? '');
        $this->controle('Nature « reprise_historique » présente', str_contains($enum, 'reprise_historique'));
        $this->controle('Paramètre anticipation_max_jours = 0 (aucune anticipation en V1)', (string) DB::table('parametres')->where('cle', 'anticipation_max_jours')->value('valeur') === '0');
        $ancien = DB::table('parametres')->where('cle', 'anticipation_autorisee')->value('valeur');
        $this->controle('Ancien paramètre anticipation_autorisee absent ou à 0'.($ancien === '1' ? ' (ATTENTION : vaut 1, à examiner)' : ''), $ancien === null || (string) $ancien === '0');
        $source = (string) file_get_contents((new \ReflectionClass(ImputationService::class))->getFileName());
        $this->controle('Le calcul ne lit plus anticipation_autorisee', ! str_contains($source, 'anticipation_autorisee'));
        $this->controle('Route rh.soldes.reprise', Route::has('rh.soldes.reprise'));
        $mw = implode(',', Route::getRoutes()->getByName('rh.soldes.reprise')?->gatherMiddleware() ?? []);
        $this->controle('La reprise est réservée à la RH (auth + rôle)', str_contains($mw, 'auth') && str_contains($mw, 'role:rh_gestionnaire,rh_responsable'));
        $this->controle('Une reprise est contre-validée par la RH responsable', SoldeService::niveauSupplementaire('reprise_historique', 'rh') === 'rh_responsable');

        $this->info('--- Reprise de l\'historique (transaction annulée) ---');
        DB::beginTransaction();
        try {
            $this->scenario();
        } catch (Throwable $e) {
            $this->controle('Scénario terminé sans erreur : '.$e->getMessage(), false);
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        $this->echecs === 0 ? $this->info('Tout est conforme (rien n\'a été conservé).') : $this->error($this->echecs.' contrôle(s) en échec.');

        return $this->echecs === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function scenario(): void
    {
        $structure = DB::table('structures')->value('id');
        if (! $structure) {
            $this->line('  [IGNORE] Aucune structure : lancer les seeders de démonstration.');

            return;
        }
        $nouveau = fn (string $m) => Agent::create([
            'matricule' => $m, 'nom' => 'TESTLOT5SEXIES', 'prenoms' => $m, 'sexe' => 'M', 'structure_id' => $structure,
            'rattachement' => 'sg', 'date_entree' => '2020-01-01', 'situation' => 'actif',
        ]);
        $cible = $nouveau('T5S-A');
        $auteur = $nouveau('T5S-B');
        $controleur = $nouveau('T5S-C');

        // Exemple de l'analyse : lots 2024 (4) et 2025 (6) en solde initial, lot 2026 crédité (30), 12 jours déjà pris.
        foreach ([2024 => 4, 2025 => 6] as $annee => $jours) {
            $id = SoldeService::proposer($auteur, $cible, $annee, 'solde_initial', $jours, "Reliquat {$annee} conservé dans son lot", 'Registre des congés');
            SoldeService::controler($controleur, $id, true, 'Conforme');
        }
        DB::table('soldes_mouvements')->insert([
            'agent_id' => $cible->id, 'annee' => 2026, 'type_mouvement' => 'droit_annuel', 'delta_disponible' => 30,
            'delta_reserve' => 0, 'delta_consomme' => 0, 'motif' => 'Droit annuel 2026 (test)', 'created_at' => now(),
        ]);

        $this->controle('Répartition FIFO de 12 jours : 4 sur 2024, 6 sur 2025, 2 sur 2026',
            SoldeService::repartirReprise($cible->id, 12.0) === [2024 => 4.0, 2025 => 6.0, 2026 => 2.0]);
        $this->controle('Répartition FIFO de 3 jours : tout sur le lot le plus ancien', SoldeService::repartirReprise($cible->id, 3.0) === [2024 => 3.0]);
        $this->refuse('Un total supérieur aux lots crédités est refusé', fn () => SoldeService::repartirReprise($cible->id, 100.0));
        $this->refuse('Un total qui n\'est pas un multiple de 0,5 est refusé', fn () => SoldeService::repartirReprise($cible->id, 2.3));

        $this->refuse('Personne ne propose une reprise sur son propre solde', fn () => SoldeService::proposerReprise($cible, $cible, [2024 => 1.0], 'Reprise de test', 'Registre'));
        $this->refuse('Le document de référence est obligatoire', fn () => SoldeService::proposerReprise($auteur, $cible, [2024 => 1.0], 'Reprise de test', ''));
        $this->refuse('Aucun jour déclaré : refus', fn () => SoldeService::proposerReprise($auteur, $cible, [2024 => 0.0], 'Reprise de test', 'Registre'));
        $this->refuse('Un lot ne peut pas devenir négatif (5 jours sur un lot qui en a 4)', fn () => SoldeService::proposerReprise($auteur, $cible, [2024 => 5.0], 'Reprise de test', 'Registre'));

        $ids = SoldeService::proposerReprise($auteur, $cible, [2024 => 4.0, 2025 => 6.0, 2026 => 2.0], 'Reprise des congés pris en 2026', 'Registre des congés 2026');
        $this->controle('Une proposition par lot débité (3)', count($ids) === 3);
        $this->controle('Rien n\'a bougé avant le contrôle', SoldeService::situation($cible->id, 2024)['disponible'] === 4.0);
        $this->refuse('L\'auteur ne contrôle pas sa propre reprise', fn () => SoldeService::controler($auteur, $ids[0], true, 'Moi'));
        foreach ($ids as $id) {
            SoldeService::controler($controleur, $id, true, 'Conforme au registre');
        }

        $s24 = SoldeService::situation($cible->id, 2024);
        $s25 = SoldeService::situation($cible->id, 2025);
        $s26 = SoldeService::situation($cible->id, 2026);
        $this->controle('Lot 2024 : 0 disponible, 4 consommés', $s24['disponible'] === 0.0 && $s24['consommes'] === 4.0);
        $this->controle('Lot 2025 : 0 disponible, 6 consommés', $s25['disponible'] === 0.0 && $s25['consommes'] === 6.0);
        $this->controle('Lot 2026 : 28 disponibles, 2 consommés', $s26['disponible'] === 28.0 && $s26['consommes'] === 2.0);

        $m = DB::table('soldes_mouvements')->where('agent_id', $cible->id)->where('annee', 2026)->where('type_mouvement', 'consommation')->first();
        $this->controle('Mouvement de type « consommation » : disponible −2, consommé +2, réservé 0, rattaché à sa proposition',
            $m !== null && (float) $m->delta_disponible === -2.0 && (float) $m->delta_consomme === 2.0 && (float) $m->delta_reserve === 0.0 && $m->proposition_id !== null);
        $this->controle('Le motif du mouvement indique la reprise', $m !== null && str_starts_with((string) $m->motif, 'Reprise de l’historique'));
        $this->controle('Aucune alerte de seuil pour une reprise (seules les corrections alertent)',
            ! DB::table('notifications')->where('type_evenement', 'solde_correction_importante')->exists());

        $total = round((float) DB::table('soldes_mouvements')->where('agent_id', $cible->id)->sum('delta_disponible'), 2);
        $this->controle('Total de l\'agent : 4 + 6 + 30 − 12 = 28', $total === 28.0);
        $this->controle('La reprise reste ouverte tant qu\'aucune demande n\'est réservée', ! SoldeService::repriseFermee((int) $cible->id));
    }

    private function refuse(string $libelle, \Closure $action): void
    {
        try {
            $action();
            $this->controle($libelle, false);
        } catch (SoldeException) {
            $this->controle($libelle, true);
        }
    }

    private function controle(string $libelle, bool $ok): void
    {
        $this->line(($ok ? '  [OK]     ' : '  [ECHEC]  ').$libelle);
        if (! $ok) {
            $this->echecs++;
        }
    }
}
