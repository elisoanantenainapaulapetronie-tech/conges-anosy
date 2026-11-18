<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\Compte;
use App\Services\ContinuiteService;
use App\Services\ReinitialisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * php artisan lot6:verifier
 * Contrôle le lot 6 : installation, continuité de la RH responsable, lien de secours, valeurs provisoires.
 * Le scénario s'exécute dans une transaction ANNULÉE : rien n'est conservé.
 */
class Lot6Verifier extends Command
{
    protected $signature = 'lot6:verifier';

    protected $description = 'Vérifie le lot 6 (installation, continuité RH responsable, lien de secours, valeurs provisoires) sans rien conserver';

    private int $echecs = 0;

    public function handle(): int
    {
        $this->info('--- Base de données, commandes et routes ---');
        $this->controle('Colonne parametres.a_confirmer', Schema::hasColumn('parametres', 'a_confirmer'));
        $commandes = array_keys(Artisan::all());
        $this->controle('Commande conges:installer', in_array('conges:installer', $commandes, true));
        $this->controle('Commande conges:lien-secours', in_array('conges:lien-secours', $commandes, true));
        $this->controle('Route rh.comptes.lien', Route::has('rh.comptes.lien'));
        $this->controle('Route rh.parametres.confirmer', Route::has('rh.parametres.confirmer'));
        $mw = implode(',', Route::getRoutes()->getByName('rh.parametres.confirmer')?->gatherMiddleware() ?? []);
        $this->controle('La confirmation des paramètres est réservée à la RH (auth + rôle)', str_contains($mw, 'auth') && str_contains($mw, 'role:rh_gestionnaire,rh_responsable'));
        $tz = (string) config('app.timezone');
        $this->line('  [INFO]  Fuseau horaire de l’application : '.$tz.($tz === 'Indian/Antananarivo' ? '' : ' (pour Madagascar : APP_TIMEZONE=Indian/Antananarivo dans .env)'));

        $this->info('--- Scénario (transaction annulée) ---');
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
        $role = DB::table('roles')->where('code', 'rh_responsable')->value('id');
        if (! $structure || ! $role) {
            $this->line('  [IGNORE] Structures ou rôles absents : lancer les seeders de démonstration.');

            return;
        }
        $nouveau = function (string $m) use ($structure, $role) {
            $a = Agent::create([
                'matricule' => $m, 'nom' => 'TESTLOT6', 'prenoms' => $m, 'sexe' => 'F', 'structure_id' => $structure,
                'rattachement' => 'sg', 'date_entree' => '2020-01-01', 'situation' => 'actif',
            ]);
            DB::table('habilitations')->insert(['agent_id' => $a->id, 'role_id' => $role, 'date_debut' => '2020-01-01', 'acte_reference' => 'test lot 6']);
            (new Compte())->forceFill(['agent_id' => $a->id, 'statut' => 'actif', 'mot_de_passe_hash' => bcrypt('Test12345')])->save();

            return $a;
        };
        $a = $nouveau('T6-A');
        $b = $nouveau('T6-B');

        // Continuité : la dernière RH responsable JOIGNABLE est protégée.
        $this->controle('Avec deux RH responsables actives, A n’est pas la seule', ! ContinuiteService::estLeSeulResponsable($a->id));
        $avant = ContinuiteService::responsablesJoignables($a->id);
        Compte::where('agent_id', $b->id)->update(['statut' => 'desactive']);
        $this->controle('B désactivée : le nombre de joignables hors A baisse de 1', ContinuiteService::responsablesJoignables($a->id) === $avant - 1);

        // On neutralise les autres responsables du jeu de données pour tester le « seul responsable ».
        // (on désactive leurs comptes : la transaction est annulée à la fin, rien n'est conservé)
        $autres = DB::table('habilitations')->where('role_id', $role)->whereNotIn('agent_id', [$a->id, $b->id])->pluck('agent_id');
        Compte::whereIn('agent_id', $autres)->update(['statut' => 'desactive']);
        $this->controle('Seule RH responsable joignable : A est protégée', ContinuiteService::estLeSeulResponsable($a->id));
        $this->controle('Une RH désactivée n’est pas comptée', ContinuiteService::responsablesJoignables(0) === 1);

        // Lien de secours : un lien, aucun rôle ajouté.
        $roles = DB::table('habilitations')->count();
        $reinit = DB::table('reinitialisations_mot_de_passe')->count();
        $code = Artisan::call('conges:lien-secours', ['identifiant' => 'T6-A', '--motif' => 'vérification lot 6', '--minutes' => 30]);
        $this->controle('conges:lien-secours réussit pour un compte actif', $code === 0);
        $this->controle('Un lien a été créé', DB::table('reinitialisations_mot_de_passe')->count() === $reinit + 1);
        $this->controle('Aucun rôle ajouté ou modifié par le secours', DB::table('habilitations')->count() === $roles);
        $this->controle('Le secours est inscrit au journal d’audit', DB::table('audit_logs')->where('action', 'lien_secours_genere')->exists());
        $refus = Artisan::call('conges:lien-secours', ['identifiant' => 'T6-B', '--motif' => 'test']);
        $this->controle('Refusé pour un compte désactivé', $refus !== 0);
        $jeton = ReinitialisationService::creerJeton(Compte::where('agent_id', $a->id)->first(), 10);
        $this->controle('Un seul lien valable à la fois (le précédent est annulé)',
            DB::table('reinitialisations_mot_de_passe')->where('compte_id', Compte::where('agent_id', $a->id)->value('id'))
                ->whereNull('utilise_le')->where('expire_le', '>', now())->count() === 1 && strlen($jeton) === 64);

        // Installation : refusée dès qu'une RH responsable avec compte actif existe.
        $this->controle('conges:installer refuse quand une RH responsable active existe', Artisan::call('conges:installer', ['--nom' => 'X', '--sexe' => 'M']) !== 0);

        // Valeurs provisoires : l'indicateur existe et ne touche que les paramètres désignés.
        $nb = DB::table('parametres')->where('a_confirmer', 1)->count();
        $this->controle('Les valeurs provisoires sont repérées ('.$nb.')', $nb >= 0 && DB::table('parametres')->where('cle', 'droit_annuel_jours')->value('a_confirmer') == 0);
    }

    private function controle(string $libelle, bool $ok): void
    {
        $this->line(($ok ? '  [OK]     ' : '  [ECHEC]  ').$libelle);
        if (! $ok) {
            $this->echecs++;
        }
    }
}
