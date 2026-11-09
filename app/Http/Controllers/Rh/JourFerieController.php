<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\JoursFeriesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Calendrier des jours fériés (dossier 8.3) : la RH l'enregistre chaque année ; le décompte des jours ne compte pas ces jours.
 * « Pré-remplir l'année » propose les dates fixes et les fêtes mobiles de Madagascar sans écraser ce qui existe.
 */
class JourFerieController extends Controller
{
    use Autorise;

    public function index(Request $request)
    {
        $this->autoriser('voirListe', Agent::class);

        $annee = $this->annee($request->query('annee'));
        $existants = DB::table('jours_feries')->whereYear('date_ferie', $annee)->orderBy('date_ferie')->get();
        $manquants = array_diff_key(JoursFeriesService::proposer($annee), $existants->pluck('libelle', 'date_ferie')->all());

        $jours = $existants->map(function ($j) {
            $j->jour = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'][(int) date('w', strtotime($j->date_ferie))];
            $j->week_end = in_array($j->jour, ['samedi', 'dimanche'], true);
            $j->passe = $j->date_ferie < date('Y-m-d');

            return $j;
        });

        $courante = (int) date('Y');

        return view('rh.jours-feries.index', [
            'jours' => $jours, 'manquants' => $manquants, 'annee' => $annee, 'annees' => range($courante + 2, $courante - 1),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autoriser('creer', Agent::class);

        $donnees = $request->validate([
            'date_ferie' => ['required', 'date_format:Y-m-d', 'unique:jours_feries,date_ferie'],
            'libelle' => ['required', 'string', 'min:3', 'max:160'],
        ], [
            'date_ferie.required' => 'Choisissez la date.',
            'date_ferie.unique' => 'Ce jour est déjà dans le calendrier.',
            'libelle.required' => 'Indiquez le nom du jour férié.',
        ]);

        DB::table('jours_feries')->insert(['date_ferie' => $donnees['date_ferie'], 'libelle' => trim($donnees['libelle'])]);
        AuditService::enregistrer('jour_ferie_ajoute', null, null, $donnees);

        return redirect()->route('rh.jours-feries.index', ['annee' => substr($donnees['date_ferie'], 0, 4)])->with('statut', 'Jour férié ajouté.');
    }

    public function remplir(Request $request): RedirectResponse
    {
        $this->autoriser('creer', Agent::class);
        $annee = $this->annee($request->input('annee'));
        $ajoutes = JoursFeriesService::remplir($annee);
        AuditService::enregistrer('jours_feries_prerempli', null, null, ['annee' => $annee, 'ajoutes' => $ajoutes]);

        return redirect()->route('rh.jours-feries.index', ['annee' => $annee])->with('statut',
            $ajoutes > 0 ? "{$ajoutes} jour(s) férié(s) ajouté(s) pour {$annee}. Vérifiez-les avec le calendrier officiel." : "Le calendrier {$annee} était déjà complet.");
    }

    /** Un jour férié passé n'est plus supprimé : il a pu servir à des décomptes déjà enregistrés. */
    public function destroy(int $jour): RedirectResponse
    {
        $this->autoriser('creer', Agent::class);
        $j = DB::table('jours_feries')->where('id', $jour)->first() ?? abort(404);

        if ($j->date_ferie < date('Y-m-d')) {
            return back()->with('erreur', 'Un jour férié passé ne peut plus être supprimé : il a pu servir à des décomptes.');
        }

        DB::table('jours_feries')->where('id', $j->id)->delete();
        AuditService::enregistrer('jour_ferie_retire', null, ['date_ferie' => $j->date_ferie, 'libelle' => $j->libelle]);

        return back()->with('statut', 'Jour férié retiré du calendrier.');
    }

    private function annee(mixed $valeur): int
    {
        $a = (int) $valeur;

        return ($a >= 2000 && $a <= 2100) ? $a : (int) date('Y');
    }
}
