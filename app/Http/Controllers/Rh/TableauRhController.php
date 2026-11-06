<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\CircuitRegles;
use Illuminate\Support\Facades\DB;

/** Tableau de bord de l'espace RH : chiffres clés et agents à traiter. */
class TableauRhController extends Controller
{
    use Autorise;

    public function __invoke()
    {
        $this->autoriser('voirListe', Agent::class);

        $comptes = fn (string $statut) => DB::table('comptes')
            ->join('agents', 'agents.id', '=', 'comptes.agent_id')
            ->where('agents.situation', 'actif')
            ->where('comptes.statut', $statut);

        $chiffres = [
            'agents' => Agent::actifs()->count(),
            'actifs' => $comptes('actif')->count(),
            'attente' => $comptes('en_attente_activation')->count(),
            'expires' => $comptes('en_attente_activation')->where('comptes.code_activation_expire_le', '<', now())->count(),
            'sans_compte' => Agent::actifs()->whereDoesntHave('compte')->count(),
        ];

        $roles = DB::table('roles')
            ->leftJoin('habilitations', function ($j) {
                $j->on('habilitations.role_id', '=', 'roles.id')
                    ->whereDate('habilitations.date_debut', '<=', today())
                    ->where(fn ($q) => $q->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()));
            })
            ->groupBy('roles.id', 'roles.libelle')
            ->orderBy('roles.id')
            ->select('roles.libelle', DB::raw('COUNT(habilitations.id) as total'))
            ->get();

        $aTraiter = Agent::actifs()->whereDoesntHave('compte')->with('structure')->orderBy('nom')->limit(6)->get();

        // Circuit : agents dont les demandes n'auraient personne pour donner l'avis.
        $sansResponsable = CircuitRegles::agentsSansResponsable();
        $responsableSansRole = CircuitRegles::agentsResponsableSansRole();

        return view('rh.tableau', compact('chiffres', 'roles', 'aTraiter', 'sansResponsable', 'responsableSansRole'));
    }
}
