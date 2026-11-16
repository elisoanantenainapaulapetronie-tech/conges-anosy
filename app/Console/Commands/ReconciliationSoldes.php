<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan conges:reconcilier
 * Contrôle d'intégrité du registre des soldes. Ne modifie rien.
 *   1. aucun lot (agent + année) n'est négatif : ni disponible, ni réservé, ni consommé ;
 *   2. toute proposition validée a bien produit un mouvement au registre ;
 *   3. tout mouvement rattaché à une proposition renvoie vers une proposition existante ;
 *   4. (lot 5 quinquies) une proposition rejetée, retirée ou en attente n'a produit aucun mouvement ;
 *   5. (lot 5 quinquies) aucune année n'est créditée deux fois : solde initial ET acquisitions mensuelles pour le même agent et la même année.
 * À lancer avant une démonstration, ou chaque nuit (voir routes/console.php).
 */
class ReconciliationSoldes extends Command
{
    protected $signature = 'conges:reconcilier';

    protected $description = 'Vérifie la cohérence du registre des soldes (lots non négatifs, propositions validées inscrites)';

    public function handle(): int
    {
        $anomalies = 0;

        $lots = DB::table('soldes_mouvements')
            ->select('agent_id', 'annee',
                DB::raw('SUM(delta_disponible) AS disponible'),
                DB::raw('SUM(delta_reserve) AS reserve'),
                DB::raw('SUM(delta_consomme) AS consomme'))
            ->groupBy('agent_id', 'annee')
            ->get();

        $negatifs = $lots->filter(fn ($l) => (float) $l->disponible < 0 || (float) $l->reserve < 0 || (float) $l->consomme < 0);
        foreach ($negatifs as $l) {
            $this->line("  [ANOMALIE] Agent {$l->agent_id}, lot {$l->annee} : disponible {$l->disponible}, réservé {$l->reserve}, consommé {$l->consomme}");
        }
        $anomalies += $negatifs->count();
        $this->bilan('Aucun lot négatif ('.$lots->count().' lots contrôlés)', $negatifs->isEmpty());

        $sansMouvement = DB::table('soldes_propositions as p')
            ->leftJoin('soldes_mouvements as m', 'm.proposition_id', '=', 'p.id')
            ->where('p.statut', 'valide')
            ->whereNull('m.id')
            ->select('p.id', 'p.agent_id', 'p.nature')
            ->get();
        foreach ($sansMouvement as $p) {
            $this->line("  [ANOMALIE] Proposition {$p->id} ({$p->nature}, agent {$p->agent_id}) validée sans mouvement au registre");
        }
        $anomalies += $sansMouvement->count();
        $this->bilan('Toute proposition validée est inscrite au registre', $sansMouvement->isEmpty());

        $orphelins = DB::table('soldes_mouvements as m')
            ->leftJoin('soldes_propositions as p', 'p.id', '=', 'm.proposition_id')
            ->whereNotNull('m.proposition_id')
            ->whereNull('p.id')
            ->select('m.id', 'm.agent_id')
            ->get();
        foreach ($orphelins as $m) {
            $this->line("  [ANOMALIE] Mouvement {$m->id} (agent {$m->agent_id}) rattaché à une proposition inexistante");
        }
        $anomalies += $orphelins->count();
        $this->bilan('Aucun mouvement orphelin', $orphelins->isEmpty());

        $faux = DB::table('soldes_propositions as p')
            ->join('soldes_mouvements as m', 'm.proposition_id', '=', 'p.id')
            ->where('p.statut', '!=', 'valide')
            ->select('p.id', 'p.statut', 'p.agent_id')
            ->get();
        foreach ($faux as $p) {
            $this->line("  [ANOMALIE] Proposition {$p->id} ({$p->statut}, agent {$p->agent_id}) a pourtant produit un mouvement");
        }
        $anomalies += $faux->count();
        $this->bilan('Seules les propositions validées ont produit un mouvement', $faux->isEmpty());

        $doubles = DB::table('soldes_mouvements as i')
            ->join('soldes_mouvements as q', fn ($j) => $j->on('q.agent_id', '=', 'i.agent_id')->on('q.annee', '=', 'i.annee'))
            ->where('i.type_mouvement', 'solde_initial')
            ->where('q.type_mouvement', 'droit_annuel')
            ->select('i.agent_id', 'i.annee')->distinct()
            ->get();
        foreach ($doubles as $d) {
            $this->line("  [ANOMALIE] Agent {$d->agent_id}, année {$d->annee} : solde initial ET droit annuel (double crédit possible)");
        }
        $anomalies += $doubles->count();
        $this->bilan('Aucune année créditée deux fois (solde initial + acquisitions)', $doubles->isEmpty());

        $reports = (int) DB::table('soldes_mouvements')->where('type_mouvement', 'report')->count();
        $this->line('  [INFO]     '.$reports.' ancien(s) mouvement(s) « report » conservé(s) pour l\'historique (le registre ne se modifie jamais)');

        $this->newLine();
        $this->line($anomalies === 0 ? 'Registre cohérent.' : "{$anomalies} anomalie(s) : à examiner par la RH responsable.");

        return $anomalies === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function bilan(string $libelle, bool $ok): void
    {
        $this->line(($ok ? '  [OK]       ' : '  [ANOMALIE] ').$libelle);
    }
}
