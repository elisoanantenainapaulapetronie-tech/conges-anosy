<?php

namespace App\Console\Commands;

use App\Http\Middleware\InactiviteSession;
use App\Models\Agent;
use App\Services\CircuitRegles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * php artisan lot3:verifier
 * Contrôle l'installation du lot 3 (3 autorités, fonctions libres, organisation, inactivité 30 min). Ne modifie rien.
 */
class Lot3Verifier extends Command
{
    protected $signature = 'lot3:verifier';

    protected $description = 'Vérifie l\'installation du lot 3 (autorités, fonctions, organisation, inactivité) sans rien modifier';

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('--- Base de données ---');
        foreach (['agents', 'structures', 'affectations_historique'] as $table) {
            $type = (string) (DB::selectOne(
                'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, 'rattachement'])->t ?? '');
            $this->controle("{$table}.rattachement accepte « sg_region »", str_contains($type, 'sg_region'));
        }
        $this->controle('structures.type_precision', Schema::hasColumn('structures', 'type_precision'));
        foreach (['categorie', 'structure_id', 'rattachement', 'actif'] as $col) {
            $this->controle("fonctions.{$col}", Schema::hasColumn('fonctions', $col));
        }
        $this->controle('Fonctions de l\'organigramme présentes (>= 30)', DB::table('fonctions')->count() >= 30);
        $this->controle('Services de l\'organigramme présents (>= 13 services)', DB::table('structures')->where('type', 'service')->count() >= 13);

        $this->info('--- Circuits de décision (calcul) ---');
        $this->controle('« sg » : une étape (SG)', CircuitRegles::ETAPES['sg'] === ['sg']);
        $this->controle('« region » : une étape (Chef de Région)', CircuitRegles::ETAPES['region'] === ['region']);
        $this->controle('« sg_region » : SG PUIS Chef de Région', CircuitRegles::ETAPES['sg_region'] === ['sg', 'region']);
        $les2 = new Agent(['rattachement' => 'sg_region']);
        $this->controle('Agent « les deux » : étapes [sg, region]', CircuitRegles::etapesDecision($les2, 'conge') === ['sg', 'region']);
        $this->controle('Agent « les deux » : le SG intervient', CircuitRegles::intervient('sg_region', 'sg'));
        $this->controle('Agent « les deux » : le Chef intervient', CircuitRegles::intervient('sg_region', 'region'));
        $this->controle('Agent « sg » : le Chef n\'intervient pas', ! CircuitRegles::intervient('sg', 'region'));
        $this->controle('Parcours « les deux » : 2 décisions', substr_count(CircuitRegles::parcours('sg_region'), 'Décision du') === 2);

        $this->info('--- Inactivité ---');
        $this->controle('Délai d\'inactivité = 30 minutes', (int) config('conges.inactivite_minutes') === 30);
        $this->controle('Durée de session alignée sur l\'inactivité', (int) config('session.lifetime') === (int) config('conges.inactivite_minutes'));
        $groupe = app('router')->getMiddlewareGroups()['web'] ?? [];
        $this->controle('Middleware d\'inactivité actif dans le groupe « web »', in_array(InactiviteSession::class, $groupe, true));

        $this->info('--- Routes ---');
        foreach (['rh.fonctions.index', 'rh.fonctions.create', 'rh.fonctions.store', 'rh.fonctions.edit', 'rh.fonctions.update', 'rh.fonctions.etat'] as $nom) {
            $this->controle("route {$nom}", Route::has($nom));
        }

        $this->info('--- Données ---');
        $this->controle('Aucun agent avec une autorité inconnue', DB::table('agents')->whereNotIn('rattachement', ['sg', 'region', 'sg_region'])->count() === 0);
        $this->line('  [INFO] agents « les deux » : '.DB::table('agents')->where('rattachement', 'sg_region')->count());

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
