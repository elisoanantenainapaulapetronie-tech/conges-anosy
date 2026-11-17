<?php

namespace App\Http\Controllers\Rh;

use App\Exceptions\SoldeException;
use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\SoldeProposition;
use App\Policies\SoldePolicy;
use App\Services\AuditService;
use App\Services\AcquisitionService;
use App\Services\DecompteService;
use App\Services\ImputationService;
use App\Services\SoldeJustificatifService;
use App\Services\SoldeNotifications;
use App\Services\SoldeService;
use App\Support\NombreSaisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Soldes côté RH : liste, fiche d'un agent (registre), propositions de mouvement (avec justificatif), contre-validation,
 * retrait d'une proposition, historique des corrections, acquisition mensuelle.
 * Les droits viennent de SoldePolicy (« personne n'écrit ni ne contrôle son propre solde »).
 */
class SoldeRhController extends Controller
{
    use Autorise;

    /** Qui contre-valide, en clair (affichage). */
    public const CONTROLEURS = [
        'rh' => 'un autre agent RH',
        'rh_responsable' => 'la RH responsable',
        'directeur_affaires_generales' => 'le Directeur des Affaires générales',
        'secretaire_general' => 'le Secrétaire Général',
        'chef_region' => 'le Chef de Région',
        'autorite_externe' => 'une autorité extérieure (décision enregistrée par la RH responsable)',
    ];

    public function index(Request $request)
    {
        $this->autoriser('voirListe', Agent::class);

        $annee = $this->annee($request->query('annee'));
        $q = trim((string) $request->query('q', ''));
        $filtre = $request->query('filtre');

        $agents = DB::table('agents as a')
            ->leftJoin('structures as s', 's.id', '=', 'a.structure_id')
            ->leftJoin('fonctions as f', 'f.id', '=', 'a.fonction_id')
            ->where('a.situation', 'actif')
            ->when($q !== '', function ($req) use ($q) {
                $m = '%'.addcslashes($q, '%_\\').'%';
                $req->where(fn ($w) => $w->where('a.nom', 'like', $m)->orWhere('a.prenoms', 'like', $m)->orWhere('a.identifiant', 'like', $m)->orWhere('a.matricule', 'like', $m));
            })
            ->orderBy('a.nom')->orderBy('a.prenoms')
            ->get(['a.id', 'a.identifiant', 'a.nom', 'a.prenoms', 's.nom as structure', 'f.libelle as fonction']);

        $soldes = DB::table('soldes_mouvements')->where('annee', $annee)->groupBy('agent_id')
            ->get(['agent_id', DB::raw('SUM(delta_disponible) AS d'), DB::raw('SUM(delta_reserve) AS r'), DB::raw('SUM(delta_consomme) AS c')])
            ->keyBy('agent_id');
        $attente = DB::table('demandes as d')->join('types_demande as t', 't.id', '=', 'd.type_demande_id')
            ->whereYear('d.date_debut', $annee)->whereIn('d.statut', SoldeService::STATUTS_EN_ATTENTE)->where('t.decompte_solde', 'oui')
            ->groupBy('d.agent_id')
            ->get(['d.agent_id', DB::raw('SUM(d.duree_jours) AS jours')])->pluck('jours', 'agent_id');
        $propositions = DB::table('soldes_propositions')->where('statut', 'en_attente')->groupBy('agent_id')
            ->get(['agent_id', DB::raw('COUNT(*) AS nombre')])->pluck('nombre', 'agent_id');

        $lignes = $agents->map(function ($a) use ($soldes, $attente, $propositions) {
            $s = $soldes->get($a->id);
            $a->enregistre = $s !== null;
            $a->disponible = $s ? (float) $s->d : 0.0;
            $a->reserves = $s ? (float) $s->r : 0.0;
            $a->consommes = $s ? (float) $s->c : 0.0;
            $a->en_attente = (float) ($attente[$a->id] ?? 0);
            $a->previsionnel = $a->disponible - $a->en_attente;
            $a->propositions = (int) ($propositions[$a->id] ?? 0);

            return $a;
        });

        $sansSolde = $lignes->where('enregistre', false)->count();
        if ($filtre === 'sans_solde') {
            $lignes = $lignes->where('enregistre', false)->values();
        } elseif ($filtre === 'a_controler') {
            $lignes = $lignes->where('propositions', '>', 0)->values();
        }

        return view('rh.soldes.index', [
            'lignes' => $lignes, 'annee' => $annee, 'annees' => $this->anneesChoix(), 'q' => $q, 'filtre' => $filtre,
            'chiffres' => [
                'agents' => $agents->count(), 'sans_solde' => $sansSolde,
                'propositions' => DB::table('soldes_propositions')->where('statut', 'en_attente')->count(),
                'reserves' => round($soldes->sum(fn ($s) => (float) $s->r), 2),
            ],
            'peutCrediter' => $request->user()->agent->aLeRole('rh_responsable'),
        ]);
    }

    public function show(Request $request, Agent $agent)
    {
        $this->autoriser('solde.voir', $agent);

        $annees = SoldeService::annees($agent->id);
        $annee = (int) $request->query('annee', $annees[0]);
        if (! in_array($annee, $annees, true)) {
            $annee = $annees[0];
        }
        $agent->loadMissing('fonction', 'structure');
        $moi = $request->user()->agent;
        $peutProposer = Gate::allows('solde.proposer', $agent);
        $controleur = app(SoldePolicy::class)->controleurAttendu($agent);

        return view('rh.soldes.show', [
            'agent' => $agent, 'annee' => $annee, 'annees' => $annees, 'anneesSaisie' => SoldeService::anneesAcquisition((int) $agent->id),
            'situation' => SoldeService::situation($agent->id, $annee),
            'registre' => SoldeService::registre($agent->id, $annee),
            'enAttente' => SoldeService::propositionsEnAttente($agent->id),
            'historique' => SoldeService::historique()->where('p.agent_id', $agent->id)->limit(10)->get(),
            'pieces' => SoldeJustificatifService::parProposition(SoldeService::propositionsEnAttente($agent->id)->pluck('id')
                ->merge(DB::table('soldes_propositions')->where('agent_id', $agent->id)->where('statut', '!=', 'en_attente')->pluck('id'))),
            'moiId' => (int) $moi->id,
            'peutProposer' => $peutProposer,
            'repriseOuverte' => ! SoldeService::repriseFermee((int) $agent->id),
            'lots' => ImputationService::tableau($agent->id),
            'natures' => $this->naturesPermises($moi),
            'controleur' => self::CONTROLEURS[$controleur] ?? $controleur,
        ]);
    }

    public function proposer(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriser('solde.proposer', $agent);
        $moi = $request->user()->agent;

        $natures = $this->naturesPermises($moi);
        // « 2,5 », « 2.5 », « -1,5 » : on convertit AVANT de valider (la règle « numeric » refuse la virgule).
        $lu = NombreSaisi::normaliser($request->input('jours'));
        $request->merge(['jours' => $lu ?? $request->input('jours')]);
        $donnees = $request->validate([
            'annee' => ['required', 'integer', 'min:2000', 'max:'.(int) date('Y')],
            'nature' => ['required', 'in:'.implode(',', array_keys($natures))],
            'jours' => ['required', 'numeric'],
            'motif' => ['required', 'string', 'max:500'],
            'reference_piece' => ['nullable', 'string', 'max:190'],
        ] + SoldeJustificatifService::regles(true), SoldeJustificatifService::messages() + [
            'nature.in' => 'Ce type de mouvement n’est pas proposé : choisissez « Solde initial » ou « Correction ».',
            'jours.required' => 'Indiquez le nombre de jours.',
            'jours.numeric' => 'Le nombre de jours doit être un nombre (ex. 2,5 ou -1,5).',
            'motif.required' => 'Expliquez le motif du mouvement.',
        ]);

        // Autres propositions déjà en attente sur le même agent et la même année : simple avertissement, pas un blocage.
        $autres = DB::table('soldes_propositions')->where('agent_id', $agent->id)->where('annee', (int) $donnees['annee'])->where('statut', 'en_attente')->count();

        try {
            // La proposition ET son justificatif sont enregistrés ensemble : si le fichier échoue, la proposition est annulée.
            $id = DB::transaction(function () use ($moi, $agent, $donnees, $request) {
                $id = SoldeService::proposer($moi, $agent, (int) $donnees['annee'], $donnees['nature'], (float) $donnees['jours'],
                    $donnees['motif'], $donnees['reference_piece'] ?? null);
                SoldeJustificatifService::attacher($id, $request->file('justificatif'), $moi);

                return $id;
            });
        } catch (SoldeException $e) {
            return back()->withInput()->withErrors(['mouvement' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['justificatif' => $e->getMessage()]);
        }

        SoldeNotifications::aControler($agent, $moi, $id);

        $retour = redirect()->route('rh.soldes.show', ['agent' => $agent->id, 'annee' => $donnees['annee']])
            ->with('statut', 'Proposition enregistrée avec son justificatif. Le solde ne change qu’après contrôle par '.self::CONTROLEURS[app(SoldePolicy::class)->controleurAttendu($agent)].'.');

        return $autres > 0
            ? $retour->with('avertissement', "Attention : {$autres} autre(s) proposition(s) attendent déjà leur contrôle pour cet agent et l’année {$donnees['annee']}. Vérifiez qu’il ne s’agit pas d’un doublon ; vous pouvez retirer l’une d’elles.")
            : $retour;
    }

    /**
     * Reprise de l'historique : jours DÉJÀ PRIS avant la mise en service de l'application. Une proposition par lot débité
     * (le lot est un fait historique, déclaré par l'auteur ; à défaut, un total est réparti du plus ancien au plus récent),
     * avec le justificatif et la référence du document. Contre-validée comme une correction.
     */
    public function reprise(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriser('solde.proposer', $agent);
        $moi = $request->user()->agent;

        $donnees = $request->validate([
            'motif' => ['required', 'string', 'max:500'],
            'reference_piece' => ['required', 'string', 'max:190'],
            'total' => ['nullable', 'string', 'max:12'],
            'lots' => ['nullable', 'array', 'max:10'],
            'lots.*' => ['nullable', 'string', 'max:12'],
        ] + SoldeJustificatifService::regles(true), SoldeJustificatifService::messages() + [
            'motif.required' => 'Expliquez le motif de la reprise.',
            'reference_piece.required' => 'Indiquez la référence du document (registre des congés, décisions).',
        ]);

        $parAnnee = [];
        foreach (($donnees['lots'] ?? []) as $annee => $valeur) {
            $valeur = trim((string) $valeur);
            if ($valeur === '') {
                continue;
            }
            $n = NombreSaisi::normaliser($valeur);
            if ($n === null || (float) $n <= 0) {
                return back()->withInput()->withErrors(['reprise' => "Lot {$annee} : « {$valeur} » n’est pas un nombre de jours valide (ex. 2,5)."]);
            }
            $parAnnee[(int) $annee] = (float) $n;
        }
        $total = trim((string) ($donnees['total'] ?? ''));
        $totalLu = $total === '' ? null : NombreSaisi::normaliser($total);
        if ($total !== '' && $totalLu === null) {
            return back()->withInput()->withErrors(['reprise' => 'Le total des jours déjà pris doit être un nombre (ex. 12).']);
        }
        if ($parAnnee !== [] && $totalLu !== null) {
            return back()->withInput()->withErrors(['reprise' => 'Remplissez soit les jours par lot, soit un total à répartir, pas les deux.']);
        }

        try {
            if ($totalLu !== null) {
                $parAnnee = SoldeService::repartirReprise((int) $agent->id, (float) $totalLu);
            }
            // Toutes les propositions et leur justificatif sont enregistrés ensemble : une erreur annule tout.
            $ids = DB::transaction(function () use ($moi, $agent, $parAnnee, $donnees, $request) {
                $ids = SoldeService::proposerReprise($moi, $agent, $parAnnee, $donnees['motif'], $donnees['reference_piece']);
                foreach ($ids as $id) {
                    SoldeJustificatifService::attacher($id, $request->file('justificatif'), $moi);
                }

                return $ids;
            });
        } catch (SoldeException $e) {
            return back()->withInput()->withErrors(['reprise' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['justificatif' => $e->getMessage()]);
        }

        foreach ($ids as $id) {
            SoldeNotifications::aControler($agent, $moi, $id);
        }
        ksort($parAnnee);
        $detail = collect($parAnnee)->map(fn ($j, $a) => DecompteService::libelle((float) $j).' sur le lot '.$a)->implode(', ');

        return redirect()->route('rh.soldes.show', ['agent' => $agent->id, 'annee' => array_key_first($parAnnee)])
            ->with('statut', 'Reprise de l’historique enregistrée ('.$detail.'). Le solde ne change qu’après contrôle par la RH responsable.');
    }

    /** File des propositions à contrôler : chacun ne voit les boutons que pour celles qu'il a le droit de contrôler. */
    public function propositions(Request $request)
    {
        $moi = $request->user()->agent;
        if (! $moi->aLeRole('rh_gestionnaire', 'rh_responsable', 'secretaire_general', 'chef_region')) {
            AuditService::refus('acces_refuse_file_controle', $moi, []);
            abort(403);
        }
        $politique = app(SoldePolicy::class);

        $liste = SoldeService::propositionsEnAttente()->map(function ($p) use ($politique, $moi) {
            $modele = SoldeProposition::find($p->id);
            $attendu = $politique->controleurAttendu(Agent::findOrFail($p->agent_id));
            $niveau = SoldeService::niveauSupplementaire($p->nature, $attendu);

            $autorise = $modele !== null && Gate::allows('controler', $modele);
            $niveauAtteint = $niveau === null || $moi->aLeRole($niveau);
            $p->peut_controler = $autorise && $niveauAtteint;
            $p->controleur_attendu = self::CONTROLEURS[$niveau ?? $attendu] ?? $attendu;

            return $p;
        });
        // Même agent, même année, plusieurs propositions en attente : signalé sur chacune (doublon possible).
        $compte = $liste->countBy(fn ($p) => $p->agent_id.'-'.$p->annee);
        $liste->each(fn ($p) => $p->memes = $compte[$p->agent_id.'-'.$p->annee] - 1);

        return view('rh.soldes.propositions', [
            'propositions' => $liste, 'natures' => SoldeService::NATURES, 'moi' => $moi,
            'estRh' => $moi->aLeRole('rh_gestionnaire', 'rh_responsable'),
            'pieces' => SoldeJustificatifService::parProposition($liste->pluck('id')),
        ]);
    }

    public function controler(Request $request, int $proposition): RedirectResponse
    {
        $modele = SoldeProposition::findOrFail($proposition);
        $this->autoriser('controler', $modele);
        $moi = $request->user()->agent;

        $cible = Agent::findOrFail($modele->agent_id);
        $niveau = SoldeService::niveauSupplementaire($modele->nature, app(SoldePolicy::class)->controleurAttendu($cible));
        if ($niveau === 'rh_responsable' && ! $moi->aLeRole('rh_responsable')) {
            AuditService::refus('acces_refuse_controler_niveau', $modele, ['exige' => $niveau]);
            abort(403, 'Une correction est contre-validée par la RH responsable.');
        }

        $donnees = $request->validate([
            'decision' => ['required', 'in:valider,rejeter'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            SoldeService::controler($moi, (int) $modele->id, $donnees['decision'] === 'valider', $donnees['commentaire'] ?? null);
        } catch (SoldeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        $valide = $donnees['decision'] === 'valider';
        $modele->refresh();
        SoldeNotifications::controlee($modele, $valide, $donnees['commentaire'] ?? null);

        $resume = (SoldeService::NATURES[$modele->nature] ?? $modele->nature).' '.($modele->jours > 0 ? '+' : '−')
            .DecompteService::libelle(abs((float) $modele->jours)).' sur '.$modele->annee.' pour '.$cible->nom_complet;
        $auteur = Agent::find($modele->propose_par);

        if ($valide) {
            $prevenus = $auteur?->nom_complet.((int) $cible->id !== (int) $modele->propose_par ? ' (auteur) et '.$cible->nom_complet.' (agent concerné)' : ' (auteur)');

            return back()->with('statut', "Validé : {$resume}. Le mouvement est inscrit au registre et le solde est à jour. Notification envoyée à {$prevenus}.");
        }

        return back()->with('statut', "Rejeté : {$resume}. Le solde n’a pas changé. Notification envoyée à ".($auteur?->nom_complet ?? 'l’auteur').' (auteur) avec votre motif.');
    }

    /** L'auteur retire sa proposition avant qu'elle soit contrôlée (motif obligatoire, date conservée). */
    public function retirer(Request $request, int $proposition): RedirectResponse
    {
        $modele = SoldeProposition::findOrFail($proposition);
        $this->autoriser('retirer', $modele);
        $moi = $request->user()->agent;

        $donnees = $request->validate(['motif_retrait' => ['required', 'string', 'min:3', 'max:500']], [
            'motif_retrait.required' => 'Indiquez pourquoi vous retirez cette proposition.',
            'motif_retrait.min' => 'Indiquez pourquoi vous retirez cette proposition.',
        ]);

        try {
            SoldeService::retirer($moi, (int) $modele->id, $donnees['motif_retrait']);
        } catch (SoldeException $e) {
            return back()->with('erreur', $e->getMessage());
        }

        SoldeNotifications::retiree($modele->refresh(), $moi, $donnees['motif_retrait']);

        return back()->with('statut', 'Proposition retirée : le solde n’a jamais changé. Elle est conservée dans l’historique avec votre motif.');
    }

    /** Historique des corrections : toutes les propositions déjà traitées (validées, rejetées, retirées). */
    public function historique(Request $request)
    {
        $moi = $request->user()->agent;
        if (! $moi->aLeRole('rh_gestionnaire', 'rh_responsable', 'secretaire_general', 'chef_region')) {
            AuditService::refus('acces_refuse_historique_corrections', $moi, []);
            abort(403);
        }

        $filtres = [
            'agent' => (string) $request->query('agent', ''),
            'annee' => $request->query('annee'),
            'statut' => (string) $request->query('statut', ''),
            'nature' => (string) $request->query('nature', ''),
        ];
        $liste = SoldeService::historique($filtres)->paginate(25)->withQueryString();

        return view('rh.soldes.historique', [
            'liste' => $liste, 'filtres' => $filtres, 'natures' => SoldeService::NATURES,
            'annees' => DB::table('soldes_propositions')->distinct()->orderByDesc('annee')->pluck('annee'),
            'pieces' => SoldeJustificatifService::parProposition($liste->pluck('id')),
            'estRh' => $moi->aLeRole('rh_gestionnaire', 'rh_responsable'),
        ]);
    }

    /** Sert un justificatif (fichier privé) à une personne autorisée ; chaque consultation est inscrite au journal. */
    public function justificatif(Request $request, int $piece)
    {
        $p = DB::table('pieces_jointes')->where('id', $piece)->whereNotNull('proposition_id')->first();
        abort_if($p === null, 404);
        $modele = SoldeProposition::findOrFail($p->proposition_id);
        $this->autoriser('lireJustificatif', $modele);

        $disque = Storage::disk('local');
        abort_unless($disque->exists($p->chemin), 404, 'Le fichier est introuvable sur le serveur.');

        AuditService::enregistrer('justificatif_solde_consulte', $modele, null, ['piece_id' => (int) $p->id]);

        return $disque->response($p->chemin, $p->nom_original, [
            'Content-Type' => $p->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    /**
     * Acquisition mensuelle (lot 5 ter) : rattrape les mois manquants jusqu'au mois en cours, pour tous les agents actifs
     * (RH responsable). Ne crédite jamais deux fois le même mois.
     */
    public function crediter(Request $request): RedirectResponse
    {
        $moi = $request->user()->agent;
        if (! $moi->aLeRole('rh_responsable')) {
            AuditService::refus('acces_refuse_crediter_droits', null, ['url' => $request->path()]);
            abort(403, 'Le crédit de l’acquisition mensuelle est réservé à la RH responsable.');
        }
        $r = AcquisitionService::rattraper(date('Y-m'));

        return redirect()->route('rh.soldes.index', ['annee' => $this->annee($request->input('annee'))])->with('statut',
            "Acquisition mensuelle jusqu’à ".AcquisitionService::libelleMois($r['jusqua']).' : '.$r['mois'].' mois crédité(s) pour '.$r['agents'].' agent(s), soit '
            .DecompteService::libelle($r['jours']).'. Les mois déjà crédités, les années fixées par un solde initial et les mois non acquis sont ignorés.');
    }

    // ------------------------------------------------------------------

    private function annee(mixed $valeur): int
    {
        $a = (int) $valeur;

        return ($a >= 2000 && $a <= (int) date('Y') + 1) ? $a : (int) date('Y');
    }

    private function anneesChoix(): array
    {
        $courante = (int) date('Y');

        return [$courante + 1, $courante, $courante - 1, $courante - 2];
    }

    /** Types de mouvement que cette personne peut proposer. */
    /**
     * Mouvements que l'on peut PROPOSER depuis l'écran (lot 5 quater) : solde initial et correction.
     * Le report est devenu un solde initial de l'année d'origine ; la perte est une correction négative ;
     * l'indemnisation sera une régularisation dans le parcours de sortie d'un agent.
     * (SoldeService accepte toujours les anciennes natures : les mouvements déjà inscrits restent lisibles.)
     */
    private function naturesPermises(Agent $moi): array
    {
        return array_intersect_key(SoldeService::NATURES, array_flip(['solde_initial', 'correction']));
    }
}
