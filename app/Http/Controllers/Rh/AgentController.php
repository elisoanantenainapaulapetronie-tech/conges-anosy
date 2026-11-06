<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\OrganisationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Annuaire et fiches des agents (espace RH). Un agent n'est jamais supprimé : il passe à « sorti ». */
class AgentController extends Controller
{
    use Autorise;

    private const CHAMPS_FICHE = [
        'matricule', 'nom', 'prenoms', 'sexe', 'date_naissance', 'telephone', 'email', 'fonction_id',
        'grade_categorie', 'structure_id', 'responsable_id', 'rattachement', 'date_entree',
    ];

    public function index(Request $request)
    {
        $this->autoriser('voirListe', Agent::class);

        $q = trim((string) $request->query('q', ''));
        $structure = $request->query('structure');
        $situation = $request->query('situation', 'actif');
        $etat = $request->query('compte');

        $agents = Agent::query()->with(['fonction', 'structure', 'compte'])
            ->when($q !== '', function ($req) use ($q) {
                $motif = '%'.addcslashes($q, '%_\\').'%';
                $req->where(fn ($w) => $w->where('nom', 'like', $motif)->orWhere('prenoms', 'like', $motif)
                    ->orWhere('matricule', 'like', $motif)->orWhere('identifiant', 'like', $motif));
            })
            ->when(filled($structure), fn ($req) => $req->where('structure_id', (int) $structure))
            ->when(in_array($situation, ['actif', 'suspendu', 'sorti'], true), fn ($req) => $req->where('situation', $situation))
            ->when($etat === 'sans', fn ($req) => $req->whereDoesntHave('compte'))
            ->when(in_array($etat, ['actif', 'en_attente_activation', 'desactive'], true),
                fn ($req) => $req->whereHas('compte', fn ($c) => $c->where('statut', $etat)))
            ->orderBy('nom')->orderBy('prenoms')
            ->simplePaginate(15)->withQueryString();

        // Rôles en cours des agents affichés, en une seule requête.
        $roles = DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->whereIn('habilitations.agent_id', $agents->pluck('id'))
            ->whereDate('habilitations.date_debut', '<=', today())
            ->where(fn ($w) => $w->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()))
            ->select('habilitations.agent_id', 'roles.libelle')
            ->get()->groupBy('agent_id');

        $structures = DB::table('structures')->orderBy('nom')->pluck('nom', 'id');

