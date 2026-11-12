<?php

namespace App\Services;

use App\Exceptions\SoldeException;
use App\Models\Agent;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Registre des soldes de congé (dossier, partie 8).
 *
 * PRINCIPE : on n'écrit jamais « le solde est de 17 ». Chaque changement est une LIGNE du registre
 * (table soldes_mouvements, écriture seule : un déclencheur interdit de la modifier ou de la supprimer).
 * Le solde est la somme des lignes ; une erreur se corrige par une nouvelle ligne.
 *
 *   solde disponible   = droits + report - jours réservés - jours consommés (somme de delta_disponible)
 *   solde prévisionnel = solde disponible - jours des demandes en attente de décision
 *
 * Depuis le lot 5 ter : un solde par LOT (année d'acquisition), consommé du plus ancien au plus récent (ImputationService),
 * et un droit acquis chaque mois (AcquisitionService).
 *
 * Deux familles de mouvements :
 *   - MANUELS (solde initial, correction, report, indemnisation, perte) : d'abord une PROPOSITION, contrôlée par une
 *     autre personne (proposer, puis controler). Personne n'écrit ni ne contrôle son propre solde.
 *   - AUTOMATIQUES (droit annuel, réservation, libération, consommation) : écrits par le système, sans auteur.
 */
class SoldeService
{
    /** Mouvements manuels : libellé. */
    public const NATURES = [
        'solde_initial' => 'Solde initial',
        'correction' => 'Correction',
        'report' => 'Report',
        'indemnisation' => 'Indemnisation',
        'perte' => 'Perte',
    ];

    /** Tous les types du registre, pour l'affichage. */
    public const TYPES = [
        'solde_initial' => 'Solde initial',
        'droit_annuel' => 'Droit annuel',
        'report' => 'Report',
        'reservation' => 'Réservation',
        'liberation' => 'Libération',
        'consommation' => 'Consommation',
        'correction' => 'Correction',
        'indemnisation' => 'Indemnisation',
        'perte' => 'Perte',
    ];

    /** Report, indemnisation et perte : décision de la RH responsable (règle officielle de fin d'année). */
    public const NATURES_RH_RESPONSABLE = ['report', 'indemnisation', 'perte'];

    /** Ces mouvements doivent citer le document officiel qui les justifie. */
    public const NATURES_AVEC_REFERENCE = ['solde_initial', 'report', 'correction'];

    /** Demandes dont les jours attendent encore une décision (comme la vue v_soldes_agents). */
    public const STATUTS_EN_ATTENTE = ['soumise', 'en_controle_rh', 'a_completer', 'en_attente_decision'];

    /** Demandes qui ne comptent pas dans le maximum annuel : jamais déposées, ou sans effet. [À confirmer avec la RH] */
    public const STATUTS_NON_COMPTES = ['brouillon', 'annulee', 'caduque', 'irrecevable', 'refusee'];

    // ======================================================================
    // Lecture
    // ======================================================================

    /**
     * Situation d'un agent pour une année.
     *
     * @return array{annee:int, acquis:float, report:float, ajustements:float, reserves:float, consommes:float,
     *               disponible:float, en_attente:float, previsionnel:float, a_des_mouvements:bool}
     */
    public static function situation(int $agentId, int $annee): array
    {
        $lignes = DB::table('soldes_mouvements')
            ->where('agent_id', $agentId)->where('annee', $annee)
            ->groupBy('type_mouvement')
            ->get(['type_mouvement', DB::raw('SUM(delta_disponible) AS d'), DB::raw('SUM(delta_reserve) AS r'), DB::raw('SUM(delta_consomme) AS c')]);

        $acquis = $report = $ajustements = $disponible = $reserves = $consommes = 0.0;
        foreach ($lignes as $l) {
            $disponible += (float) $l->d;
            $reserves += (float) $l->r;
            $consommes += (float) $l->c;
            match ($l->type_mouvement) {
                'solde_initial', 'droit_annuel' => $acquis += (float) $l->d,
                'report' => $report += (float) $l->d,
                'correction', 'indemnisation', 'perte' => $ajustements += (float) $l->d,
                default => null,
            };
        }

        $enAttente = (float) DB::table('demandes as d')
            ->join('types_demande as t', 't.id', '=', 'd.type_demande_id')
            ->where('d.agent_id', $agentId)
            ->whereYear('d.date_debut', $annee)
            ->whereIn('d.statut', self::STATUTS_EN_ATTENTE)
            ->where('t.decompte_solde', 'oui')
            ->sum('d.duree_jours');

        return [
            'annee' => $annee,
            'acquis' => round($acquis, 2), 'report' => round($report, 2), 'ajustements' => round($ajustements, 2),
            'reserves' => round($reserves, 2), 'consommes' => round($consommes, 2),
            'disponible' => round($disponible, 2),
            'en_attente' => round($enAttente, 2),
            'previsionnel' => round($disponible - $enAttente, 2),
            'a_des_mouvements' => $lignes->isNotEmpty(),
        ];
    }

    /**
     * Registre d'une année, avec le solde disponible après chaque ligne (comme un carnet de banque).
     *
     * @return array<int, array{id:int, date:string, type:string, libelle:string, jours:float, reserve:float, consomme:float,
     *                          solde_apres:float, motif:?string, demande:?string, auteur:?string}>
     */
    public static function registre(int $agentId, int $annee): array
    {
        $lignes = DB::table('soldes_mouvements as m')
            ->leftJoin('demandes as d', 'd.id', '=', 'm.demande_id')
            ->leftJoin('agents as a', 'a.id', '=', 'm.auteur_id')
            ->where('m.agent_id', $agentId)->where('m.annee', $annee)
            ->orderBy('m.created_at')->orderBy('m.id')
            ->get(['m.id', 'm.created_at', 'm.periode', 'm.type_mouvement', 'm.delta_disponible', 'm.delta_reserve', 'm.delta_consomme',
                'm.motif', 'd.reference as demande', 'a.nom as auteur_nom', 'a.prenoms as auteur_prenoms']);

        $solde = 0.0;
        $sortie = [];
        foreach ($lignes as $l) {
            $solde += (float) $l->delta_disponible;
            $sortie[] = [
                'id' => (int) $l->id,
                'date' => substr((string) $l->created_at, 0, 10),
                'type' => $l->type_mouvement,
                'libelle' => ($l->type_mouvement === 'droit_annuel' && $l->periode) ? 'Acquisition mensuelle' : (self::TYPES[$l->type_mouvement] ?? $l->type_mouvement),
                'periode' => $l->periode,
                'jours' => (float) $l->delta_disponible,
                'reserve' => (float) $l->delta_reserve,
                'consomme' => (float) $l->delta_consomme,
                'solde_apres' => round($solde, 2),
                'motif' => $l->motif,
                'demande' => $l->demande,
                'auteur' => $l->auteur_nom ? trim($l->auteur_prenoms.' '.mb_strtoupper($l->auteur_nom)) : null,
            ];
        }

        return $sortie;
    }

    /** Années à proposer dans un sélecteur : celles qui ont des mouvements, l'année en cours et la suivante. */
    public static function annees(int $agentId): array
    {
        $annees = DB::table('soldes_mouvements')->where('agent_id', $agentId)->distinct()->pluck('annee')->map(fn ($a) => (int) $a)->all();
        $annees[] = (int) date('Y');
        $annees = array_values(array_unique($annees));
        rsort($annees);

        return $annees;
    }

    /** Propositions d'un agent (ou toutes) en attente de contrôle. */
    public static function propositionsEnAttente(?int $agentId = null): \Illuminate\Support\Collection
    {
        return DB::table('soldes_propositions as p')
            ->join('agents as c', 'c.id', '=', 'p.agent_id')
            ->join('agents as a', 'a.id', '=', 'p.propose_par')
            ->where('p.statut', 'en_attente')
            ->when($agentId, fn ($q) => $q->where('p.agent_id', $agentId))
            ->orderBy('p.created_at')
            ->get(['p.*', 'c.nom as cible_nom', 'c.prenoms as cible_prenoms', 'c.identifiant as cible_identifiant',
                'a.nom as auteur_nom', 'a.prenoms as auteur_prenoms']);
    }

    // ======================================================================
    // Mouvements manuels : proposition puis contre-validation
    // ======================================================================

    /**
     * Enregistre une PROPOSITION de mouvement. Le registre n'est pas modifié tant qu'une autre personne ne l'a pas validée.
     * Les droits (qui peut proposer pour qui) sont vérifiés par le contrôleur avec SoldePolicy ; ici on contrôle le fond.
     *
     * Signe des jours : saisir un nombre POSITIF pour solde initial, report, indemnisation et perte (le sens est celui du
     * mouvement : l'indemnisation et la perte retirent des jours). Pour une correction, saisir +3 ou -3.
     *
     * @throws SoldeException
     */
    public static function proposer(Agent $auteur, Agent $cible, int $annee, string $nature, float $jours, string $motif, ?string $reference = null): int
    {
        if (! isset(self::NATURES[$nature])) {
            throw new SoldeException('Nature de mouvement inconnue.');
        }
        if ((int) $auteur->id === (int) $cible->id) {
            throw new SoldeException('Personne ne propose de mouvement sur son propre solde.');
        }
        if ($cible->situation !== 'actif') {
            throw new SoldeException('Cet agent n’est plus en activité : son solde ne peut plus être modifié.');
        }
        if ($annee < 2000 || $annee > (int) date('Y') + 1) {
            throw new SoldeException('Année invalide.');
        }

        $motif = trim(preg_replace('/\s+/u', ' ', $motif));
        $reference = $reference !== null ? trim($reference) : null;
        if (mb_strlen($motif) < 5) {
            throw new SoldeException('Expliquez le motif du mouvement (au moins quelques mots).');
        }
        if (in_array($nature, self::NATURES_AVEC_REFERENCE, true) && ($reference === null || $reference === '')) {
            throw new SoldeException('Indiquez le document officiel qui justifie ce mouvement (décision, registre des congés...).');
        }

        if ($jours == 0.0 || fmod(abs($jours) * 2, 1.0) > 1e-9) {
            throw new SoldeException('Le nombre de jours doit être non nul et par pas d’une demi-journée (0,5).');
        }
        if (in_array($nature, ['solde_initial', 'report'], true) && $jours < 0) {
            throw new SoldeException('Un '.mb_strtolower(self::NATURES[$nature]).' est un nombre positif de jours.');
        }
        if (in_array($nature, ['indemnisation', 'perte'], true)) {
            $jours = -abs($jours);
        }

        return DB::transaction(function () use ($auteur, $cible, $annee, $nature, $jours, $motif, $reference) {
            DB::table('agents')->where('id', $cible->id)->lockForUpdate()->first();  // une proposition à la fois par agent

            if ($nature === 'solde_initial') {
                $existe = DB::table('soldes_mouvements')->where('agent_id', $cible->id)->where('annee', $annee)->where('type_mouvement', 'solde_initial')->exists()
                    || DB::table('soldes_propositions')->where('agent_id', $cible->id)->where('annee', $annee)->where('nature', 'solde_initial')->where('statut', 'en_attente')->exists();
                if ($existe) {
                    throw new SoldeException("Un solde initial existe déjà pour {$annee} (ou attend son contrôle) : on le corrige par un mouvement de correction.");
                }
                if (DB::table('soldes_mouvements')->where('agent_id', $cible->id)->where('annee', $annee)->where('type_mouvement', 'droit_annuel')->exists()) {
                    throw new SoldeException("Le droit annuel {$annee} est déjà crédité : un solde initial le compterait deux fois. Utilisez une correction.");
                }
            }

            if ($jours < 0 && $nature !== 'correction') {
                $disponible = self::situation((int) $cible->id, $annee)['disponible'];
                if ($disponible + $jours < 0) {
                    throw new SoldeException('Le solde disponible ('.DecompteService::libelle($disponible).") ne permet pas de retirer ".DecompteService::libelle(abs($jours)).'.');
                }
            }

            if (DB::table('soldes_propositions')->where('agent_id', $cible->id)->where('annee', $annee)->where('nature', $nature)
                ->where('jours', $jours)->where('statut', 'en_attente')->exists()) {
                throw new SoldeException('Une proposition identique attend déjà son contrôle.');
            }

            $id = DB::table('soldes_propositions')->insertGetId([
                'agent_id' => $cible->id, 'annee' => $annee, 'nature' => $nature, 'jours' => $jours,
                'motif' => $motif, 'reference_piece' => $reference, 'propose_par' => $auteur->id, 'statut' => 'en_attente',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            AuditService::enregistrer('solde_propose', null, null, [
                'proposition_id' => $id, 'agent_id' => (int) $cible->id, 'annee' => $annee, 'nature' => $nature, 'jours' => $jours,
            ]);

            return $id;
        });
    }

    /** Qui doit contre-valider, et à quel niveau, en plus de SoldePolicy : la correction est contre-validée par la RH responsable. */
    public static function niveauSupplementaire(string $nature, string $controleurAttendu): ?string
    {
        return ($nature === 'correction' && $controleurAttendu === 'rh') ? 'rh_responsable' : null;
    }

    /**
     * Valide ou rejette une proposition. À appeler APRÈS l'autorisation (SoldePolicy::controler) ; les séparations
     * essentielles sont revérifiées ici et par la base (contraintes de la table).
     *
     * @return array{statut:string, mouvement_id:?int}
     * @throws SoldeException
     */
    public static function controler(Agent $controleur, int $propositionId, bool $valide, ?string $commentaire = null): array
    {
        $commentaire = $commentaire !== null ? trim($commentaire) : null;
        if (! $valide && ($commentaire === null || mb_strlen($commentaire) < 3)) {
            throw new SoldeException('Indiquez pourquoi la proposition est rejetée.');
        }

        return DB::transaction(function () use ($controleur, $propositionId, $valide, $commentaire) {
            $p = DB::table('soldes_propositions')->where('id', $propositionId)->lockForUpdate()->first();
            if (! $p) {
                throw new SoldeException('Proposition introuvable.');
            }
            if ($p->statut !== 'en_attente') {
                throw new SoldeException('Cette proposition a déjà été contrôlée.');
            }
            if ((int) $p->propose_par === (int) $controleur->id || (int) $p->agent_id === (int) $controleur->id) {
                throw new SoldeException('Ni l’auteur de la proposition, ni la personne concernée ne peuvent la contrôler.');
            }

            $mouvementId = null;
            if ($valide) {
                $jours = (float) $p->jours;
                if ($jours < 0 && $p->nature !== 'correction') {
                    $disponible = self::situation((int) $p->agent_id, (int) $p->annee)['disponible'];
                    if ($disponible + $jours < 0) {
                        throw new SoldeException('Le solde disponible a changé depuis la proposition : il ne permet plus ce retrait ('.DecompteService::libelle($disponible).').');
                    }
                }
                if ($p->nature === 'solde_initial' && DB::table('soldes_mouvements')->where('agent_id', $p->agent_id)->where('annee', $p->annee)
                    ->whereIn('type_mouvement', ['solde_initial', 'droit_annuel'])->exists()) {
                    throw new SoldeException('Un solde initial ou le droit annuel existe déjà pour cette année : utilisez une correction.');
                }

                $mouvementId = DB::table('soldes_mouvements')->insertGetId([
                    'agent_id' => $p->agent_id, 'annee' => $p->annee, 'type_mouvement' => $p->nature,
                    'delta_disponible' => $jours, 'delta_reserve' => 0, 'delta_consomme' => 0,
                    'proposition_id' => $p->id,
                    'motif' => mb_substr($p->motif.($p->reference_piece ? ' — Réf. '.$p->reference_piece : ''), 0, 255),
                    'auteur_id' => $p->propose_par, 'created_at' => now(),
                ]);
            }

            DB::table('soldes_propositions')->where('id', $p->id)->update([
                'statut' => $valide ? 'valide' : 'rejete', 'controle_par' => $controleur->id,
                'controle_le' => now(), 'commentaire_controle' => $commentaire, 'updated_at' => now(),
            ]);

            AuditService::enregistrer($valide ? 'solde_valide' : 'solde_rejete', null,
                ['proposition_id' => (int) $p->id, 'statut' => 'en_attente'],
                ['proposition_id' => (int) $p->id, 'statut' => $valide ? 'valide' : 'rejete', 'mouvement_id' => $mouvementId, 'jours' => (float) $p->jours]);

            // Les corrections importantes sont signalées à la RH (paramètre alerte_correction_solde_jours).
            if ($valide && $p->nature === 'correction' && abs((float) $p->jours) > Parametres::decimal('alerte_correction_solde_jours', 5)) {
                app(NotificationService::class)->notifierRh('solde_correction_importante', 'Correction de solde importante',
                    'Correction de '.DecompteService::libelle((float) $p->jours).' validée sur le solde '.$p->annee.' de l’agent n° '.$p->agent_id.'.');
            }

            return ['statut' => $valide ? 'valide' : 'rejete', 'mouvement_id' => $mouvementId];
        });
    }

    // ======================================================================
    // Droit annuel EN BLOC (ancien mode du lot 5)
    // ======================================================================
    // Depuis le lot 5 ter, le droit s'acquiert mois par mois (AcquisitionService, 2,5 jours par mois). Les méthodes
    // ci-dessous sont conservées pour les années déjà créditées en bloc et pour le contrôle lot5:verifier ; l'écran RH
    // et la commande conges:acquisition n'y font plus appel.

    /** Mois entiers travaillés dans l'année : droit annuel / 12 par mois (30 j par an = 2,5 j par mois). */
    public static function moisTravailles(\DateTimeInterface|string $entree, \DateTimeInterface|string|null $sortie, int $annee): int
    {
        $entree = DecompteService::date($entree);
        $sortie = $sortie ? DecompteService::date($sortie) : null;
        $mois = 0;

        for ($m = 1; $m <= 12; $m++) {
            $premier = new DateTimeImmutable(sprintf('%04d-%02d-01', $annee, $m));
            $dernier = $premier->modify('last day of this month');
            if ($entree <= $premier && ($sortie === null || $sortie >= $dernier)) {
                $mois++;
            }
        }

        return $mois;
    }

    /** Droit acquis pour l'année, arrondi à la demi-journée. */
    public static function droitAnnee(\DateTimeInterface|string $entree, \DateTimeInterface|string|null $sortie, int $annee, ?float $droitAnnuel = null): float
    {
        $droitAnnuel ??= Parametres::decimal('droit_annuel_jours', 30);

        return round($droitAnnuel / 12 * self::moisTravailles($entree, $sortie, $annee) * 2) / 2;
    }

    /**
     * Crédite le droit annuel d'un agent. Sans effet (null) si le droit est déjà crédité, si un solde initial existe
     * pour cette année (il le remplace) ou si l'agent n'a acquis aucun mois.
     */
    public static function crediterDroitAnnuel(int $agentId, int $annee): ?float
    {
        return DB::transaction(function () use ($agentId, $annee) {
            $agent = DB::table('agents')->where('id', $agentId)->lockForUpdate()->first();
            if (! $agent || $agent->situation !== 'actif') {
                return null;
            }
            $deja = DB::table('soldes_mouvements')->where('agent_id', $agentId)->where('annee', $annee)
                ->whereIn('type_mouvement', ['droit_annuel', 'solde_initial'])->exists();
            if ($deja) {
                return null;
            }

            $mois = self::moisTravailles($agent->date_entree, $agent->date_sortie, $annee);
            $jours = self::droitAnnee($agent->date_entree, $agent->date_sortie, $annee);
            if ($jours <= 0) {
                return null;
            }

            DB::table('soldes_mouvements')->insert([
                'agent_id' => $agentId, 'annee' => $annee, 'type_mouvement' => 'droit_annuel',
                'delta_disponible' => $jours, 'delta_reserve' => 0, 'delta_consomme' => 0,
                'motif' => "Droit annuel {$annee} : {$mois} mois travaillés", 'auteur_id' => null, 'created_at' => now(),
            ]);

            return $jours;
        });
    }

    /** @return array{credites:int, ignores:int, jours:float} */
    public static function crediterTous(int $annee): array
    {
        $credites = 0;
        $ignores = 0;
        $jours = 0.0;
        foreach (DB::table('agents')->where('situation', 'actif')->pluck('id') as $id) {
            $c = self::crediterDroitAnnuel((int) $id, $annee);
            if ($c === null) {
                $ignores++;
            } else {
                $credites++;
                $jours += $c;
            }
        }
        AuditService::enregistrer('droits_annuels_credites', null, null, ['annee' => $annee, 'agents' => $credites, 'jours' => $jours]);

        return ['credites' => $credites, 'ignores' => $ignores, 'jours' => $jours];
    }

    // ======================================================================
    // Mouvements automatiques liés aux demandes (utilisés par le circuit, lots suivants)
    // ======================================================================

    /**
     * Réserve les jours d'une demande validée : disponible -, réservé +, lot par lot (le plus ancien d'abord, lot 5 ter).
     * Sans effet si le type de demande ne décompte pas le solde ("non") ou si la RH n'a pas décidé d'imputer
     * ("sur_decision" : passer $imputer = true). Les jours qui ne seront acquis que plus tard sont réservés au crédit mensuel.
     *
     * @return bool vrai si des jours ont été réservés
     * @throws SoldeException solde insuffisant (sauf $forcer), demande déjà réservée, durée incohérente
     */
    public static function reserver(int $demandeId, ?bool $imputer = null, bool $forcer = false): bool
    {
        return DB::transaction(function () use ($demandeId, $imputer, $forcer) {
            [$d, $type] = self::demande($demandeId);
            if ($type->decompte_solde === 'non' || ($type->decompte_solde === 'sur_decision' && $imputer !== true)) {
                return false;
            }
            if (DB::table('soldes_mouvements')->where('demande_id', $demandeId)->where('type_mouvement', 'reservation')->exists()
                || DB::table('demande_imputations')->where('demande_id', $demandeId)->exists()) {
                throw new SoldeException('Les jours de cette demande sont déjà réservés.');
            }

            $calcul = DecompteService::compter($d->date_debut, $d->date_fin, $d->demi_journee_debut ?? 'journee', $d->demi_journee_fin ?? 'journee');
            if (abs($calcul['total'] - (float) $d->duree_jours) > 0.01) {
                throw new SoldeException('La durée enregistrée ('.DecompteService::libelle((float) $d->duree_jours).') ne correspond pas au décompte ('
                    .DecompteService::libelle($calcul['total']).') : recalculez la demande.');
            }

            // Lot 5 ter : chaque jour est débité du plus ancien lot ; une ligne de registre par lot touché.
            ImputationService::reserverDemande($d, $calcul, $forcer);

            return true;
        });
    }

    /** Libère les jours encore réservés (annulation approuvée, demande caduque) : disponible +, réservé -. */
    public static function liberer(int $demandeId, string $motif = 'Libération'): float
    {
        return self::solderReserve($demandeId, 'liberation', $motif);
    }

    /** Fin du congé : les jours réservés deviennent consommés (réservé -, consommé +). */
    public static function consommer(int $demandeId): float
    {
        return self::solderReserve($demandeId, 'consommation', 'Consommation : congé pris');
    }

    /**
     * Contrôle à la soumission (partie 8.8) : durée, solde prévisionnel PAR LOT, plafond par demande, maximum de demandes par an.
     * Ne modifie rien. Un solde insuffisant bloque (paramètre solde_insuffisant_mode : bloquer ou alerter) ; un dépassement
     * de plafond ou de nombre passe en dérogation. $ignorerDemandeId : la demande elle-même, quand on la recontrôle à la décision.
     *
     * @return array{duree:float, par_annee:array<int,float>, decompte_solde:string, suffisant:bool, bloquant:bool, simulation:?array, previsionnel:?float,
     *               derogation:bool, raisons:array<int,string>, avertissements:array<int,string>}
     */
    public static function controlerSoumission(int $agentId, string $typeCode, string $debut, string $fin, string $demiDebut = 'journee', string $demiFin = 'journee', ?int $ignorerDemandeId = null): array
    {
        $type = DB::table('types_demande')->where('code', $typeCode)->first() ?? throw new SoldeException('Type de demande inconnu.');
        $calcul = DecompteService::compter($debut, $fin, $demiDebut, $demiFin);

        $suffisant = true;
        $previsionnel = null;
        $raisons = [];
        $bloquant = false;
        $simulation = null;
        if ($type->decompte_solde === 'oui') {
            // Lot 5 ter : débit par lot (le plus ancien d'abord), sur les jours acquis à la date de chaque jour.
            $simulation = ImputationService::simuler($agentId, $calcul, $ignorerDemandeId);
            $suffisant = $simulation['suffisant'];
            $bloquant = $simulation['bloquant'];
            $previsionnel = $simulation['total_avant'];
            foreach ($simulation['messages'] as $m) {
                $raisons[] = $m;
            }
        }

        $derogation = false;
        if ($typeCode === 'conge_annuel') {
            $plafond = Parametres::entier('plafond_jours_par_demande', 20);
            if ($calcul['total'] > $plafond) {
                $derogation = true;
                $raisons[] = "Plus de {$plafond} jours ouvrés : dérogation avec avis de conformité de la RH.";
            }
            $max = Parametres::entier('max_demandes_conge_annuel', 2);
            $annee = (int) substr($debut, 0, 4);
            $deja = DB::table('demandes')->where('agent_id', $agentId)->where('type_demande_id', $type->id)
                ->whereYear('date_debut', $annee)->whereNotIn('statut', self::STATUTS_NON_COMPTES)->count();
            if ($deja >= $max) {
                $derogation = true;
                $raisons[] = "Déjà {$deja} demande(s) de congé annuel en {$annee} (maximum {$max}) : dérogation avec avis de conformité de la RH.";
            }
        }

        return [
            'duree' => $calcul['total'], 'par_annee' => $calcul['par_annee'], 'decompte_solde' => $type->decompte_solde,
            'suffisant' => $suffisant, 'bloquant' => $bloquant, 'previsionnel' => $previsionnel, 'derogation' => $derogation,
            'simulation' => $simulation, 'raisons' => $raisons, 'avertissements' => $calcul['avertissements'],
        ];
    }

    // ----------------------------------------------------------------------

    /** @return array{0: object, 1: object} la demande (verrouillée) et son type */
    private static function demande(int $demandeId): array
    {
        $d = DB::table('demandes')->where('id', $demandeId)->lockForUpdate()->first() ?? throw new SoldeException('Demande introuvable.');
        $type = DB::table('types_demande')->where('id', $d->type_demande_id)->first();

        return [$d, $type];
    }

    private static function solderReserve(int $demandeId, string $type, string $motif): float
    {
        return DB::transaction(function () use ($demandeId, $type, $motif) {
            [$d] = self::demande($demandeId);
            $total = 0.0;

            // Jours « différés » (pas encore acquis) : une libération les annule ; on ne peut pas consommer ce qui n'est pas réservé.
            $differees = DB::table('demande_imputations')->where('demande_id', $demandeId)->where('differee', 1);
            if ($type === 'consommation' && (clone $differees)->exists()) {
                throw new SoldeException('Une partie des jours de la demande '.$d->reference.' n’est pas encore acquise : elle ne peut pas être consommée.');
            }
            if ($type === 'liberation') {
                $differees->delete();
            }

            $restes = DB::table('soldes_mouvements')->where('demande_id', $demandeId)->groupBy('annee')
                ->get(['annee', DB::raw('SUM(delta_reserve) AS reste')]);
            foreach ($restes as $r) {
                $reste = round((float) $r->reste, 2);
                if ($reste <= 0) {
                    continue;
                }
                DB::table('soldes_mouvements')->insert([
                    'agent_id' => $d->agent_id, 'annee' => $r->annee, 'type_mouvement' => $type,
                    'delta_disponible' => $type === 'liberation' ? $reste : 0,
                    'delta_reserve' => -$reste,
                    'delta_consomme' => $type === 'consommation' ? $reste : 0,
                    'demande_id' => $demandeId, 'motif' => "{$motif} : demande {$d->reference}", 'auteur_id' => null, 'created_at' => now(),
                ]);
                $total += $reste;
            }

            return $total;
        });
    }
}
