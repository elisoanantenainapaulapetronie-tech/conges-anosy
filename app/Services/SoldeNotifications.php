<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\SoldeProposition;
use App\Policies\SoldePolicy;

/**
 * Qui est prévenu, et de quoi, dans la vie d'une proposition de solde. Un seul endroit : le contrôleur n'écrit plus
 * aucun texte de notification. Tout passe par NotificationService (interne obligatoire, e-mail ou SMS selon le cas).
 *
 *   proposition déposée  -> ceux qui peuvent la contrôler (hors l'auteur et la personne concernée)
 *   validée              -> l'auteur (avec l'observation) ET la personne concernée si ce n'est pas l'auteur
 *   rejetée              -> l'auteur seulement (le solde de l'agent n'a pas changé)
 *   retirée              -> ceux qui devaient la contrôler
 */
class SoldeNotifications
{
    public static function aControler(Agent $cible, Agent $auteur, int $propositionId): void
    {
        app(NotificationService::class)->notifierPlusieurs(
            self::controleurs($cible)->reject(fn ($a) => in_array((int) $a->id, [(int) $auteur->id, (int) $cible->id], true)),
            'solde_a_controler', 'Mouvement de solde à contrôler',
            'Une proposition de mouvement concerne le solde de '.$cible->nom_complet.'.',
            ['lien' => route('rh.soldes.propositions')],
        );
    }

    public static function controlee(SoldeProposition $p, bool $valide, ?string $observation): void
    {
        $notifs = app(NotificationService::class);
        $cible = Agent::find($p->agent_id);
        $nom = $cible?->nom_complet ?? 'l’agent';
        $resume = SoldeService::NATURES[$p->nature] ?? $p->nature;
        $resume .= ' '.($p->jours > 0 ? '+' : '−').DecompteService::libelle(abs((float) $p->jours)).' sur '.$p->annee.' ('.$nom.')';
        $observation = trim((string) $observation);

        $notifs->notifier((int) $p->propose_par, 'solde_controle',
            $valide ? 'Votre proposition de solde est validée' : 'Votre proposition de solde est rejetée',
            $resume.($valide ? ' : validée et inscrite au registre.' : ' : rejetée, le solde n’a pas changé.')
                .($observation !== '' ? ' Observation : '.$observation : ''),
            ['lien' => route('rh.historique-corrections'), 'importance' => $valide ? 'normale' : 'importante']);

        // La personne concernée est prévenue quand SON solde change (pas pour un rejet : rien n'a changé pour elle).
        if ($valide && (int) $p->agent_id !== (int) $p->propose_par) {
            $notifs->notifier((int) $p->agent_id, 'solde_modifie', 'Votre solde de congés a été modifié',
                'Un mouvement a été inscrit à votre registre ('.($p->motif ?: 'voir « Mon solde »').'). Consultez « Mon solde » pour le détail.',
                ['lien' => route('solde', ['annee' => $p->annee])]);
        }
    }

    public static function retiree(SoldeProposition $p, Agent $auteur, string $motif): void
    {
        $cible = Agent::find($p->agent_id);
        if (! $cible) {
            return;
        }
        app(NotificationService::class)->notifierPlusieurs(
            self::controleurs($cible)->reject(fn ($a) => in_array((int) $a->id, [(int) $auteur->id, (int) $cible->id], true)),
            'solde_retiree', 'Proposition de solde retirée',
            $auteur->nom_complet.' a retiré sa proposition concernant '.$cible->nom_complet.' : '.$motif,
            ['lien' => route('rh.historique-corrections')],
        );
    }

    /** Ceux qui peuvent contrôler un mouvement sur le solde de $cible (même règle que SoldePolicy::controleurAttendu). */
    private static function controleurs(Agent $cible)
    {
        $notifs = app(NotificationService::class);
        $codes = match (app(SoldePolicy::class)->controleurAttendu($cible)) {
            'rh' => ['rh_gestionnaire', 'rh_responsable'],
            'rh_responsable', 'autorite_externe' => ['rh_responsable'],
            'secretaire_general' => ['secretaire_general'],
            'chef_region' => ['chef_region'],
            default => null,
        };

        // Sans rôle (Directeur des Affaires générales), on le retrouve par sa fonction.
        return $codes
            ? $notifs->agentsAyantRole($codes)
            : Agent::actifs()->whereHas('structure', fn ($q) => $q->where('code', 'DAG'))->whereHas('fonction', fn ($q) => $q->where('libelle', 'Directeur'))->get();
    }
}
