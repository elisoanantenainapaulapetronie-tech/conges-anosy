<?php

namespace App\Console\Commands;

use App\Exceptions\SoldeException;
use App\Models\Agent;
use App\Services\AcquisitionService;
use App\Services\DecompteService;
use App\Services\ImputationService;
use App\Services\SoldeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * php artisan lot5ter:verifier
 * Contrôle l'installation du lot 5 ter (acquisition mensuelle, lots, imputation) puis rejoue des scénarios dans une
 * transaction ANNULÉE à la fin : rien n'est conservé dans la base.
 */
class Lot5terVerifier extends Command
{
    protected $signature = 'lot5ter:verifier';

    protected $description = 'Vérifie l\'installation du lot 5 ter (acquisition mensuelle, lots par année, imputation) sans rien conserver';

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('--- Base de données ---');
        $this->controle('soldes_mouvements.periode', Schema::hasColumn('soldes_mouvements', 'periode'));
        $index = DB::select("SHOW INDEX FROM soldes_mouvements WHERE Key_name = 'uq_acquisition_mensuelle'");
        $this->controle('Index unique (agent, période) : un seul crédit par mois', count($index) === 2);
        $this->controle('Table demande_imputations', Schema::hasTable('demande_imputations'));
        foreach (['acquisition_debut_mois', 'anticipation_autorisee', 'solde_insuffisant_mode', 'reliquat_validite_annees'] as $cle) {
            $this->controle("Paramètre {$cle} présent", DB::table('parametres')->where('cle', $cle)->exists());
        }
        $this->controle('Reliquats : aucune expiration (reliquat_validite_annees vide)', (string) DB::table('parametres')->where('cle', 'reliquat_validite_annees')->value('valeur') === '');
        $this->controle('Commande conges:acquisition enregistrée', array_key_exists('conges:acquisition', Artisan::all()));

        $this->info('--- Règles (calcul pur) ---');
        $this->controle('Entré le 1er mars : acquisition dès mars', AcquisitionService::premierMois('2026-03-01') === '2026-03');
        $this->controle('Entré le 15 novembre : acquisition dès décembre', AcquisitionService::premierMois('2026-11-15') === '2026-12');
        $this->controle('Mensualité = 30 / 12 = 2,5 jours', AcquisitionService::mensualite(30.0) === 2.5);

        $feries = ['2026-12-25' => 'Noël', '2027-01-01' => 'Jour de l’An'];
        $jours = fn (string $d, string $f) => ImputationService::joursDecomptes(DecompteService::calculer($d, $f, 'journee', 'journee', $feries));
        $proj = [2027 => ['2027-01' => 2.5, '2027-02' => 2.5]];
        $r = ImputationService::repartir($jours('2026-12-28', '2027-01-08'), [2025 => 3.0, 2026 => 10.0], $proj);
        $this->controle('Plus ancien d\'abord : 3 jours sur 2025, 6 sur 2026, rien sur 2027', ($r['lots'][2025]['total'] ?? 0) === 3.0 && ($r['lots'][2026]['total'] ?? 0) === 6.0 && ! isset($r['lots'][2027]));
        $r = ImputationService::repartir($jours('2026-12-28', '2027-01-18'), [2026 => 12.0], $proj);
        $this->controle('Solde insuffisant : manque 0,5 jour, couvert jusqu\'au 15/01/2027, 2,5 jours provisoires',
            $r['manque'] === 0.5 && $r['couvert_jusqu'] === '2027-01-15' && ($r['lots'][2027]['provisoire'] ?? 0) === 2.5);
        $r = ImputationService::repartir($jours('2026-12-07', '2026-12-08'), [2027 => 30.0]);
        $this->controle('Un lot 2027 ne couvre jamais un jour de 2026', $r['manque'] === 2.0);

