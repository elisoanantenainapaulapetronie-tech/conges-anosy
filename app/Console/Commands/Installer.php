<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\AuditLog;
use App\Services\CodeActivationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * php artisan conges:installer
 * Crée la PREMIÈRE RH responsable d'une installation vierge : fiche agent, rôle « RH responsable » (acte : installation),
 * compte « en attente d'activation » et code d'activation à usage unique (affiché une seule fois ; durée : paramètre
 * duree_code_activation_jours, 5 jours par défaut ; 5 essais maximum à l'activation). L'opération est inscrite au journal d'audit.
 *
 * Refuse de s'exécuter si une RH responsable existe déjà (rôle en cours). Si la première RH responsable n'a pas activé son compte
 * à temps, --regenerer émet un nouveau code pour elle ; si son compte est déjà actif, la commande refuse toujours.
 * Ensuite, chaque profil RH crée les comptes de l'autre dans l'application, jamais le sien.
 */
class Installer extends Command
{
    protected $signature = 'conges:installer
        {--nom= : Nom de famille} {--prenoms= : Prénoms} {--sexe= : M ou F}
        {--matricule= : Matricule (facultatif : sinon un identifiant ANO-xxxxx est généré)}
        {--email=} {--telephone=} {--structure= : Code ou nom de la structure de rattachement}
        {--date-entree= : AAAA-MM-JJ (défaut : aujourd’hui)}
        {--regenerer : nouveau code pour la première RH responsable qui n’a pas encore activé son compte}';

    protected $description = 'Crée la première RH responsable (installation) et son code d’activation';

    public function handle(): int
    {
        $existants = DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->leftJoin('comptes', 'comptes.agent_id', '=', 'habilitations.agent_id')
            ->where('roles.code', 'rh_responsable')
            ->whereDate('habilitations.date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()))
            ->get(['habilitations.agent_id', 'comptes.statut']);

        if ($existants->isNotEmpty()) {
            if ($existants->contains(fn ($e) => $e->statut === 'actif')) {
                $this->error('Installation déjà faite : une RH responsable a un compte actif. Ses comptes se gèrent dans l’application.');

                return self::FAILURE;
            }
            if (! $this->option('regenerer')) {
                $this->error('Une RH responsable existe déjà mais n’a pas activé son compte. Pour lui émettre un nouveau code : php artisan conges:installer --regenerer');

                return self::FAILURE;
            }
            $agent = Agent::findOrFail($existants->first()->agent_id);

            return $this->emettre($agent, 'installation_code_regenere');
        }

        if ($this->option('regenerer')) {
            $this->error('Aucune RH responsable à régénérer : lancez d’abord la commande sans --regenerer.');

            return self::FAILURE;
        }

        $donnees = [
            'nom' => $this->option('nom') ?: $this->ask('Nom de famille'),
            'prenoms' => $this->option('prenoms') ?: $this->ask('Prénoms'),
            'sexe' => strtoupper((string) ($this->option('sexe') ?: $this->choice('Sexe', ['M', 'F']))),
            'matricule' => $this->option('matricule') ?: null,
            'email' => $this->option('email') ?: null,
            'telephone' => $this->option('telephone') ?: null,
            'date_entree' => $this->option('date-entree') ?: today()->toDateString(),
        ];
        $v = Validator::make($donnees, [
            'nom' => ['required', 'string', 'max:120'], 'prenoms' => ['nullable', 'string', 'max:160'],
            'sexe' => ['required', 'in:M,F'], 'matricule' => ['nullable', 'string', 'max:30', 'unique:agents,matricule'],
            'email' => ['nullable', 'email', 'max:190'], 'telephone' => ['nullable', 'string', 'max:30'],
            'date_entree' => ['required', 'date'],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $structure = $this->structure();
        if (! $structure) {
            $this->error('Structure introuvable. Créez d’abord l’organisation (jeu de données de base) ou donnez un code existant avec --structure.');

            return self::FAILURE;
        }
        $roleId = DB::table('roles')->where('code', 'rh_responsable')->value('id');
        if (! $roleId) {
            $this->error('Le rôle « rh_responsable » n’existe pas : la base n’est pas initialisée.');

            return self::FAILURE;
        }

        $agent = DB::transaction(function () use ($donnees, $structure, $roleId) {
            $agent = Agent::create($donnees + [
                'structure_id' => $structure->id, 'rattachement' => $structure->rattachement, 'situation' => 'actif',
            ]);
            DB::table('habilitations')->insert([
                'agent_id' => $agent->id, 'role_id' => $roleId, 'structure_id' => null,
                'date_debut' => today()->toDateString(), 'date_fin' => null,
                'acte_reference' => 'Installation initiale (commande conges:installer)', 'attribuee_par' => null,
            ]);
            AuditLog::create([
                'auteur_id' => null, 'action' => 'installation_rh_responsable', 'objet_type' => 'Agent', 'objet_id' => $agent->id,
                'valeur_apres' => ['role' => 'rh_responsable', 'identifiant' => $agent->identifiant],
            ]);

            return $agent;
        });

        return $this->emettre($agent, 'compte_cree');
    }

    private function emettre(Agent $agent, string $action): int
    {
        $emis = CodeActivationService::emettre($agent);
        // Le code n'est jamais journalisé.
        AuditLog::create([
            'auteur_id' => null, 'action' => $action, 'objet_type' => 'Agent', 'objet_id' => $agent->id,
            'valeur_apres' => ['statut' => 'en_attente_activation', 'expire_le' => $emis['expire']->toDateString(), 'origine' => 'installation'],
        ]);

        $this->newLine();
        $this->info('RH responsable : '.$agent->nom_complet.'  (identifiant : '.$agent->identifiant.')');
        $this->line('Code d’activation : <options=bold>'.$emis['code'].'</>   (valable jusqu’au '.$emis['expire']->format('d/m/Y').', ce code ne sera plus affiché)');
        $this->line('Page d’activation : '.route('activation'));
        $this->line('Remettez-le en main propre : la personne choisit elle-même son mot de passe.');

        return self::SUCCESS;
    }

    private function structure(): ?object
    {
        $saisie = $this->option('structure');
        if ($saisie) {
            return DB::table('structures')->where('code', $saisie)->orWhere('nom', $saisie)->first();
        }
        $liste = DB::table('structures')->orderBy('nom')->get(['id', 'nom', 'code', 'rattachement']);
        if ($liste->isEmpty()) {
            return null;
        }
        $choix = $this->choice('Structure de rattachement', $liste->map(fn ($s) => $s->nom.' ('.$s->code.')')->all(), 0);

        return $liste->first(fn ($s) => $s->nom.' ('.$s->code.')' === $choix);
    }
}
