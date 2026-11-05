<?php

namespace App\Providers;

use App\Models\Agent;
use App\Models\AvisPrealable;
use App\Models\Demande;
use App\Models\PieceJointe;
use App\Models\SoldeProposition;
use App\Policies\AgentPolicy;
use App\Policies\AvisPolicy;
use App\Policies\DemandePolicy;
use App\Policies\PieceMedicalePolicy;
use App\Policies\SoldePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Associe chaque modèle à sa policy (AvisPolicy, SoldePolicy... ne suivent pas la convention de nom de Laravel).
 * Aucun passe-droit : pas de Gate::before, pas même pour les administrateurs.
 *
 *   Gate::allows('decider', $demande)        $this->authorize('voir', $agent)       @can('modifier', $agent)
 *   Gate::allows('solde.voir', $agent)       Gate::allows('solde.proposer', $agent)
 */
class DroitsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Agent::class, AgentPolicy::class);
        Gate::policy(Demande::class, DemandePolicy::class);
        Gate::policy(AvisPrealable::class, AvisPolicy::class);
        Gate::policy(PieceJointe::class, PieceMedicalePolicy::class);
        Gate::policy(SoldeProposition::class, SoldePolicy::class);

        // Le solde d'un AGENT : l'argument est un Agent, donc on nomme les droits explicitement.
        Gate::define('solde.voir', [SoldePolicy::class, 'voir']);
        Gate::define('solde.proposer', [SoldePolicy::class, 'proposer']);
    }
}