        $this->info('--- Scénarios (rejoués dans une transaction annulée) ---');
        DB::beginTransaction();
        try {
            $this->scenario();
        } catch (Throwable $e) {
            $this->controle('Scénario terminé sans erreur : '.$e->getMessage().' ('.basename($e->getFile()).':'.$e->getLine().')', false);
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        $this->echecs === 0 ? $this->info('Tout est conforme (les scénarios n\'ont rien conservé).') : $this->error($this->echecs.' contrôle(s) en échec.');

        return $this->echecs === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function scenario(): void
    {
        $structure = DB::table('structures')->value('id');
        $type = DB::table('types_demande')->where('code', 'conge_annuel')->value('id');
        if (! $structure || ! $type) {
            $this->line('  [IGNORE] structures ou types de demande absents : lancer les seeders de démonstration.');

            return;
        }
        $nouveau = fn (string $m, string $entree) => Agent::create([
            'matricule' => $m, 'nom' => 'TESTLOT5TER', 'prenoms' => $m, 'sexe' => 'M', 'structure_id' => $structure,
            'rattachement' => 'sg', 'date_entree' => $entree, 'situation' => 'actif',
        ]);
        $x1 = $nouveau('T5T-1', '2020-01-01');   // entré depuis longtemps
        $x2 = $nouveau('T5T-2', '2026-03-15');   // entré en milieu de mois
        $x3 = $nouveau('T5T-3', '2026-03-01');   // entré le 1er du mois
        $x5 = $nouveau('T5T-5', '2020-01-01');   // plusieurs lots
        $x7 = $nouveau('T5T-7', '2020-01-01');   // jours différés
        $x8 = $nouveau('T5T-8', '2020-01-01');   // simulation avec manque
        $b = $nouveau('T5T-B', '2020-01-01');    // propose
        $d = $nouveau('T5T-D', '2020-01-01');    // contrôle

        // Acquisition mensuelle
        $r = AcquisitionService::rattraper('2026-06', $x1->id);
        $this->controle('Acquisition jusqu\'à juin 2026 : 6 mois, 15 jours', $r['mois'] === 6 && $r['jours'] === 15.0 && SoldeService::situation($x1->id, 2026)['disponible'] === 15.0);
        $r = AcquisitionService::rattraper('2026-06', $x1->id);
        $this->controle('Relancée : aucun doublon', $r['mois'] === 0 && SoldeService::situation($x1->id, 2026)['disponible'] === 15.0);
        try {
            DB::table('soldes_mouvements')->insert(['agent_id' => $x1->id, 'annee' => 2026, 'periode' => '2026-06', 'type_mouvement' => 'droit_annuel',
                'delta_disponible' => 2.5, 'delta_reserve' => 0, 'delta_consomme' => 0, 'created_at' => now()]);
            $this->controle('La base refuse un second crédit du même mois', false);
        } catch (Throwable) {
            $this->controle('La base refuse un second crédit du même mois', true);
        }
        AcquisitionService::rattraper('2026-06', $x2->id);
        $this->controle('Entré le 15 mars : avril, mai, juin = 3 mois = 7,5 jours', SoldeService::situation($x2->id, 2026)['disponible'] === 7.5);
        AcquisitionService::rattraper('2026-06', $x3->id);
        $this->controle('Entré le 1er mars : mars à juin = 4 mois = 10 jours', SoldeService::situation($x3->id, 2026)['disponible'] === 10.0);
        $reg = SoldeService::registre($x1->id, 2026);
        $this->controle('Le registre porte « Acquisition mensuelle »', ($reg[0]['libelle'] ?? '') === 'Acquisition mensuelle' && ($reg[0]['periode'] ?? '') === '2026-01');

        // Année fixée par la RH : l'acquisition ne la double pas
        $id = SoldeService::proposer($b, $x8, 2026, 'solde_initial', 12, 'Reprise du registre papier', 'Registre des congés 2026');
        SoldeService::controler($d, $id, true);
        $r = AcquisitionService::rattraper('2026-06', $x8->id);
        $this->controle('Solde initial 2026 : l\'acquisition mensuelle ne touche pas cette année', $r['mois'] === 0 && SoldeService::situation($x8->id, 2026)['disponible'] === 12.0);
        $r = AcquisitionService::rattraper('2027-02', $x8->id);
        $this->controle('… mais 2027 est acquis mois par mois (janvier, février = 5 jours)', $r['mois'] === 2 && SoldeService::situation($x8->id, 2027)['disponible'] === 5.0);

        // Plusieurs lots : le plus ancien d'abord
        $id = SoldeService::proposer($b, $x5, 2025, 'solde_initial', 3, 'Reliquat 2025', 'Registre 2025');
        SoldeService::controler($d, $id, true);
        AcquisitionService::rattraper('2026-12', $x5->id);
        $this->controle('Lots : 2025 = 3 jours, 2026 = 30 jours', ImputationService::lots($x5->id) === [2025 => 3.0, 2026 => 30.0]);
        $dm = $this->demande($x5->id, $type, 'T5T-0001', '2026-12-28', '2027-01-08');
        $this->controle('Réservation de 9 jours sur deux lots', SoldeService::reserver($dm) === true);
        $l2025 = SoldeService::situation($x5->id, 2025);
        $l2026 = SoldeService::situation($x5->id, 2026);
        $this->controle('Lot 2025 épuisé en premier (3 jours réservés)', $l2025['disponible'] === 0.0 && $l2025['reserves'] === 3.0);
        $this->controle('Lot 2026 : 6 jours réservés, 24 disponibles', $l2026['reserves'] === 6.0 && $l2026['disponible'] === 24.0);
        $this->controle('Une ligne de registre par lot, même numéro de demande', DB::table('soldes_mouvements')->where('demande_id', $dm)->where('type_mouvement', 'reservation')->count() === 2);
        $this->controle('Imputations conservées (2 lots)', DB::table('demande_imputations')->where('demande_id', $dm)->count() === 2);
        $this->controle('Consommation : 9 jours pris', SoldeService::consommer($dm) === 9.0 && SoldeService::situation($x5->id, 2026)['consommes'] === 6.0);

        // Jours différés : couverts par le crédit de janvier 2027
        $id = SoldeService::proposer($b, $x7, 2026, 'solde_initial', 1, 'Solde de départ', 'Registre 2026');
        SoldeService::controler($d, $id, true);
        $d7 = $this->demande($x7->id, $type, 'T5T-0007', '2027-01-04', '2027-01-06');
        $this->controle('Demande de 3 jours : 1 jour du lot 2026, 2 jours à acquérir en janvier', SoldeService::reserver($d7) === true);
        $this->controle('Seul 1 jour est réservé au registre (aucun lot négatif)', SoldeService::situation($x7->id, 2026)['reserves'] === 1.0 && ! isset(ImputationService::lots($x7->id)[2027]));
        $this->controle('2 jours sont différés', (float) DB::table('demande_imputations')->where('demande_id', $d7)->where('differee', 1)->sum('jours') === 2.0);
        $this->refuse('Consommer des jours pas encore acquis est refusé', fn () => SoldeService::consommer($d7));
        $this->controle('Crédit de janvier 2027 : 2,5 jours', AcquisitionService::acquerirMois($x7->id, '2027-01') === 2.5);
        $s27 = SoldeService::situation($x7->id, 2027);
        $this->controle('Les jours différés sont réservés : lot 2027 = 0,5 disponible, 2 réservés', $s27['disponible'] === 0.5 && $s27['reserves'] === 2.0);
        $this->controle('Plus aucun jour différé', DB::table('demande_imputations')->where('demande_id', $d7)->where('differee', 1)->count() === 0);

        $d7b = $this->demande($x7->id, $type, 'T5T-0008', '2027-01-07', '2027-01-08');
        try {
            SoldeService::reserver($d7b);
            $this->controle('Solde insuffisant : réservation refusée', false);
        } catch (SoldeException $e) {
            $this->controle('Solde insuffisant : réservation refusée avec le manque et la date atteignable', str_contains($e->getMessage(), 'il manque') && str_contains($e->getMessage(), 'couvre'));
        }

        // Simulation
        $calcul = DecompteService::compter('2026-12-28', '2027-01-18');
        $sim = ImputationService::simuler($x8->id, $calcul);
        $this->controle('Simulation (lot 2026 = 12, 2027 = 5) : 15 jours couverts, rien de provisoire', $calcul['total'] === 15.0 && $sim['suffisant'] === true && $sim['provisoire'] === 0.0);
        $x9 = $nouveau('T5T-9', '2020-01-01');
        $id = SoldeService::proposer($b, $x9, 2026, 'solde_initial', 12, 'Reprise du registre papier', 'Registre des congés 2026');
        SoldeService::controler($d, $id, true);
        $sim = ImputationService::simuler($x9->id, $calcul);
        $this->controle('Simulation (12 jours en 2026) : manque 0,5, couvert jusqu\'au 15/01/2027, 2,5 provisoires',
            $sim['suffisant'] === false && $sim['manque'] === 0.5 && $sim['couvert_jusqu'] === '2027-01-15' && $sim['provisoire'] === 2.5 && $sim['bloquant'] === true);
        $controle = SoldeService::controlerSoumission($x9->id, 'conge_annuel', '2026-12-28', '2027-01-18');
        $this->controle('Contrôle à la soumission : insuffisant et bloquant', $controle['suffisant'] === false && $controle['bloquant'] === true);
        $tab = ImputationService::tableau($x5->id);
        $this->controle('Tableau « Mon solde » : un lot par année, le plus ancien consommé en premier', count($tab['lignes']) >= 2 && $tab['premier_lot'] !== null);
    }

    private function demande(int $agentId, int $type, string $reference, string $debut, string $fin): int
    {
        $calcul = DecompteService::compter($debut, $fin);

        return DB::table('demandes')->insertGetId([
            'reference' => $reference, 'agent_id' => $agentId, 'type_demande_id' => $type,
            'date_debut' => $debut, 'date_fin' => $fin, 'duree_jours' => $calcul['total'], 'statut' => 'enregistree',
        ]);
    }

    private function refuse(string $libelle, callable $action): void
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
