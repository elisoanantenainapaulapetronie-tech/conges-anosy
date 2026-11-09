<?php

namespace App\Http\Controllers\Rh;

use App\Exceptions\SoldeException;
use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\SoldeProposition;
use App\Policies\SoldePolicy;
use App\Services\AuditService;
use App\Services\DecompteService;
use App\Services\NotificationService;
use App\Services\SoldeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Soldes côté RH : liste, fiche d'un agent (registre), propositions de mouvement et contre-validation, droit annuel.
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
            ->groupBy('d.agent_id')->pluck(DB::raw('SUM(d.duree_jours)'), 'd.agent_id');
        $propositions = DB::table('soldes_propositions')->where('statut', 'en_attente')->groupBy('agent_id')->pluck(DB::raw('COUNT(*)'), 'agent_id');

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
            'agent' => $agent, 'annee' => $annee, 'annees' => $annees,
            'situation' => SoldeService::situation($agent->id, $annee),
            'registre' => SoldeService::registre($agent->id, $annee),
            'enAttente' => SoldeService::propositionsEnAttente($agent->id),
            'historique' => DB::table('soldes_propositions as p')->join('agents as a', 'a.id', '=', 'p.propose_par')
                ->where('p.agent_id', $agent->id)->where('p.statut', '!=', 'en_attente')->orderByDesc('p.controle_le')->limit(10)
                ->get(['p.*', 'a.nom as auteur_nom', 'a.prenoms as auteur_prenoms']),
            'peutProposer' => $peutProposer,
            'natures' => $this->naturesPermises($moi),
            'controleur' => self::CONTROLEURS[$controleur] ?? $controleur,
        ]);
    }

    public function proposer(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriser('solde.proposer', $agent);
        $moi = $request->user()->agent;

        $natures = $this->naturesPermises($moi);
        $donnees = $request->validate([
            'annee' => ['required', 'integer', 'min:2000', 'max:'.((int) date('Y') + 1)],
            'nature' => ['required', 'in:'.implode(',', array_keys($natures))],
            'jours' => ['required', 'numeric'],
            'motif' => ['required', 'string', 'max:500'],
            'reference_piece' => ['nullable', 'string', 'max:190'],
        ], [
            'nature.in' => 'Ce type de mouvement est réservé à la RH responsable.',
            'jours.required' => 'Indiquez le nombre de jours.',
            'jours.numeric' => 'Le nombre de jours doit être un nombre (ex. 2,5).',
            'motif.required' => 'Expliquez le motif du mouvement.',
        ]);

        try {
            $id = SoldeService::proposer($moi, $agent, (int) $donnees['annee'], $donnees['nature'], (float) str_replace(',', '.', (string) $donnees['jours']),
                $donnees['motif'], $donnees['reference_piece'] ?? null);
        } catch (SoldeException $e) {
            return back()->withInput()->withErrors(['mouvement' => $e->getMessage()]);
        }

        $this->notifierControleurs($agent, $moi, $id);

        return redirect()->route('rh.soldes.show', ['agent' => $agent->id, 'annee' => $donnees['annee']])
            ->with('statut', 'Proposition enregistrée. Le solde ne change qu’après contrôle par '.self::CONTROLEURS[app(SoldePolicy::class)->controleurAttendu($agent)].'.');
    }

    /** File des propositions à contrôler : chacun ne voit les boutons que pour celles qu'il a le droit de contrôler. */
    public function propositions(Request $request)
    {
        $this->autoriser('voirListe', Agent::class);
        $moi = $request->user()->agent;
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

        return view('rh.soldes.propositions', ['propositions' => $liste, 'natures' => SoldeService::NATURES, 'moi' => $moi]);
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
        app(NotificationService::class)->notifier((int) $modele->propose_par, 'solde_controle',
            $valide ? 'Mouvement de solde validé' : 'Mouvement de solde rejeté',
            ($valide ? 'Votre proposition a été validée et inscrite au registre.' : 'Votre proposition a été rejetée : '.($donnees['commentaire'] ?? '')),
            ['lien' => route('rh.soldes.show', $modele->agent_id)]);

        return back()->with('statut', $valide ? 'Proposition validée : le mouvement est inscrit au registre.' : 'Proposition rejetée.');
    }

    /** Droit annuel de l'année pour tous les agents actifs (RH responsable). Ne crédite jamais deux fois. */
    public function crediter(Request $request): RedirectResponse
    {
        $moi = $request->user()->agent;
        if (! $moi->aLeRole('rh_responsable')) {
            AuditService::refus('acces_refuse_crediter_droits', null, ['url' => $request->path()]);
            abort(403, 'Le crédit du droit annuel est réservé à la RH responsable.');
        }
        $annee = $this->annee($request->input('annee'));
        $r = SoldeService::crediterTous($annee);

        return redirect()->route('rh.soldes.index', ['annee' => $annee])->with('statut',
            "Droit annuel {$annee} : {$r['credites']} agent(s) crédité(s) pour ".DecompteService::libelle($r['jours']).". {$r['ignores']} ignoré(s) (déjà crédités, solde initial existant ou aucun mois acquis).");
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
    private function naturesPermises(Agent $moi): array
    {
        $natures = SoldeService::NATURES;
        if (! $moi->aLeRole('rh_responsable')) {
            foreach (SoldeService::NATURES_RH_RESPONSABLE as $n) {
                unset($natures[$n]);
            }
        }

        return $natures;
    }

    /** Prévient ceux qui peuvent contrôler (hors l'auteur et la personne concernée). */
    private function notifierControleurs(Agent $cible, Agent $auteur, int $propositionId): void
    {
        $notifs = app(NotificationService::class);
        $attendu = app(SoldePolicy::class)->controleurAttendu($cible);
        $codes = match ($attendu) {
            'rh' => ['rh_gestionnaire', 'rh_responsable'],
            'rh_responsable', 'autorite_externe' => ['rh_responsable'],
            'secretaire_general' => ['secretaire_general'],
            'chef_region' => ['chef_region'],
            default => null,
        };

        $destinataires = $codes
            ? $notifs->agentsAyantRole($codes)
            : Agent::actifs()->whereHas('structure', fn ($q) => $q->where('code', 'DAG'))->whereHas('fonction', fn ($q) => $q->where('libelle', 'Directeur'))->get();

        $notifs->notifierPlusieurs(
            $destinataires->reject(fn ($a) => in_array((int) $a->id, [(int) $auteur->id, (int) $cible->id], true)),
            'solde_a_controler', 'Mouvement de solde à contrôler',
            'Une proposition de mouvement concerne le solde de '.$cible->nom_complet.'.',
            ['lien' => route('rh.soldes.propositions')],
        );
    }
}
