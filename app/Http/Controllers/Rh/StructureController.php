<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\CircuitRegles;
use App\Services\OrganisationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Organigramme : la RH lit, le RH responsable modifie (cela change les circuits de décision). */
class StructureController extends Controller
{
    use Autorise;

    /** Valeur de la liste « Dépend de » qui signifie « la structure parente n'est pas dans la liste : je la saisis ». */
    public const PARENT_AUTRE = '__autre__';

    public const TYPES = [
        'cabinet' => 'Cabinet',
        'direction' => 'Direction',
        'service' => 'Service',
        'service_rattache' => 'Service rattaché',
        'autre' => 'Autre',
    ];

    public function index()
    {
        $this->autoriser('voirListe', Agent::class);

        $toutes = DB::table('structures')->orderBy('nom')->get();
        $effectifs = DB::table('agents')->where('situation', 'actif')
            ->select('structure_id', DB::raw('COUNT(*) as n'))->groupBy('structure_id')->pluck('n', 'structure_id');
        $parParent = $toutes->groupBy(fn ($s) => $s->parent_id ?? 0);

        $lignes = [];
        $parcourir = function (int $parent, int $niveau) use (&$parcourir, &$lignes, $parParent, $effectifs) {
            foreach ($parParent->get($parent, collect()) as $s) {
                $lignes[] = ['structure' => $s, 'niveau' => $niveau, 'effectif' => (int) ($effectifs[$s->id] ?? 0)];
                $parcourir((int) $s->id, $niveau + 1);
            }
        };
        $parcourir(0, 0);

        return view('rh.structures.index', [
            'lignes' => $lignes,
            'types' => self::TYPES,
            'peutModifier' => $this->peutModifier(),
        ]);
    }

    public function create()
    {
        $this->exigerRhResponsable();

        return view('rh.structures.form', $this->donneesFormulaire(new \stdClass));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->exigerRhResponsable();

        $donnees = $this->preparer($request->validate($this->regles(null), $this->messages()));
        $id = DB::table('structures')->insertGetId($donnees + ['actif' => 1]);
        AuditService::enregistrer('structure_creee', null, null, ['id' => $id] + $donnees);

        return redirect()->route('rh.structures.index')->with('statut', 'Structure ajoutée.');
    }

    public function edit(int $structure)
    {
        $this->exigerRhResponsable();

        return view('rh.structures.form', $this->donneesFormulaire($this->trouver($structure)));
    }

    public function update(Request $request, int $structure): RedirectResponse
    {
        $this->exigerRhResponsable();
        $actuelle = $this->trouver($structure);

        $donnees = $request->validate($this->regles($actuelle), $this->messages());
        $donnees = $this->preparer($donnees);

        if (! empty($donnees['parent_id'])
            && in_array((int) $donnees['parent_id'], OrganisationService::structuresEnfants($actuelle->id), true)) {
            throw ValidationException::withMessages(['parent_id' => 'Une structure ne peut pas dépendre d’une de ses propres sous-structures.']);
        }

        DB::table('structures')->where('id', $actuelle->id)->update($donnees);
        AuditService::enregistrer('structure_modifiee', null,
            ['id' => $actuelle->id, 'nom' => $actuelle->nom, 'parent_id' => $actuelle->parent_id, 'rattachement' => $actuelle->rattachement],
            ['id' => $actuelle->id] + $donnees);

        return redirect()->route('rh.structures.index')->with('statut', 'Structure enregistrée.');
    }

    /** Désactive ou réactive. Une structure occupée n'est jamais désactivée. */
    public function etat(int $structure): RedirectResponse
    {
        $this->exigerRhResponsable();
        $s = $this->trouver($structure);

        if ($s->actif) {
            $agents = DB::table('agents')->where('structure_id', $s->id)->where('situation', 'actif')->count();
            $enfants = DB::table('structures')->where('parent_id', $s->id)->where('actif', 1)->count();
            if ($agents > 0 || $enfants > 0) {
                return back()->with('erreur', "Impossible : cette structure compte encore {$agents} agent(s) actif(s) et {$enfants} sous-structure(s) active(s).");
            }
        }

        DB::table('structures')->where('id', $s->id)->update(['actif' => $s->actif ? 0 : 1]);
        AuditService::enregistrer($s->actif ? 'structure_desactivee' : 'structure_reactivee', null, ['id' => $s->id, 'nom' => $s->nom]);

        return back()->with('statut', $s->actif ? 'Structure désactivée.' : 'Structure réactivée.');
    }

