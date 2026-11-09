<?php

namespace App\Console\Commands;

use App\Exceptions\SoldeException;
use App\Models\Agent;
use App\Models\SoldeProposition;
use App\Services\DecompteService;
use App\Services\JoursFeriesService;
use App\Services\SoldeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * php artisan lot5:verifier
 * Contrôle l'installation du lot 5 (décompte, soldes, jours fériés) puis rejoue l'exemple fil rouge du dossier
 * dans une transaction ANNULÉE à la fin : rien n'est conservé dans la base.
 */
class Lot5Verifier extends Command
{
    protected $signature = 'lot5:verifier';

    protected $description = 'Vérifie l\'installation du lot 5 (décompte, soldes, jours fériés) sans rien conserver';

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('--- Base de données ---');
        $this->controle('soldes_propositions.reference_piece', Schema::hasColumn('soldes_propositions', 'reference_piece'));
        $this->controle('demandes.demi_journee_debut / demi_journee_fin (lot 0)', Schema::hasColumn('demandes', 'demi_journee_debut') && Schema::hasColumn('demandes', 'demi_journee_fin'));
        $this->controle('Modèle App\\Models\\SoldeProposition présent', class_exists(SoldeProposition::class));
        foreach ([2026, 2027, 2028] as $annee) {
            $n = DB::table('jours_feries')->whereYear('date_ferie', $annee)->count();
            $this->controle("Jours fériés {$annee} enregistrés ({$n})", $n >= 8);
        }
        $this->controle('Paramètre droit_annuel_jours défini', is_numeric(DB::table('parametres')->where('cle', 'droit_annuel_jours')->value('valeur')));

        $this->info('--- Routes ---');
        foreach (['solde', 'decompte.simuler', 'rh.soldes.index', 'rh.soldes.show', 'rh.soldes.proposer', 'rh.soldes.propositions', 'rh.soldes.controler',
            'rh.soldes.crediter', 'rh.jours-feries.index', 'rh.jours-feries.store', 'rh.jours-feries.remplir', 'rh.jours-feries.destroy'] as $nom) {
            $this->controle("route {$nom}", Route::has($nom));
        }

        $this->info('--- Décompte (calcul) ---');
        $feries2026 = JoursFeriesService::proposer(2026);
        $this->controle('Exemple fil rouge : 7 au 18 décembre 2026 = 10 jours', DecompteService::calculer('2026-12-07', '2026-12-18', 'journee', 'journee', $feries2026)['total'] === 10.0);
        $this->controle('23 au 27 mars : 5 jours ; vendredi férié : 4 jours',
            DecompteService::calculer('2026-03-23', '2026-03-27')['total'] === 5.0
            && DecompteService::calculer('2026-03-23', '2026-03-27', 'journee', 'journee', ['2026-03-27' => 'x'])['total'] === 4.0);
        $this->controle('Après-midi au premier jour + matin au dernier = 4 jours', DecompteService::calculer('2026-03-23', '2026-03-27', 'apres_midi', 'matin')['total'] === 4.0);
        $cheval = DecompteService::calculer('2026-12-28', '2027-01-05', 'journee', 'journee', ['2027-01-01' => 'x']);
        $this->controle('Période sur deux années : 4 jours en 2026, 2 en 2027', ($cheval['par_annee'][2026] ?? null) === 4.0 && ($cheval['par_annee'][2027] ?? null) === 2.0);
        $this->controle('Pâques 2026 = 5 avril ; Ascension = 14 mai', JoursFeriesService::paques(2026)->format('Y-m-d') === '2026-04-05' && isset($feries2026['2026-05-14']));
        $this->controle('Entré le 1er mars : 10 mois = 25 jours (droit 30)', SoldeService::droitAnnee('2026-03-01', null, 2026, 30.0) === 25.0);

        $this->info('--- Registre des soldes (rejoué dans une transaction annulée) ---');
        DB::beginTransaction();
        try {
            $this->scenario();
        } catch (Throwable $e) {
            $this->controle('Scénario terminé sans erreur : '.$e->getMessage(), false);
        } finally {
            DB::rollBack();
        }

