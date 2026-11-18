<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\Parametres;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Valeurs provisoires : un paramètre marqué « à confirmer par la RH » peut être confirmé tel quel, ou corrigé puis confirmé,
 * par la RH responsable uniquement. Seuls les paramètres encore marqués sont concernés (jamais un paramètre déjà confirmé),
 * et la valeur doit être un nombre positif ou nul (ou vide = non fixé). Chaque confirmation est inscrite au journal d'audit.
 */
class ParametreProvisoireController extends Controller
{
    public function confirmer(Request $request, string $cle): RedirectResponse
    {
        $moi = $request->user()->agent;
        if (! $moi->aLeRole('rh_responsable')) {
            AuditService::refus('acces_refuse_confirmer_parametre', null, ['cle' => $cle]);
            abort(403);
        }

        $ligne = DB::table('parametres')->where('cle', $cle)->where('a_confirmer', 1)->first();
        abort_if($ligne === null, 404);

        $saisie = str_replace(',', '.', trim((string) $request->input('valeur', '')));
        if ($saisie !== '' && (! is_numeric($saisie) || (float) $saisie < 0 || (float) $saisie > 3650)) {
            return back()->with('erreur', 'La valeur doit être un nombre positif ou nul (ex. 10 ou 2,5), ou rester vide.');
        }
        $nouvelle = $saisie === '' ? null : (string) ((float) $saisie == (int) $saisie ? (int) $saisie : (float) $saisie);

        DB::table('parametres')->where('cle', $cle)->update(['valeur' => $nouvelle, 'a_confirmer' => 0, 'modifie_par' => $moi->id]);
        Parametres::oublier();
        AuditService::enregistrer('parametre_confirme', null, ['cle' => $cle, 'valeur' => $ligne->valeur], ['valeur' => $nouvelle]);

        return back()->with('statut', "Valeur confirmée : « {$cle} » = ".($nouvelle ?? 'non fixée').'.');
    }
}
