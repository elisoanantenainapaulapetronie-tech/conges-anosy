<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Centre de notifications : chacun ne voit, n'ouvre et ne marque que SES notifications
 * (le destinataire vient toujours de la session, jamais de la requête).
 */
class NotificationController extends Controller
{
    /** Libellé court par type d'événement (affichage). Un type inconnu reste lisible. */
    public const TYPES = [
        'solde_a_controler' => 'Soldes',
        'solde_controle' => 'Soldes',
        'solde_modifie' => 'Soldes',
        'solde_retiree' => 'Soldes',
        'solde_correction_importante' => 'Soldes',
        'avis_demande' => 'Avis',
        'avis_rendu' => 'Avis',
        'a_completer' => 'Demande',
        'validee' => 'Demande',
    ];

    public function index(Request $request)
    {
        $moi = $request->user()->agent_id;
        $filtre = $request->query('filtre') === 'non_lues' ? 'non_lues' : 'toutes';

        $requete = DB::table('notifications')->where('destinataire_id', $moi)
            ->when($filtre === 'non_lues', fn ($q) => $q->whereNull('lue_le'))
            ->orderByDesc('created_at')->orderByDesc('id');

        return view('notifications.index', [
            'notifications' => $requete->paginate(20)->withQueryString(),
            'filtre' => $filtre,
            'nonLues' => (int) DB::table('notifications')->where('destinataire_id', $moi)->whereNull('lue_le')->count(),
            'types' => self::TYPES,
        ]);
    }

    /** Marque la notification comme lue, puis ouvre la page liée (chemin interne uniquement). */
    public function ouvrir(Request $request, int $notification): RedirectResponse
    {
        $n = DB::table('notifications')->where('id', $notification)->where('destinataire_id', $request->user()->agent_id)->first();
        abort_if($n === null, 404);

        if ($n->lue_le === null) {
            DB::table('notifications')->where('id', $n->id)->update(['lue_le' => now()]);
        }

        $lien = (string) ($n->lien ?? '');
        $interne = $lien !== '' && $lien[0] === '/' && ! str_starts_with($lien, '//');

        return $interne ? redirect($lien) : redirect()->route('notifications');
    }

    public function marquerLue(Request $request, int $notification): RedirectResponse
    {
        DB::table('notifications')->where('id', $notification)->where('destinataire_id', $request->user()->agent_id)
            ->whereNull('lue_le')->update(['lue_le' => now()]);

        return back();
    }

    public function toutLire(Request $request): RedirectResponse
    {
        DB::table('notifications')->where('destinataire_id', $request->user()->agent_id)->whereNull('lue_le')->update(['lue_le' => now()]);

        return redirect()->route('notifications')->with('statut', 'Toutes les notifications sont marquées comme lues.');
    }
}