        $this->newLine();
        $this->echecs === 0 ? $this->info('Tout est conforme (le scénario de test n\'a rien conservé).') : $this->error($this->echecs.' contrôle(s) en échec.');

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
            'matricule' => $m, 'nom' => 'TESTLOT5', 'prenoms' => $m, 'sexe' => 'M', 'structure_id' => $structure,
            'rattachement' => 'sg', 'date_entree' => $entree, 'situation' => 'actif',
        ]);
        $a = $nouveau('T5-A', '2024-01-01');   // agent concerné
        $c = $nouveau('T5-C', '2026-03-01');   // entré le 1er mars 2026
        $b = $nouveau('T5-B', '2020-01-01');   // propose
        $d = $nouveau('T5-D', '2020-01-01');   // contrôle

        // Droit annuel
        $droitA = SoldeService::droitAnnee('2024-01-01', null, 2026);
        $this->controle('Droit annuel crédité à A', SoldeService::crediterDroitAnnuel($a->id, 2026) === $droitA);
        $this->controle('Crédit du droit annuel : jamais deux fois', SoldeService::crediterDroitAnnuel($a->id, 2026) === null);
        $this->controle('Agent entré le 1er mars : 10 mois', SoldeService::crediterDroitAnnuel($c->id, 2026) === SoldeService::droitAnnee('2026-03-01', null, 2026));

        // Proposition puis contre-validation
        $id = SoldeService::proposer($b, $a, 2026, 'report', 5, 'Report de 2025', 'Décision de report n° T5');
        $this->controle('Proposition : le registre n\'a pas encore bougé', SoldeService::situation($a->id, 2026)['disponible'] === $droitA);
        $this->refuse('L\'auteur ne contrôle pas sa proposition', fn () => SoldeService::controler($b, $id, true));
        $this->refuse('La personne concernée ne contrôle pas sa proposition', fn () => SoldeService::controler($a, $id, true));
        $this->refuse('Personne ne propose sur son propre solde', fn () => SoldeService::proposer($a, $a, 2026, 'report', 1, 'Test auto', 'Réf'));
        $this->refuse('Un rejet exige un commentaire', fn () => SoldeService::controler($d, $id, false, ''));
        SoldeService::controler($d, $id, true);
        $this->controle('Validation : le report s\'ajoute au solde', SoldeService::situation($a->id, 2026)['disponible'] === $droitA + 5.0);
        $this->refuse('Une proposition déjà contrôlée ne se rejoue pas', fn () => SoldeService::controler($d, $id, true));
        $this->refuse('Solde initial refusé quand le droit annuel est crédité', fn () => SoldeService::proposer($b, $a, 2026, 'solde_initial', 12, 'Mise en service', 'Registre'));
        $this->refuse('Pas de pas de 0,25 jour', fn () => SoldeService::proposer($b, $a, 2026, 'correction', 1.25, 'Test pas', 'Réf'));
        $this->refuse('Un retrait ne peut pas dépasser le solde', fn () => SoldeService::proposer($b, $a, 2026, 'perte', 500, 'Test dépassement', null));

        $vue = DB::table('v_soldes_agents')->where('agent_id', $a->id)->where('annee', 2026)->first();
        $this->controle('La vue v_soldes_agents donne le même solde disponible', $vue && (float) $vue->solde_disponible === $droitA + 5.0);

        // Réservation, consommation, libération
        $d1 = $this->demande($a->id, $type, 'T5-0001', '2026-12-07', '2026-12-18');
        $avant = SoldeService::situation($a->id, 2026);
        $this->controle('Réservation de 10 jours', SoldeService::reserver($d1) === true);
        $s = SoldeService::situation($a->id, 2026);
        $this->controle('Réservé : disponible −10, réservé +10', $s['disponible'] === $avant['disponible'] - 10 && $s['reserves'] === 10.0);
        $this->refuse('Une demande ne se réserve pas deux fois', fn () => SoldeService::reserver($d1));
        $this->controle('Consommation : réservé → consommé, disponible inchangé',
            SoldeService::consommer($d1) === 10.0
            && ($t = SoldeService::situation($a->id, 2026))['reserves'] === 0.0 && $t['consommes'] === 10.0 && $t['disponible'] === $s['disponible']);
        $this->controle('Rien à libérer après consommation', SoldeService::liberer($d1) === 0.0);

        $d2 = $this->demande($a->id, $type, 'T5-0002', '2026-06-01', '2026-06-05');
        $avant = SoldeService::situation($a->id, 2026);
        SoldeService::reserver($d2);
        $this->controle('Libération : le disponible est rendu', SoldeService::liberer($d2, 'Annulation approuvée') === 5.0 && SoldeService::situation($a->id, 2026)['disponible'] === $avant['disponible']);

        $long = $this->demande($c->id, $type, 'T5-0003', '2026-03-02', '2026-12-31');
        $this->refuse('Solde insuffisant : réservation refusée', fn () => SoldeService::reserver($long));

        $controle = SoldeService::controlerSoumission($a->id, 'conge_annuel', '2026-09-01', '2026-10-06');
        $this->controle('Soumission de plus de 20 jours : dérogation', $controle['derogation'] === true && $controle['duree'] > 20);

        $registre = SoldeService::registre($a->id, 2026);
        $this->controle('Le registre se lit avec le solde après chaque ligne', count($registre) >= 5 && end($registre)['solde_apres'] === SoldeService::situation($a->id, 2026)['disponible']);

        try {
            DB::table('soldes_mouvements')->where('agent_id', $a->id)->update(['motif' => 'modifié']);
            $this->controle('Le registre est en écriture seule (déclencheur)', false);
        } catch (Throwable) {
            $this->controle('Le registre est en écriture seule (déclencheur)', true);
        }
    }

    private function demande(int $agentId, int $type, string $reference, string $debut, string $fin): int
    {
        $calcul = DecompteService::compter($debut, $fin);

        return DB::table('demandes')->insertGetId([
            'reference' => $reference, 'agent_id' => $agentId, 'type_demande_id' => $type,
            'date_debut' => $debut, 'date_fin' => $fin, 'duree_jours' => $calcul['total'], 'statut' => 'enregistree',
        ]);
    }

    /** Vrai si l'action est refusée par une SoldeException. */
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