    // ------------------------------------------------------------------

    private function peutModifier(): bool
    {
        return auth()->user()->agent->aLeRole('rh_responsable');
    }

    private function exigerRhResponsable(): void
    {
        if (! $this->peutModifier()) {
            AuditService::refus('acces_refuse_structures', null, ['url' => request()->path()]);
            abort(403, 'Seul le RH responsable modifie l’organisation.');
        }
    }

    private function trouver(int $id): object
    {
        return DB::table('structures')->where('id', $id)->first() ?? abort(404);
    }

    private function regles(?object $s): array
    {
        return [
            'nom' => ['required', 'string', 'max:190'],
            'code' => ['nullable', 'string', 'max:30', Rule::unique('structures', 'code')->ignore($s->id ?? null)],
            'type' => ['required', Rule::in(array_keys(self::TYPES))],
            // Type « Autre » : on précise de quel type il s'agit (Mission, Projet, Antenne…).
            'type_precision' => ['nullable', 'string', 'max:80', 'required_if:type,autre'],
            // Une structure de la liste, ou « __autre__ » : la structure parente saisie à la main est alors créée.
            'parent_id' => ['nullable', function (string $attribut, mixed $valeur, \Closure $echec) use ($s) {
                if ($valeur === null || $valeur === '' || $valeur === self::PARENT_AUTRE) {
                    return;
                }
                if (! ctype_digit((string) $valeur) || ! DB::table('structures')->where('id', (int) $valeur)->exists()) {
                    $echec('Cette structure n’existe pas.');
                } elseif ((int) $valeur === (int) ($s->id ?? 0)) {
                    $echec('Une structure ne peut pas dépendre d’elle-même.');
                }
            }],
            'parent_autre' => ['nullable', 'string', 'max:190', 'required_if:parent_id,'.self::PARENT_AUTRE],
            'rattachement' => ['required', Rule::in(array_keys(CircuitRegles::CIRCUITS))],
        ];
    }

    private function messages(): array
    {
        return [
            'nom.required' => 'Saisissez le nom de la structure.',
            'code.unique' => 'Ce code est déjà utilisé.',
            'type.required' => 'Choisissez le type de structure.',
            'parent_id.not_in' => 'Une structure ne peut pas dépendre d’elle-même.',
            'rattachement.required' => 'Indiquez le circuit de décision.',
            'type_precision.required_if' => 'Précisez le type de cette structure (ex. Mission, Projet, Antenne).',
            'parent_autre.required_if' => 'Saisissez le nom de la structure dont celle-ci dépend.',
        ];
    }

    /**
     * Applique les saisies libres : le texte du type n'est gardé que pour « Autre », et une structure parente
     * saisie à la main est retrouvée par son nom (sans tenir compte des accents ni de la casse) ou créée.
     */
    private function preparer(array $donnees): array
    {
        $donnees['type_precision'] = $donnees['type'] === 'autre'
            ? mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $donnees['type_precision'])), 0, 80) : null;

        if (($donnees['parent_id'] ?? null) === self::PARENT_AUTRE) {
            $nom = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $donnees['parent_autre'])), 0, 190);
            if ($nom === '') {
                throw ValidationException::withMessages(['parent_autre' => 'Saisissez le nom de la structure dont celle-ci dépend.']);
            }
            $existante = DB::table('structures')->where('nom', $nom)->first();
            if ($existante) {
                $donnees['parent_id'] = (int) $existante->id;
            } else {
                $donnees['parent_id'] = DB::table('structures')->insertGetId([
                    'nom' => $nom, 'type' => 'autre', 'type_precision' => 'Ajoutée lors de la saisie d’une structure',
                    'parent_id' => null, 'rattachement' => $donnees['rattachement'], 'actif' => 1,
                ]);
                AuditService::enregistrer('structure_creee', null, null, ['id' => $donnees['parent_id'], 'nom' => $nom, 'origine' => 'saisie libre']);
            }
        }

        $donnees['parent_id'] = ($donnees['parent_id'] ?? '') === '' ? null : (int) $donnees['parent_id'];
        unset($donnees['parent_autre']);

        return $donnees;
    }

    private function donneesFormulaire(object $structure): array
    {
        return [
            'structure' => $structure,
            'types' => self::TYPES,
            'parents' => DB::table('structures')->where('actif', 1)
                ->when($structure->id ?? null, fn ($q, $id) => $q->where('id', '!=', $id))
                ->orderBy('nom')->pluck('nom', 'id')->all(),
        ];
    }
}