        return view('rh.agents.index', compact('agents', 'roles', 'structures', 'q', 'structure', 'situation', 'etat'));
    }

    public function create()
    {
        $this->autoriser('creer', Agent::class);

        return view('rh.agents.form', ['agent' => new Agent(['rattachement' => 'sg']), 'liste' => $this->listes(null)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->autoriser('creer', Agent::class);

        $donnees = $request->validate($this->regles(null), $this->messages());

        $agent = DB::transaction(function () use ($donnees) {
            $agent = Agent::create(Arr::only($donnees, self::CHAMPS_FICHE));

            DB::table('affectations_historique')->insert([
                'agent_id' => $agent->id,
                'structure_id' => $agent->structure_id,
                'responsable_id' => $agent->responsable_id,
                'rattachement' => $agent->rattachement,
                'date_debut' => Carbon::parse($donnees['date_entree'])->toDateString(),
                'motif' => 'Création de la fiche',
                'modifie_par' => auth()->user()->agent_id,
            ]);

            return $agent;
        });

        AuditService::enregistrer('agent_cree', $agent, null, Arr::only($donnees, self::CHAMPS_FICHE));

        return redirect()->route('rh.agents.show', $agent)
            ->with('statut', 'Fiche créée. Identifiant de connexion : '.$agent->fresh()->identifiant.'. Créez maintenant son compte pour obtenir le code d’activation.');
    }

    public function show(Agent $agent)
    {
        $this->autoriser('voir', $agent);

        $agent->load(['fonction', 'structure', 'responsable', 'compte']);
        $aujourdhui = today()->toDateString();

        $habilitations = DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->leftJoin('structures', 'structures.id', '=', 'habilitations.structure_id')
            ->leftJoin('agents as att', 'att.id', '=', 'habilitations.attribuee_par')
            ->where('habilitations.agent_id', $agent->id)
            ->orderByDesc('habilitations.date_debut')->orderByDesc('habilitations.id')
            ->select('habilitations.*', 'roles.libelle as role_libelle', 'structures.nom as structure_nom',
                DB::raw("TRIM(CONCAT_WS(' ', att.prenoms, UPPER(att.nom))) as attribuee_par_nom"))
            ->get();

        $affectations = DB::table('affectations_historique as a')
            ->join('structures as s', 's.id', '=', 'a.structure_id')
            ->leftJoin('agents as r', 'r.id', '=', 'a.responsable_id')
            ->where('a.agent_id', $agent->id)
            ->orderByDesc('a.date_debut')->orderByDesc('a.id')
            ->select('a.*', 's.nom as structure_nom', DB::raw("TRIM(CONCAT_WS(' ', r.prenoms, UPPER(r.nom))) as responsable_nom"))
            ->get();

        $equipe = $agent->equipe()->actifs()->orderBy('nom')->get();
        $roles = DB::table('roles')->orderBy('id')->get(['id', 'code', 'libelle', 'description']);
        $structures = DB::table('structures')->where('actif', 1)->orderBy('nom')->pluck('nom', 'id');

        return view('rh.agents.show', compact('agent', 'habilitations', 'affectations', 'equipe', 'roles', 'structures', 'aujourdhui'));
    }

    public function edit(Agent $agent)
    {
        $this->autoriser('modifier', $agent);

        return view('rh.agents.form', ['agent' => $agent, 'liste' => $this->listes($agent)]);
    }

    public function update(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriser('modifier', $agent);

        $donnees = $request->validate($this->regles($agent), $this->messages());
        $this->controlerResponsable($agent, $donnees);
        $this->controlerSituation($agent, $donnees);

        $avant = $agent->only([...self::CHAMPS_FICHE, 'situation', 'date_sortie']);
        $circuitChange = (int) $agent->structure_id !== (int) $donnees['structure_id']
            || (int) $agent->responsable_id !== (int) ($donnees['responsable_id'] ?? 0)
            || $agent->rattachement !== $donnees['rattachement'];
        $quitte = $donnees['situation'] !== 'actif' && $agent->situation === 'actif';

        DB::transaction(function () use ($agent, $donnees, $circuitChange, $quitte) {
            $agent->fill(Arr::only($donnees, [...self::CHAMPS_FICHE, 'situation']));
            $agent->date_sortie = $donnees['situation'] === 'sorti' ? $donnees['date_sortie'] : null;
            $agent->save();

            if ($circuitChange) {
                DB::table('affectations_historique')->where('agent_id', $agent->id)->whereNull('date_fin')
                    ->update(['date_fin' => DB::raw('GREATEST(date_debut, CURDATE())')]);
                DB::table('affectations_historique')->insert([
                    'agent_id' => $agent->id,
                    'structure_id' => $agent->structure_id,
                    'responsable_id' => $agent->responsable_id,
                    'rattachement' => $agent->rattachement,
                    'date_debut' => max(today(), Carbon::parse($agent->date_entree))->toDateString(),
                    'motif' => $donnees['motif_affectation'] ?? 'Modification de la fiche',
                    'modifie_par' => auth()->user()->agent_id,
                ]);
            }

            // Un agent qui part ou est suspendu perd ses rôles à compter d'hier : plus aucun droit.
            if ($quitte) {
                DB::table('habilitations')->where('agent_id', $agent->id)
                    ->where(fn ($q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', today()))
                    ->update(['date_fin' => DB::raw('GREATEST(date_debut, DATE_SUB(CURDATE(), INTERVAL 1 DAY))')]);
            }
        });

        AuditService::enregistrer('agent_modifie', $agent, $avant,
            $agent->fresh()->only([...self::CHAMPS_FICHE, 'situation', 'date_sortie']));

        return redirect()->route('rh.agents.show', $agent)->with('statut', 'Fiche enregistrée.');
    }

    // ------------------------------------------------------------------

    private function regles(?Agent $agent): array
    {
        $id = $agent?->id;

        $regles = [
            'nom' => ['required', 'string', 'max:120'],
            'prenoms' => ['nullable', 'string', 'max:160'],
            'sexe' => ['required', Rule::in(['M', 'F'])],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'matricule' => ['nullable', 'string', 'max:30',
                Rule::unique('agents', 'matricule')->ignore($id), Rule::unique('agents', 'identifiant')->ignore($id)],
            'telephone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s().-]{6,30}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'fonction_id' => ['nullable', 'integer', 'exists:fonctions,id'],
            'grade_categorie' => ['nullable', 'string', 'max:60'],
            'structure_id' => ['required', 'integer',
                Rule::exists('structures', 'id')->where(fn ($q) => $q->where('actif', 1)->orWhere('id', $agent?->structure_id))],
            'responsable_id' => ['nullable', 'integer',
                Rule::exists('agents', 'id')->where(fn ($q) => $q->where('situation', 'actif')->orWhere('id', $agent?->responsable_id))],
            'rattachement' => ['required', Rule::in(['sg', 'region'])],
            'date_entree' => ['required', 'date'],
        ];

        if ($agent) {
            $regles['responsable_id'][] = Rule::notIn([$id]);
            $regles['situation'] = ['required', Rule::in(['actif', 'suspendu', 'sorti'])];
            $regles['date_sortie'] = ['nullable', 'date', 'after_or_equal:date_entree', 'required_if:situation,sorti'];
            $regles['motif_affectation'] = ['nullable', 'string', 'max:255'];
        }

        return $regles;
    }

    private function messages(): array
    {
        return [
            'nom.required' => 'Saisissez le nom.',
            'sexe.required' => 'Indiquez le sexe (utile pour la maternité et la paternité).',
            'matricule.unique' => 'Ce matricule est déjà utilisé par un autre agent.',
            'telephone.regex' => 'Numéro de téléphone invalide.',
            'email.email' => 'Adresse e-mail invalide.',
            'structure_id.required' => 'Choisissez le service ou la direction.',
            'structure_id.exists' => 'Cette structure n’existe pas ou n’est plus active.',
            'responsable_id.exists' => 'Ce responsable n’est pas un agent actif.',
            'responsable_id.not_in' => 'Un agent ne peut pas être son propre responsable.',
            'rattachement.required' => 'Indiquez le circuit de décision (Secrétaire Général ou Chef de Région).',
            'date_entree.required' => 'Saisissez la date d’entrée.',
            'date_naissance.before' => 'La date de naissance doit être passée.',
            'date_sortie.required_if' => 'Saisissez la date de sortie.',
            'date_sortie.after_or_equal' => 'La date de sortie ne peut pas précéder la date d’entrée.',
        ];
    }

    private function listes(?Agent $agent): array
    {
        $structures = DB::table('structures')
            ->where(fn ($q) => $q->where('actif', 1)->orWhere('id', $agent?->structure_id))
            ->orderBy('nom')->get(['id', 'nom', 'rattachement']);

        $responsables = Agent::query()
            ->where(fn ($q) => $q->where('situation', 'actif')->orWhere('id', $agent?->responsable_id))
            ->when($agent, fn ($q) => $q->where('id', '!=', $agent->id))
            ->orderBy('nom')->orderBy('prenoms')->get();

        return [
            'structures' => $structures->pluck('nom', 'id')->all(),
            'rattachements' => $structures->pluck('rattachement', 'id')->all(),
            'fonctions' => DB::table('fonctions')->orderBy('libelle')->pluck('libelle', 'id')->all(),
            'responsables' => $responsables->mapWithKeys(fn ($r) => [$r->id => $r->nom_complet.' ('.$r->identifiant.')'])->all(),
        ];
    }

    /** Le responsable ne peut pas être un de ses propres subordonnés (boucle dans la hiérarchie). */
    private function controlerResponsable(Agent $agent, array $donnees): void
    {
        $responsable = (int) ($donnees['responsable_id'] ?? 0);
        if ($responsable && in_array($responsable, OrganisationService::subordonnes($agent->id), true)) {
            throw ValidationException::withMessages([
                'responsable_id' => 'Ce responsable est lui-même placé sous la responsabilité de cet agent.',
            ]);
        }
    }

    /** On ne met pas « sorti » ou « suspendu » un agent dont dépend encore une équipe ou une fonction clé. */
    private function controlerSituation(Agent $agent, array $donnees): void
    {
        if ($donnees['situation'] === 'actif' || $agent->situation !== 'actif') {
            return;
        }

        $equipe = Agent::actifs()->where('responsable_id', $agent->id)->count();
        if ($equipe > 0) {
            throw ValidationException::withMessages([
                'situation' => "Cet agent est encore le responsable direct de {$equipe} agent(s) actif(s) : changez d’abord leur responsable.",
            ]);
        }

        foreach (['rh_responsable' => 'RH responsable', 'secretaire_general' => 'Secrétaire Général', 'chef_region' => 'Chef de Région'] as $code => $libelle) {
            if (OrganisationService::aRoleActif($agent->id, $code) && OrganisationService::autresDetenteurs($code, $agent->id) === 0) {
                throw ValidationException::withMessages([
                    'situation' => "Cet agent est le seul « {$libelle} » : attribuez d’abord ce rôle à un autre agent.",
                ]);
            }
        }
    }
}
