<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\CircuitRegles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Postes et fonctions : une liste OUVERTE, tenue par la RH (gestionnaire ou responsable).
 * Une fonction est un simple libellé : elle ne donne aucun droit. Elle peut suggérer une structure et une autorité
 * décisionnaire à la saisie d'une fiche, mais seuls les rôles (habilitations) et la fiche de l'agent décident du circuit.
 */
class FonctionController extends Controller
{
    use Autorise;

    public const CATEGORIES = [
        'direction' => 'Direction et autorités',
        'encadrement' => 'Encadrement (chefs de service…)',
        'cabinet' => 'Cabinet',
        'technique' => 'Personnel technique et administratif',
        'appui' => 'Personnel d’appui',
        'autre' => 'Autre',
    ];

    public function index(Request $request)
    {
        $this->autoriser('voirListe', Agent::class);

        $q = trim((string) $request->query('q', ''));
        $categorie = $request->query('categorie');

        $fonctions = DB::table('fonctions as f')
            ->leftJoin('structures as s', 's.id', '=', 'f.structure_id')
            ->leftJoin('agents as a', fn ($j) => $j->on('a.fonction_id', '=', 'f.id')->where('a.situation', '=', 'actif'))
            ->when($q !== '', fn ($req) => $req->where('f.libelle', 'like', '%'.addcslashes($q, '%_\\').'%'))
            ->when(array_key_exists((string) $categorie, self::CATEGORIES), fn ($req) => $req->where('f.categorie', $categorie))
            ->groupBy('f.id', 'f.libelle', 'f.categorie', 'f.rattachement', 'f.actif', 's.nom')
            ->orderBy('f.libelle')
            ->get(['f.id', 'f.libelle', 'f.categorie', 'f.rattachement', 'f.actif', 's.nom as structure_nom', DB::raw('COUNT(a.id) as agents')]);

        return view('rh.fonctions.index', [
            'fonctions' => $fonctions, 'categories' => self::CATEGORIES, 'q' => $q, 'categorie' => $categorie,
        ]);
    }

    public function create()
    {
        $this->autoriser('creer', Agent::class);

        return view('rh.fonctions.form', $this->donneesFormulaire(new \stdClass));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autoriser('creer', Agent::class);

        $donnees = $this->valider($request, null);
        $id = DB::table('fonctions')->insertGetId($donnees + ['actif' => 1]);
        AuditService::enregistrer('fonction_creee', null, null, ['id' => $id] + $donnees);

        return redirect()->route('rh.fonctions.index')->with('statut', 'Fonction ajoutée : elle est maintenant proposée dans les fiches des agents.');
    }

    public function edit(int $fonction)
    {
        $this->autoriser('creer', Agent::class);

        return view('rh.fonctions.form', $this->donneesFormulaire($this->trouver($fonction)));
    }

    public function update(Request $request, int $fonction): RedirectResponse
    {
        $this->autoriser('creer', Agent::class);
        $actuelle = $this->trouver($fonction);

        $donnees = $this->valider($request, $actuelle);
        DB::table('fonctions')->where('id', $actuelle->id)->update($donnees);
        AuditService::enregistrer('fonction_modifiee', null,
            ['id' => $actuelle->id, 'libelle' => $actuelle->libelle, 'structure_id' => $actuelle->structure_id, 'rattachement' => $actuelle->rattachement],
            ['id' => $actuelle->id] + $donnees);

        return redirect()->route('rh.fonctions.index')->with('statut', 'Fonction enregistrée.');
    }

    /** Désactive ou réactive. Une fonction désactivée n'est plus proposée, mais les agents qui l'ont la gardent. */
    public function etat(int $fonction): RedirectResponse
    {
        $this->autoriser('creer', Agent::class);
        $f = $this->trouver($fonction);

        DB::table('fonctions')->where('id', $f->id)->update(['actif' => $f->actif ? 0 : 1]);
        AuditService::enregistrer($f->actif ? 'fonction_desactivee' : 'fonction_reactivee', null, ['id' => $f->id, 'libelle' => $f->libelle]);

        return back()->with('statut', $f->actif ? 'Fonction désactivée : elle n’est plus proposée pour les nouvelles fiches.' : 'Fonction réactivée.');
    }

    // ------------------------------------------------------------------

    private function trouver(int $id): object
    {
        return DB::table('fonctions')->where('id', $id)->first() ?? abort(404);
    }

    private function valider(Request $request, ?object $f): array
    {
        $donnees = $request->validate([
            'libelle' => ['required', 'string', 'min:2', 'max:120', Rule::unique('fonctions', 'libelle')->ignore($f->id ?? null)],
            'categorie' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'rattachement' => ['nullable', Rule::in(array_keys(CircuitRegles::CIRCUITS))],
        ], [
            'libelle.required' => 'Saisissez le nom de la fonction.',
            'libelle.unique' => 'Cette fonction existe déjà (les majuscules et les accents ne comptent pas).',
            'categorie.required' => 'Choisissez une catégorie.',
        ]);

        $donnees['libelle'] = trim(preg_replace('/\s+/u', ' ', $donnees['libelle']));
        if ($donnees['libelle'] === '') {
            throw ValidationException::withMessages(['libelle' => 'Saisissez le nom de la fonction.']);
        }

        return [
            'libelle' => $donnees['libelle'],
            'categorie' => $donnees['categorie'],
            'structure_id' => $donnees['structure_id'] ?? null,
            'rattachement' => $donnees['rattachement'] ?? null,
        ];
    }

    private function donneesFormulaire(object $fonction): array
    {
        return [
            'fonction' => $fonction,
            'categories' => self::CATEGORIES,
            'structures' => DB::table('structures')->where('actif', 1)->orderBy('nom')->pluck('nom', 'id')->all(),
        ];
    }
}
