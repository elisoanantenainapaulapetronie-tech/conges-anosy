<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Postes de l'organigramme (places vacantes, postes à créer). Lecture : tout agent via l'organigramme.
 * Modification : RH responsable seulement, comme les structures (cela change l'organisation).
 */
class PosteController extends Controller
{
    public function create(Request $request)
    {
        $this->exiger();
        $poste = new \stdClass;
        $poste->structure_id = $request->integer('structure') ?: null;

        return view('rh.postes.form', $this->donnees($poste));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->exiger();
        $donnees = $this->valider($request, null);
        $id = DB::table('postes')->insertGetId($donnees + ['actif' => 1]);
        AuditService::enregistrer('poste_cree', null, null, ['id' => $id] + $donnees);

        return $this->retour($donnees['structure_id'], 'Poste ajouté à l’organigramme.');
    }

    public function edit(int $poste)
    {
        $this->exiger();

        return view('rh.postes.form', $this->donnees($this->trouver($poste)));
    }

    public function update(Request $request, int $poste): RedirectResponse
    {
        $this->exiger();
        $actuel = $this->trouver($poste);
        $donnees = $this->valider($request, $actuel);

        DB::table('postes')->where('id', $actuel->id)->update($donnees);
        AuditService::enregistrer('poste_modifie', null,
            ['id' => $actuel->id, 'structure_id' => $actuel->structure_id, 'intitule' => $actuel->intitule, 'titulaire_id' => $actuel->titulaire_id],
            ['id' => $actuel->id] + $donnees);

        return $this->retour($donnees['structure_id'], 'Poste enregistré.');
    }

    /** Retire le poste de l'organigramme (il reste dans le journal d'audit). */
    public function retirer(int $poste): RedirectResponse
    {
        $this->exiger();
        $p = $this->trouver($poste);
        DB::table('postes')->where('id', $p->id)->update(['actif' => 0]);
        AuditService::enregistrer('poste_retire', null, ['id' => $p->id, 'intitule' => $p->intitule, 'structure_id' => $p->structure_id]);

        return $this->retour($p->structure_id, 'Poste retiré de l’organigramme.');
    }

    // ------------------------------------------------------------------

    private function exiger(): void
    {
        if (! auth()->user()->agent->aLeRole('rh_responsable')) {
            AuditService::refus('acces_refuse_postes', null, ['url' => request()->path()]);
            abort(403, 'Seul le RH responsable modifie l’organisation.');
        }
    }

    private function trouver(int $id): object
    {
        return DB::table('postes')->where('id', $id)->where('actif', 1)->first() ?? abort(404);
    }

    private function retour(int|string $structureId, string $message): RedirectResponse
    {
        return redirect(route('organigramme').'#s'.$structureId)->with('statut', $message);
    }

    private function valider(Request $request, ?object $poste): array
    {
        $structure = $request->input('structure_id');
        $donnees = $request->validate([
            'structure_id' => ['required', 'integer', Rule::exists('structures', 'id')->where('actif', 1)],
            'intitule' => ['required', 'string', 'max:150',
                Rule::unique('postes', 'intitule')
                    ->where(fn ($q) => $q->where('structure_id', $structure)->where('actif', 1))
                    ->ignore($poste->id ?? null)],
            'fonction_id' => ['nullable', 'integer', 'exists:fonctions,id'],
            'titulaire_id' => ['nullable', 'integer', Rule::exists('agents', 'id')->where('situation', 'actif')],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'structure_id.required' => 'Choisissez la structure où se trouve le poste.',
            'structure_id.exists' => 'Cette structure n’existe pas ou est désactivée.',
            'intitule.required' => 'Saisissez l’intitulé du poste.',
            'intitule.unique' => 'Ce poste existe déjà dans cette structure.',
            'titulaire_id.exists' => 'Cet agent n’existe pas ou n’est plus en activité.',
        ]);

        $donnees['intitule'] = mb_substr(trim(preg_replace('/\s+/u', ' ', $donnees['intitule'])), 0, 150);

        return [
            'structure_id' => (int) $donnees['structure_id'],
            'intitule' => $donnees['intitule'],
            'fonction_id' => ($donnees['fonction_id'] ?? null) ?: null,
            'titulaire_id' => ($donnees['titulaire_id'] ?? null) ?: null,
            'note' => ($donnees['note'] ?? null) ?: null,
        ];
    }

    private function donnees(object $poste): array
    {
        return [
            'poste' => $poste,
            'structures' => DB::table('structures')->where('actif', 1)->orderBy('nom')->pluck('nom', 'id')->all(),
            'fonctions' => DB::table('fonctions')->where('actif', 1)->orderBy('libelle')->pluck('libelle', 'id')->all(),
            'agents' => Agent::actifs()->orderBy('nom')->orderBy('prenoms')->get(['id', 'nom', 'prenoms', 'identifiant'])
                ->mapWithKeys(fn ($a) => [$a->id => mb_strtoupper($a->nom).' '.$a->prenoms.' ('.$a->identifiant.')'])->all(),
        ];
    }
}
