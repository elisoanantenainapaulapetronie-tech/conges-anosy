<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccueilController extends Controller
{
    /** Accueil provisoire : identité, rôles et solde de l'année. Les autres espaces arrivent ensuite. */
    public function index(Request $request)
    {
        $agent = $request->user()->agent->load(['fonction', 'structure', 'responsable']);

        $ligne = DB::table('v_soldes_agents')
            ->where('agent_id', $agent->id)->where('annee', now()->year)->first();

        $jours = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');

        // Parts de la barre : consommés, réservés, en attente, disponible après attente (total = droits + report).
        $disp = max(0, (float) ($ligne->solde_disponible ?? 0));
        $prev = max(0, (float) ($ligne->solde_previsionnel ?? 0));
        $res = max(0, (float) ($ligne->jours_reserves ?? 0));
        $cons = max(0, (float) ($ligne->jours_consommes ?? 0));
        $attente = max(0, $disp - $prev);
        $total = $disp + $res + $cons;
        $part = fn (float $v) => $total > 0 ? round($v / $total * 100, 2) : 0;

        return view('accueil', [
            'agent' => $agent,
            'roles' => $agent->codesRoles(),
            'solde' => [
                'disponible' => $jours($ligne->solde_disponible ?? null),
                'previsionnel' => $jours($ligne->solde_previsionnel ?? null),
                'reserves' => $jours($ligne->jours_reserves ?? null),
                'consommes' => $jours($ligne->jours_consommes ?? null),
                'annee' => now()->year,
                'existe' => $ligne !== null,
                'attente' => $jours($ligne ? $attente : null),
                'barre' => ['consommes' => $part($cons), 'reserves' => $part($res), 'attente' => $part($attente), 'previsionnel' => $part($prev)],
            ],
        ]);
    }
}
