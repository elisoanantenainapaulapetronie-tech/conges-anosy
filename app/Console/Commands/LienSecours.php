<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\AuditLog;
use App\Services\EnvoiMessage;
use App\Services\ReinitialisationService;
use Illuminate\Console\Command;

/**
 * php artisan conges:lien-secours {identifiant} --motif="..."
 * Secours technique : génère un lien de réinitialisation de mot de passe pour un compte ACTIF dont la personne ne peut plus se
 * connecter (ex. la seule RH responsable a perdu son accès). Le lien est à usage unique, valable --minutes (60 par défaut),
 * affiché une seule fois et jamais journalisé ; l'opération et son motif sont inscrits au journal d'audit ; l'agent est prévenu
 * par e-mail ou SMS s'il a un canal. Cette commande ne crée aucun compte et n'attribue AUCUN rôle.
 */
class LienSecours extends Command
{
    protected $signature = 'conges:lien-secours {identifiant : identifiant ou matricule} {--motif= : raison du secours (obligatoire)} {--minutes=60 : durée de validité}';

    protected $description = 'Génère un lien de réinitialisation tracé pour un compte actif (secours technique, sans attribution de rôle)';

    public function handle(EnvoiMessage $messages): int
    {
        $motif = trim((string) ($this->option('motif') ?: $this->ask('Motif du secours (sera inscrit au journal)')));
        if ($motif === '') {
            $this->error('Le motif est obligatoire.');

            return self::FAILURE;
        }
        $minutes = max(5, min(1440, (int) $this->option('minutes')));

        $agent = Agent::trouverPourConnexion((string) $this->argument('identifiant'));
        $compte = $agent?->compte;
        if (! $agent || ! $compte || $compte->statut !== 'actif' || $agent->situation !== 'actif') {
            $this->error('Aucun compte actif ne correspond à cet identifiant (ou la saisie est ambiguë).');
            AuditLog::create([
                'action' => 'lien_secours_refuse', 'objet_type' => 'compte', 'refuse' => true,
                'valeur_apres' => ['saisie' => mb_substr((string) $this->argument('identifiant'), 0, 30), 'motif' => mb_substr($motif, 0, 200)],
            ]);

            return self::FAILURE;
        }

        $jeton = ReinitialisationService::creerJeton($compte, $minutes);
        $expire = now()->addMinutes($minutes);
        AuditLog::create([
            'auteur_id' => null, 'action' => 'lien_secours_genere', 'objet_type' => 'compte', 'objet_id' => $compte->id,
            'valeur_apres' => ['motif' => mb_substr($motif, 0, 200), 'expire_le' => $expire->format('Y-m-d H:i'), 'origine' => 'console'],
        ]);
        $messages->envoyer($agent, 'Un lien de réinitialisation a été généré pour votre compte — Congés Anosy',
            'Un administrateur a généré un lien pour changer votre mot de passe. Si vous ne l’attendiez pas, prévenez la RH.');

        $this->info('Compte : '.$agent->nom_complet.' ('.$agent->identifiant.')');
        $this->line('Lien (valable jusqu’à '.$expire->format('d/m/Y H:i').', une seule utilisation, ne sera plus affiché) :');
        $this->line(route('reinitialisation', ['jeton' => $jeton]));

        return self::SUCCESS;
    }
}
