<?php

namespace App\Http\Controllers;

use App\Services\DecompteService;
use App\Services\SoldeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * « Mon solde » : l'agent voit SON solde et SON registre (lecture seule), et peut simuler la durée d'une absence.
 * Aucun identifiant d'agent n'est reçu : on ne peut donc jamais consulter le solde d'un autre par cette page.
 */
class SoldeController extends Controller
{
    public function index(Request $request)
    {
        $agent = $request->user()->agent;
        $annees = SoldeService::annees($agent->id);
        $annee = (int) $request->query('annee', $annees[0]);
        if (! in_array($annee, $annees, true)) {
            $annee = $annees[0];
        }

        return view('solde.mon-solde', [
            'agent' => $agent,
            'annee' => $annee,
            'annees' => $annees,
            'situation' => SoldeService::situation($agent->id, $annee),
            'registre' => SoldeService::registre($agent->id, $annee),
        ]);
    }

    /** Simulation de durée (sans rien enregistrer) : jours ouvrés, fériés, demi-journées, effet sur le solde. */
    public function simuler(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'debut' => ['required', 'date_format:Y-m-d'],
            'fin' => ['required', 'date_format:Y-m-d'],
            'demi_debut' => ['nullable', 'in:journee,apres_midi'],
            'demi_fin' => ['nullable', 'in:journee,matin'],
        ], [
            'debut.required' => 'Choisissez la date de début.',
            'fin.required' => 'Choisissez la date de fin.',
            'debut.date_format' => 'Date de début invalide.',
            'fin.date_format' => 'Date de fin invalide.',
        ]);

        try {
            $calcul = DecompteService::compter($donnees['debut'], $donnees['fin'], $donnees['demi_debut'] ?? 'journee', $donnees['demi_fin'] ?? 'journee');
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $agentId = $request->user()->agent_id;
        $soldes = [];
        foreach ($calcul['par_annee'] as $annee => $jours) {
            $s = SoldeService::situation($agentId, (int) $annee);
            $soldes[] = [
                'annee' => (int) $annee, 'jours' => $jours, 'jours_libelle' => DecompteService::libelle($jours),
                'previsionnel' => $s['previsionnel'], 'apres' => round($s['previsionnel'] - $jours, 2),
                'enregistre' => $s['a_des_mouvements'],
            ];
        }

        return response()->json([
            'total' => $calcul['total'],
            'total_libelle' => DecompteService::libelle($calcul['total']),
            'calendaires' => $calcul['calendaires'],
            'lignes' => $calcul['lignes'],
            'soldes' => $soldes,
            'avertissements' => $calcul['avertissements'],
        ]);
    }
}
