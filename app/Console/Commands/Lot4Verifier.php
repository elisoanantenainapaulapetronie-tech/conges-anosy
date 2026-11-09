<?php

namespace App\Console\Commands;

use App\Services\OrganigrammeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * php artisan lot4:verifier
 * Contrôle l'installation du lot 4 (organigramme évolutif et postes). Ne modifie rien.
 */
class Lot4Verifier extends Command
{
    protected $signature = 'lot4:verifier';

    protected $description = 'Vérifie l\'installation du lot 4 (organigramme, postes) sans rien modifier';

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('--- Base de données ---');
        $this->controle('table postes', Schema::hasTable('postes'));
        if (Schema::hasTable('postes')) {
            foreach (['structure_id', 'intitule', 'fonction_id', 'titulaire_id', 'note', 'actif'] as $col) {
                $this->controle("postes.{$col}", Schema::hasColumn('postes', $col));
            }
            $relations = (int) (DB::selectOne(
                'SELECT COUNT(*) AS n FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['postes'])->n ?? 0);
            $this->controle('postes : 3 relations (structure, fonction, titulaire)', $relations === 3);
        }

        $this->info('--- Routes et vues ---');
        foreach (['organigramme', 'rh.postes.create', 'rh.postes.store', 'rh.postes.edit', 'rh.postes.update', 'rh.postes.retirer'] as $nom) {
            $this->controle("route {$nom}", Route::has($nom));
        }
        foreach (['organigramme.index', 'organigramme._carte', 'organigramme._noeud', 'rh.postes.form'] as $vue) {
            $this->controle("vue {$vue}", View::exists($vue));
        }
        foreach (['css/organigramme.css', 'js/organigramme.js'] as $fichier) {
            $this->controle("fichier public/{$fichier}", is_file(public_path($fichier)));
        }

        if (! Schema::hasTable('postes')) {
            return $this->fin();
        }

        $this->info('--- Arbre calculé ---');
        $arbre = OrganigrammeService::construire(true);
        $ids = [];
        $collecter = function (array $noeuds) use (&$collecter, &$ids) {
            foreach ($noeuds as $n) {
                $ids[] = $n['id'];
                $collecter($n['enfants']);
            }
        };
        $collecter($arbre['racines']);

        $total = DB::table('structures')->count();
        $this->controle("toutes les structures sont dans l'arbre ({$total})", count($ids) === $total);
        $this->controle('aucune structure en double dans l\'arbre', count($ids) === count(array_unique($ids)));
        $this->controle('aucune boucle dans la hiérarchie', $arbre['orphelines'] === []);

        $sansInactives = OrganigrammeService::construire(false);
        $this->controle('sans les désactivées : seules des structures actives restent comptées',
            $sansInactives['stats']['structures'] === DB::table('structures')->where('actif', 1)->count());

        $effectifArbre = array_sum(array_column($arbre['racines'], 'effectif_total'));
        $effectifBase = DB::table('agents')->where('situation', 'actif')->whereNotNull('structure_id')->count();
        $this->controle("effectifs de l'arbre = agents actifs affectés ({$effectifBase})", $effectifArbre === $effectifBase);

        $this->info('--- Données (information) ---');
        $this->line('  [INFO] Chef de Région en fonction : '.(count($arbre['chefs']) ?: 'aucun'));
        $this->line('  [INFO] structures actives : '.$arbre['stats']['structures'].' ; agents actifs : '.$arbre['stats']['agents']);
        $this->line('  [INFO] structures sans responsable : '.$arbre['stats']['sans_responsable'].' ; postes vacants : '.$arbre['stats']['postes_vacants']);
        $this->line('  [INFO] postes enregistrés : '.DB::table('postes')->where('actif', 1)->count());

        return $this->fin();
    }

    private function fin(): int
    {
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
