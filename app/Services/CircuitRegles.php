<?php

namespace App\Services;

use App\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Règles de circuit validées avec l'encadreur (lot 1 bis). Aucune décision n'est prise ici :
 * ce sont des règles pures que le futur CircuitService (phase 3) appellera.
 *
 *  1. Un seul responsable donne l'avis : désigné par la RH dans la fiche (en principe le Directeur ;
 *     un chef de service seulement si la RH le désigne).
 *  2. L'autorité décisionnaire est désignée dans la fiche : « sg », « region » ou « sg_region »
 *     (les deux : le Secrétaire Général décide d'abord, puis le Chef de Région, dans cet ordre).
 *  3. Directeur demandeur : avis du SG, décision du Chef de Région (paramètres, à valider par l'administration).
 *  4. Mission hors Région : décision du Chef de Région, quelle que soit la fiche.
 *  5. Décision qui accepte malgré un avis défavorable : motif obligatoire (tout avis défavorable).
 */
class CircuitRegles
{
    /** Rôles dont le titulaire peut être désigné pour donner l'avis préalable. */
    public const ROLES_AVIS = ['responsable_hierarchique', 'secretaire_general', 'chef_region'];

    private const LIBELLES_AUTORITE = ['secretaire_general' => 'Secrétaire Général', 'chef_region' => 'Chef de Région'];

    /** Les trois circuits de décision possibles, tels qu'ils sont enregistrés et affichés. */
    public const CIRCUITS = [
        'sg' => 'Secrétaire Général',
        'region' => 'Chef de Région',
        'sg_region' => 'Les deux (Secrétaire Général et Chef de Région)',
    ];

    /** Étapes de décision, dans l'ordre, pour chaque circuit. Une étape refusée arrête la demande. */
    public const ETAPES = [
        'sg' => ['sg'],
        'region' => ['region'],
        'sg_region' => ['sg', 'region'],
    ];

    public static function libelle(?string $circuit): string
    {
        return self::CIRCUITS[$circuit] ?? '—';
    }

    /** Libellé court (tableaux) : « Secrétaire Général », « Chef de Région », « SG puis Chef de Région ». */
    public static function libelleCourt(?string $circuit): string
    {
        return $circuit === 'sg_region' ? 'SG puis Chef de Région' : self::libelle($circuit);
    }

    /** Parcours complet d'une demande de congé, lisible par la RH (aperçu dans les formulaires et fiches). */
    public static function parcours(?string $circuit): string
    {
        $decisions = array_map(fn ($e) => $e === 'sg' ? 'Décision du Secrétaire Général' : 'Décision du Chef de Région',
            self::ETAPES[$circuit] ?? []);

        return implode(' → ', ['Avis du responsable', 'Contrôle RH', ...$decisions, 'Enregistrement automatique']);
    }

    /**
     * Étapes de décision, dans l'ordre : ['sg'], ['region'] ou ['sg', 'region'].
     * C'est ce que le futur CircuitService (phase 3) transformera en étapes figées de la demande.
     */
    public static function etapesDecision(Agent $demandeur, ?string $famille = null, bool $horsRegion = false): array
    {
        return self::ETAPES[self::autoriteDecision($demandeur, $famille, $horsRegion)] ?? ['sg'];
    }

    /** Cette autorité (« sg » ou « region ») intervient-elle dans ce circuit ? */
    public static function intervient(?string $circuit, string $autorite): bool
    {
        return in_array($autorite, self::ETAPES[$circuit] ?? [], true);
    }

    // ------------------------------------------------------------------ autorité décisionnaire

    /** Traduit un paramètre (« chef_region », « secretaire_general », « les_deux ») en circuit « region » / « sg » / « sg_region ». */
    public static function autorite(string $cle, string $defaut): string
    {
        return match (Parametres::valeur($cle)) {
            'secretaire_general' => 'sg',
            'chef_region' => 'region',
            'les_deux' => 'sg_region',
            default => $defaut,
        };
    }

    /**
     * Circuit de décision : « sg », « region » ou « sg_region » (les deux, SG d'abord).
     * Part de la fiche de l'agent, puis applique les deux exceptions validées (Directeur ; mission hors Région).
     */
    public static function autoriteDecision(Agent $demandeur, ?string $famille = null, bool $horsRegion = false): string
    {
        $autorite = array_key_exists((string) $demandeur->rattachement, self::CIRCUITS) ? $demandeur->rattachement : 'sg';

        if ($demandeur->exists && self::estDirecteur((int) $demandeur->id)) {
            $autorite = self::autorite('directeur_decision_par', $autorite);
        }
        if ($famille === 'mission' && $horsRegion) {
            $autorite = self::autorite('mission_hors_region_decision_par', $autorite);
        }

        return $autorite;
    }

    /** Un « Directeur » = responsable hiérarchique d'une structure de type direction (la fonction n'est pas un droit). */
    public static function estDirecteur(int $agentId): bool
    {
        return self::actives()
            ->join('structures', 'structures.id', '=', 'habilitations.structure_id')
            ->where('habilitations.agent_id', $agentId)
            ->where('roles.code', 'responsable_hierarchique')
            ->where('structures.type', 'direction')
            ->exists();
    }

    // ------------------------------------------------------------------ responsable qui donne l'avis

    /** Cette personne peut-elle être désignée pour donner l'avis ? (agent actif avec un rôle de responsable en cours) */
    public static function peutDonnerAvis(int $agentId): bool
    {
        return self::actives()
            ->join('agents', 'agents.id', '=', 'habilitations.agent_id')
            ->where('habilitations.agent_id', $agentId)
            ->where('agents.situation', 'actif')
            ->whereIn('roles.code', self::ROLES_AVIS)
            ->exists();
    }

    /** Identifiants des agents qu'on peut désigner pour donner l'avis. */
    public static function detenteursAvis(): array
    {
        return self::actives()
            ->join('agents', 'agents.id', '=', 'habilitations.agent_id')
            ->where('agents.situation', 'actif')
            ->whereIn('roles.code', self::ROLES_AVIS)
            ->pluck('habilitations.agent_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /**
     * Responsable proposé pour chaque structure : le titulaire du rôle « responsable hiérarchique » de la
     * structure, sinon celui de la structure parente, et ainsi de suite. C'est une AIDE : la RH décide.
     *
     * @return array<int, array{id:int, nom:string}>  indexé par identifiant de structure
     */
    public static function suggestions(?int $exclureAgentId = null): array
    {
        $titulaires = self::actives()
            ->join('agents', 'agents.id', '=', 'habilitations.agent_id')
            ->where('roles.code', 'responsable_hierarchique')
            ->where('agents.situation', 'actif')
            ->whereNotNull('habilitations.structure_id')
            ->when($exclureAgentId, fn ($q) => $q->where('habilitations.agent_id', '!=', $exclureAgentId))
            ->orderBy('habilitations.id')
            ->get(['habilitations.structure_id', 'agents.id', 'agents.prenoms', 'agents.nom'])
            ->groupBy('structure_id')->map(fn ($g) => $g->first());

        $parents = DB::table('structures')->pluck('parent_id', 'id');
        $resultat = [];

        foreach ($parents as $id => $_) {
            $courant = $id;
            for ($garde = 0; $courant && $garde < 20; $garde++) {
                if ($t = $titulaires->get($courant)) {
                    $resultat[$id] = ['id' => (int) $t->id, 'nom' => trim($t->prenoms.' '.mb_strtoupper($t->nom))];
                    break;
                }
                $courant = $parents[$courant] ?? null;
            }
        }

        return $resultat;
    }

    /** Agents qui n'ont pas besoin d'un responsable pour l'avis : SG, Chef de Région, responsable du Cabinet. */
    public static function agentsSansAvisRequis(): array
    {
        return self::actives()
            ->leftJoin('structures', 'structures.id', '=', 'habilitations.structure_id')
            ->where(fn ($q) => $q->whereIn('roles.code', ['chef_region', 'secretaire_general'])
                ->orWhere(fn ($w) => $w->where('roles.code', 'responsable_hierarchique')->where('structures.type', 'cabinet')))
            ->pluck('habilitations.agent_id')->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    /** Agents actifs sans responsable pour l'avis (à corriger : leurs demandes n'auraient pas d'avis). */
    public static function agentsSansResponsable(): Collection
    {
        return Agent::actifs()->whereNull('responsable_id')
            ->whereNotIn('id', self::agentsSansAvisRequis())
            ->with('structure')->orderBy('nom')->get();
    }

    /** Agents actifs dont le responsable n'est plus actif ou n'a plus de rôle de responsable. */
    public static function agentsResponsableSansRole(): Collection
    {
        return Agent::actifs()->whereNotNull('responsable_id')
            ->whereNotIn('responsable_id', self::detenteursAvis())
            ->with(['structure', 'responsable'])->orderBy('nom')->get();
    }

    /** Points à vérifier sur la fiche d'un agent (messages lisibles, du plus important au moins important). */
    public static function incoherences(Agent $agent): array
    {
        $messages = [];
        $exempte = in_array((int) $agent->id, self::agentsSansAvisRequis(), true);

        if (! $agent->responsable_id) {
            if (! $exempte) {
                $messages[] = 'Aucun responsable ne donne l’avis : l’agent ne pourra pas obtenir l’avis préalable de ses demandes. Désignez-en un (pour un directeur : le Secrétaire Général).';
            }
        } else {
            $resp = DB::table('agents')->where('id', $agent->responsable_id)->first();
            if (! $resp || $resp->situation !== 'actif') {
                $messages[] = 'Le responsable désigné n’est plus en activité : désignez-en un autre.';
            } elseif (! self::peutDonnerAvis((int) $resp->id)) {
                $nom = trim($resp->prenoms.' '.mb_strtoupper($resp->nom));
                $messages[] = "{$nom} ne détient plus de rôle de responsable : il ne peut plus donner l’avis. Désignez-en un autre ou rétablissez son rôle.";
            }
        }

        if (self::estDirecteur((int) $agent->id)) {
            $roleAvis = Parametres::valeur('directeur_avis_par', 'secretaire_general');
            if ($agent->responsable_id && ! OrganisationService::aRoleActif((int) $agent->responsable_id, $roleAvis)) {
                $messages[] = 'Pour un directeur, l’avis est donné par le '.(self::LIBELLES_AUTORITE[$roleAvis] ?? $roleAvis).' (règle en attente de validation par l’administration).';
            }
            $attendue = self::autorite('directeur_decision_par', 'region');
            if ($agent->rattachement !== $attendue) {
                $messages[] = 'Pour un directeur, la décision revient à : '.self::libelle($attendue).'. Réglez l’autorité décisionnaire en conséquence.';
            }
        }

        return $messages;
    }

    // ------------------------------------------------------------------ avis défavorable et motif d'écart

    /**
     * Avis défavorables rendus sur une demande : l'avis préalable (avis_prealables) et les étapes d'avis du circuit.
     *
     * @return array<int, array{auteur_id:?int, motif:?string}>
     */
    public static function avisDefavorables(int $demandeId): array
    {
        $prealables = DB::table('demandes')
            ->join('avis_prealables as av', 'av.id', '=', 'demandes.avis_id')
            ->where('demandes.id', $demandeId)->where('av.sens_avis', 'defavorable')
            ->selectRaw('COALESCE(av.avis_donne_par, av.responsable_id) as auteur_id, av.motif_avis as motif')
            ->get();

        $etapes = DB::table('demande_etapes')
            ->where('demande_id', $demandeId)
            ->whereIn('role_etape', ['avis_responsable', 'avis_chef_projet'])
            ->where('resultat', 'defavorable')
            ->selectRaw('COALESCE(agent_effectif_id, acteur_id) as auteur_id, commentaire as motif')
            ->get();

        return $prealables->concat($etapes)
            ->map(fn ($l) => ['auteur_id' => $l->auteur_id ? (int) $l->auteur_id : null, 'motif' => $l->motif])
            ->all();
    }

    /** Faut-il un motif d'écart pour accepter cette demande ? */
    public static function motifEcartObligatoire(int $demandeId): bool
    {
        return Parametres::valeur('motif_obligatoire_si_avis_defavorable', '1') === '1'
            && self::avisDefavorables($demandeId) !== [];
    }

    /**
     * À appeler au moment de la décision : si on ACCEPTE malgré un avis défavorable, le motif est obligatoire.
     *
     * @throws ValidationException
     */
    public static function controlerMotifEcart(int $demandeId, string $resultat, ?string $motif): void
    {
        if ($resultat !== 'approuve' || ! self::motifEcartObligatoire($demandeId)) {
            return;
        }
        if (mb_strlen(trim((string) $motif)) < 10) {
            throw ValidationException::withMessages([
                'motif_ecart' => 'Un avis défavorable a été rendu : expliquez pourquoi vous acceptez malgré tout (au moins 10 caractères).',
            ]);
        }
    }

    // ------------------------------------------------------------------

    /** Habilitations en cours, avec le code du rôle. */
    private static function actives()
    {
        return DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->whereDate('habilitations.date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()));
    }
}
