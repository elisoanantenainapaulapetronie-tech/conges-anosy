<?php

namespace App\Services;

use App\Exceptions\SoldeException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lots de congé et imputation des jours (lot 5 ter).
 *
 * UN LOT = les jours acquis pendant une année (le « millésime »). Chaque lot a son propre compte dans le registre
 * (colonne soldes_mouvements.annee). Règles :
 *
 *  1. CHAQUE JOUR décompté est débité du plus ANCIEN lot qui a encore des jours (règlement : les jours les plus
 *     anciens d'abord) ;
 *  2. un lot ne couvre qu'un jour de son année ou d'une année suivante : le droit 2027 ne couvre jamais un jour de 2026 ;
 *  3. un jour pris est couvert par les jours ACQUIS À LA DATE DE CE JOUR : les mois à venir comptent quand le jour
 *     tombe dans ce mois (acquisition du 1er du mois), jamais avant (sauf paramètre anticipation_autorisee) ;
 *  4. aucun lot ne devient négatif : ce qui n'est pas couvert est un « manque », jamais accepté automatiquement ;
 *  5. la répartition par année civile (affichée) et le débit par lot (écrit au registre) sont deux choses distinctes.
 *
 * repartir() est PURE : elle ne touche pas la base et se teste sans Laravel.
 */
class ImputationService
{
    /** Jours acquis d'un lot au plus tard au mois $mois (AAAA-MM) : somme des crédits à venir dont la période est atteinte. */
    public static function projeteJusqua(array $parPeriode, string $mois): float
    {
        $somme = 0.0;
        foreach ($parPeriode as $periode => $jours) {
            if ((string) $periode <= $mois) {
                $somme += (float) $jours;
            }
        }

        return round($somme, 2);
    }

    /**
     * Débite les jours des lots, du plus ancien au plus récent.
     *
     * @param array<int, array{date:string, valeur:float}> $jours      jours décomptés, dans l'ordre des dates
     * @param array<int, float>                           $lots       année du lot => jours disponibles aujourd'hui (registre)
     * @param array<int, array<string, float>>            $projections année du lot => [période AAAA-MM => jours à acquérir]
     * @param array<int, float>                           $dejaUtilise année du lot => jours déjà pris par d'autres demandes en attente
     *
     * @return array{
     *   lots: array<int, array{total:float, reel:float, provisoire:float, debut_reel:?string, fin_reel:?string, debut_prov:?string, fin_prov:?string}>,
     *   debit_total: float, reel: float, provisoire: float, manque: float,
     *   jours_manque: array<int, array{date:string, valeur:float}>, premier_manque: ?string, couvert_jusqu: ?string
     * }
     */
    public static function repartir(array $jours, array $lots, array $projections = [], array $dejaUtilise = []): array
    {
        $annees = array_map('intval', array_unique(array_merge(array_keys($lots), array_keys($projections))));
        sort($annees);

        $reelLot = [];
        $cumul = [];
        foreach ($annees as $a) {
            $reelLot[$a] = (float) ($lots[$a] ?? 0);
            $cumul[$a] = (float) ($dejaUtilise[$a] ?? 0);
        }

        $sortie = [];
        $manque = 0.0;
        $joursManque = [];
        $premierManque = null;
        $dernierCouvert = null;

        foreach ($jours as $j) {
            $valeur = round((float) $j['valeur'], 2);
            if ($valeur <= 0) {
                continue;
            }
            $date = (string) $j['date'];
            $mois = substr($date, 0, 7);
            $anneeJour = (int) substr($date, 0, 4);
            $reste = $valeur;

            foreach ($annees as $a) {
                if ($reste <= 0.004 || $a > $anneeJour) {
                    break;
                }
                $capacite = round($reelLot[$a] + self::projeteJusqua($projections[$a] ?? [], $mois) - $cumul[$a], 2);
                if ($capacite <= 0.004) {
                    continue;
                }
                $pris = round(min($reste, $capacite), 2);
                $reelRestant = max(0.0, $reelLot[$a] - $cumul[$a]);
                $partReel = round(min($pris, $reelRestant), 2);
                $partProv = round($pris - $partReel, 2);

                $sortie[$a] ??= ['total' => 0.0, 'reel' => 0.0, 'provisoire' => 0.0, 'debut_reel' => null, 'fin_reel' => null, 'debut_prov' => null, 'fin_prov' => null];
                $sortie[$a]['total'] = round($sortie[$a]['total'] + $pris, 2);
                if ($partReel > 0) {
                    $sortie[$a]['reel'] = round($sortie[$a]['reel'] + $partReel, 2);
                    $sortie[$a]['debut_reel'] ??= $date;
                    $sortie[$a]['fin_reel'] = $date;
                }
                if ($partProv > 0) {
                    $sortie[$a]['provisoire'] = round($sortie[$a]['provisoire'] + $partProv, 2);
                    $sortie[$a]['debut_prov'] ??= $date;
                    $sortie[$a]['fin_prov'] = $date;
                }

                $cumul[$a] = round($cumul[$a] + $pris, 2);
                $reste = round($reste - $pris, 2);
            }

            if ($reste > 0.004) {
                $manque = round($manque + $reste, 2);
                $joursManque[] = ['date' => $date, 'valeur' => $reste];
                $premierManque ??= $date;
            } elseif ($premierManque === null) {
                $dernierCouvert = $date;
            }
        }

        ksort($sortie);

        return [
            'lots' => $sortie,
            'debit_total' => round(array_sum(array_column($sortie, 'total')), 2),
            'reel' => round(array_sum(array_column($sortie, 'reel')), 2),
            'provisoire' => round(array_sum(array_column($sortie, 'provisoire')), 2),
            'manque' => $manque,
            'jours_manque' => $joursManque,
            'premier_manque' => $premierManque,
            'couvert_jusqu' => $premierManque === null ? null : $dernierCouvert,
        ];
    }

    // ======================================================================
    // Lecture
    // ======================================================================

    /** Jours disponibles par lot (registre) : année => jours. */
    public static function lots(int $agentId): array
    {
        $lots = [];
        foreach (DB::table('soldes_mouvements')->where('agent_id', $agentId)->groupBy('annee')->orderBy('annee')
            ->get(['annee', DB::raw('SUM(delta_disponible) AS d')]) as $l) {
            $lots[(int) $l->annee] = round((float) $l->d, 2);
        }

        return $lots;
    }

    /** Jours décomptés d'un calcul (DecompteService), sans les jours à 0. */
    public static function joursDecomptes(array $calcul): array
    {
        $jours = [];
        foreach ($calcul['lignes'] as $l) {
            if ((float) $l['valeur'] > 0) {
                $jours[] = ['date' => $l['date'], 'valeur' => (float) $l['valeur']];
            }
        }

        return $jours;
    }

    /** Projections à utiliser pour ces jours (avec le paramètre anticipation_autorisee). */
    public static function projectionsPour(int $agentId, array $jours, ?int $anneeMax = null): array
    {
        $fin = $jours ? (int) substr((string) end($jours)['date'], 0, 4) : (int) date('Y');
        $projections = AcquisitionService::projections($agentId, max($fin, $anneeMax ?? 0));

        if (Parametres::entier('anticipation_autorisee', 0) === 1) {
            foreach ($projections as $annee => $periodes) {
                $projections[$annee] = [sprintf('%04d-01', $annee) => round(array_sum($periodes), 2)];
            }
        }

        return $projections;
    }

    /**
     * Ce que les demandes en attente de décision (déjà déposées, pas encore réservées) prendront sur chaque lot.
     * Elles passent dans l'ordre du dépôt, chacune avec la règle « plus ancien d'abord ».
     *
     * @return array{0: array<int,float>, 1: int} jours déjà engagés par lot, dernière année concernée
     */
    private static function enAttente(int $agentId, ?int $ignorerDemandeId, array $lots, \Closure $projectionsDe): array
    {
        $demandes = DB::table('demandes as d')->join('types_demande as t', 't.id', '=', 'd.type_demande_id')
            ->where('d.agent_id', $agentId)->whereIn('d.statut', SoldeService::STATUTS_EN_ATTENTE)->where('t.decompte_solde', 'oui')
            ->when($ignorerDemandeId, fn ($q) => $q->where('d.id', '!=', $ignorerDemandeId))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('soldes_mouvements as m')
                ->whereColumn('m.demande_id', 'd.id')->where('m.type_mouvement', 'reservation'))
            ->orderBy('d.id')->get(['d.id', 'd.date_debut', 'd.date_fin', 'd.duree_jours', 'd.demi_journee_debut', 'd.demi_journee_fin']);

        $deja = [];
        $derniere = 0;
        foreach ($demandes as $dm) {
            try {
                $calcul = DecompteService::compter($dm->date_debut, $dm->date_fin, $dm->demi_journee_debut ?? 'journee', $dm->demi_journee_fin ?? 'journee');
                $jours = self::joursDecomptes($calcul);
            } catch (InvalidArgumentException) {
                $jours = [['date' => (string) $dm->date_debut, 'valeur' => (float) $dm->duree_jours]];
            }
            $derniere = max($derniere, (int) substr((string) $dm->date_fin, 0, 4));
            $r = self::repartir($jours, $lots, $projectionsDe($derniere), $deja);
            foreach ($r['lots'] as $annee => $l) {
                $deja[$annee] = round(($deja[$annee] ?? 0) + $l['total'], 2);
            }
        }

        return [$deja, $derniere];
    }

    /**
     * Simulation pour l'agent : débit par lot, manque, date atteignable. Ne modifie rien.
     *
     * @return array{suffisant:bool, manque:float, couvert_jusqu:?string, premier_manque:?string, provisoire:float, bloquant:bool,
     *               lots: array<int, array{annee:int, avant:float, debit_reel:float, debit_provisoire:float, apres:float}>,
     *               total_avant: float, messages: array<int,string>, issues: array<int,string>}
     */
    public static function simuler(int $agentId, array $calcul, ?int $ignorerDemandeId = null): array
    {
        $jours = self::joursDecomptes($calcul);
        $lots = self::lots($agentId);
        $projectionsDe = fn (int $anneeMax) => self::projectionsPour($agentId, $jours, $anneeMax);

        [$deja, $derniere] = self::enAttente($agentId, $ignorerDemandeId, $lots, $projectionsDe);
        $r = self::repartir($jours, $lots, $projectionsDe($derniere), $deja);

        $annees = array_values(array_unique(array_merge(array_keys($lots), array_keys($r['lots']))));
        sort($annees);
        $lignes = [];
        $totalAvant = 0.0;
        foreach ($annees as $a) {
            $avant = round(($lots[$a] ?? 0) - ($deja[$a] ?? 0), 2);
            $reel = $r['lots'][$a]['reel'] ?? 0.0;
            $prov = $r['lots'][$a]['provisoire'] ?? 0.0;
            $lignes[] = ['annee' => $a, 'avant' => $avant, 'debit_reel' => $reel, 'debit_provisoire' => $prov, 'apres' => round($avant - $reel, 2)];
            $totalAvant += $avant;
        }

        $messages = [];
        $issues = [];
        if ($r['provisoire'] > 0) {
            $messages[] = 'Provisoire : '.DecompteService::libelle($r['provisoire']).' seront couverts par les jours acquis chaque mois ; '
                .'la réservation se fera quand ces jours seront crédités (1er du mois).';
        }
        if ($r['manque'] > 0.004) {
            $messages[] = self::messageManque($r);
            $issues = ['Raccourcir la période.', 'Scinder la demande en deux périodes.', 'Demander une dérogation à la RH.'];
        }

        $suffisant = $r['manque'] <= 0.004;

        return [
            'suffisant' => $suffisant, 'manque' => $r['manque'], 'couvert_jusqu' => $r['couvert_jusqu'], 'premier_manque' => $r['premier_manque'],
            'provisoire' => $r['provisoire'],
            'bloquant' => ! $suffisant && Parametres::valeur('solde_insuffisant_mode', 'bloquer') !== 'alerter',
            'lots' => $lignes, 'total_avant' => round($totalAvant, 2), 'messages' => $messages, 'issues' => $issues,
        ];
    }

    /** « Il manque 4 jours. Votre solde couvre jusqu'au 04/01/2027. » */
    public static function messageManque(array $r): string
    {
        $texte = 'Solde insuffisant : il manque '.DecompteService::libelle($r['manque']).'. ';
        $texte .= $r['couvert_jusqu']
            ? 'Votre solde couvre jusqu’au '.self::dateFr($r['couvert_jusqu']).'.'
            : 'Votre solde ne couvre aucun jour de cette période.';

        return $texte;
    }

    public static function dateFr(string $iso): string
    {
        return substr($iso, 8, 2).'/'.substr($iso, 5, 2).'/'.substr($iso, 0, 4);
    }

    /**
     * Tableau « Mon solde » : un lot par ligne (du plus ancien au plus récent), les totaux, ce qui reste à acquérir
     * dans l'année et les alertes d'affichage.
     *
     * @return array{lignes: array<int,array>, total: array, premier_lot: ?int, a_acquerir: float, mois_a_acquerir: int,
     *               fin_annee: float, alertes: array<int,array{niveau:string, texte:string}>}
     */
    public static function tableau(int $agentId): array
    {
        $courante = (int) date('Y');
        $annees = array_keys(self::lots($agentId));
        $annees[] = $courante;
        $annees = array_values(array_unique($annees));
        sort($annees);

        $lignes = [];
        $total = ['acquis' => 0.0, 'consommes' => 0.0, 'reserves' => 0.0, 'disponible' => 0.0];
        $premier = null;
        foreach ($annees as $a) {
            $s = SoldeService::situation($agentId, $a);
            $ligne = [
                'annee' => $a, 'acquis' => round($s['acquis'] + $s['report'] + $s['ajustements'], 2),
                'consommes' => $s['consommes'], 'reserves' => $s['reserves'], 'disponible' => $s['disponible'],
                'a_des_mouvements' => $s['a_des_mouvements'],
            ];
            if ($premier === null && $s['disponible'] > 0) {
                $premier = $a;
            }
            foreach (['acquis', 'consommes', 'reserves', 'disponible'] as $k) {
                $total[$k] = round($total[$k] + $ligne[$k], 2);
            }
            $lignes[] = $ligne;
        }

        $projection = AcquisitionService::projections($agentId, $courante)[$courante] ?? [];
        $aAcquerir = round(array_sum($projection), 2);

        $alertes = [];
        $droit = Parametres::decimal('droit_annuel_jours', 30);
        $reliquat = 0.0;
        foreach ($lignes as $l) {
            if ($l['annee'] < $courante && $l['disponible'] > 0) {
                $reliquat += $l['disponible'];
            }
        }
        if ($reliquat > $droit) {
            $alertes[] = ['niveau' => 'info', 'texte' => 'Reliquat important : '.DecompteService::libelle($reliquat).' des années précédentes (plus d’un droit annuel). Ils sont consommés en premier et ne périment pas.'];
        }
        $bas = Parametres::decimal('alerte_solde_bas_jours', 5);
        if ($total['disponible'] > 0 && $total['disponible'] <= $bas) {
            $alertes[] = ['niveau' => 'attention', 'texte' => 'Solde bas : '.DecompteService::libelle($total['disponible']).' disponibles.'];
        }
        if ($total['disponible'] <= 0 && in_array(true, array_column($lignes, 'a_des_mouvements'), true)) {
            $alertes[] = ['niveau' => 'attention', 'texte' => 'Aucun jour disponible pour le moment.'];
        }

        return [
            'lignes' => $lignes, 'total' => $total, 'premier_lot' => $premier, 'a_acquerir' => $aAcquerir, 'mois_a_acquerir' => count($projection),
            'fin_annee' => round($total['disponible'] + $aAcquerir, 2), 'alertes' => $alertes,
        ];
    }

    // ======================================================================
    // Réservation par lot (appelée par SoldeService::reserver)
    // ======================================================================

    /**
     * Écrit la réservation d'une demande : une ligne de registre PAR LOT touché, avec le même numéro de demande.
     * Les jours qui ne seront acquis que plus tard sont enregistrés « différés » : ils sont réservés dès que le crédit
     * mensuel est fait (reserverDifferees). Sans dérogation, un manque refuse la réservation.
     *
     * @throws SoldeException
     */
    public static function reserverDemande(object $demande, array $calcul, bool $forcer): void
    {
        $agentId = (int) $demande->agent_id;
        $jours = self::joursDecomptes($calcul);
        $r = self::repartir($jours, self::lots($agentId), self::projectionsPour($agentId, $jours));

        if ($r['manque'] > 0.004 && ! $forcer) {
            throw new SoldeException(self::messageManque($r));
        }

        foreach ($r['lots'] as $annee => $l) {
            if ($l['reel'] > 0) {
                self::ecrireReservation($demande, (int) $annee, $l['reel'], (string) $l['debut_reel'], (string) $l['fin_reel'], 'Réservation');
            }
            if ($l['provisoire'] > 0) {
                DB::table('demande_imputations')->insert([
                    'demande_id' => $demande->id, 'annee_lot' => $annee, 'jours' => $l['provisoire'],
                    'jour_debut' => $l['debut_prov'], 'jour_fin' => $l['fin_prov'], 'differee' => 1, 'created_at' => now(),
                ]);
            }
        }

        if ($r['manque'] > 0.004) {
            $parAnnee = [];
            foreach ($r['jours_manque'] as $j) {
                $a = (int) substr($j['date'], 0, 4);
                $parAnnee[$a]['jours'] = round(($parAnnee[$a]['jours'] ?? 0) + $j['valeur'], 2);
                $parAnnee[$a]['debut'] ??= $j['date'];
                $parAnnee[$a]['fin'] = $j['date'];
            }
            foreach ($parAnnee as $annee => $p) {
                self::ecrireReservation($demande, $annee, $p['jours'], $p['debut'], $p['fin'], 'Réservation en dérogation (solde insuffisant)');
            }
        }
    }

    private static function ecrireReservation(object $demande, int $annee, float $jours, string $debut, string $fin, string $libelle): void
    {
        DB::table('soldes_mouvements')->insert([
            'agent_id' => $demande->agent_id, 'annee' => $annee, 'type_mouvement' => 'reservation',
            'delta_disponible' => -$jours, 'delta_reserve' => $jours, 'delta_consomme' => 0,
            'demande_id' => $demande->id, 'motif' => "{$libelle} : demande {$demande->reference} (lot {$annee})", 'auteur_id' => null, 'created_at' => now(),
        ]);
        DB::table('demande_imputations')->insert([
            'demande_id' => $demande->id, 'annee_lot' => $annee, 'jours' => $jours, 'jour_debut' => $debut, 'jour_fin' => $fin,
            'differee' => 0, 'reservee_le' => now(), 'created_at' => now(),
        ]);
    }

    /**
     * Réserve les jours « différés » d'un agent dont le lot a maintenant assez de jours (après un crédit mensuel).
     * Les demandes devenues sans effet (annulée, refusée...) sont retirées. Retourne le nombre de jours réservés.
     */
    public static function reserverDifferees(int $agentId): float
    {
        $lignes = DB::table('demande_imputations as i')->join('demandes as d', 'd.id', '=', 'i.demande_id')
            ->where('d.agent_id', $agentId)->where('i.differee', 1)->orderBy('i.jour_debut')->orderBy('i.id')
            ->get(['i.*', 'd.reference', 'd.agent_id', 'd.statut']);

        $total = 0.0;
        foreach ($lignes as $l) {
            if (in_array($l->statut, SoldeService::STATUTS_NON_COMPTES, true)) {
                DB::table('demande_imputations')->where('id', $l->id)->delete();
                continue;
            }
            $disponible = SoldeService::situation($agentId, (int) $l->annee_lot)['disponible'];
            $x = round(min($disponible, (float) $l->jours), 2);
            if ($x <= 0) {
                continue;
            }

            DB::table('soldes_mouvements')->insert([
                'agent_id' => $agentId, 'annee' => $l->annee_lot, 'type_mouvement' => 'reservation',
                'delta_disponible' => -$x, 'delta_reserve' => $x, 'delta_consomme' => 0,
                'demande_id' => $l->demande_id, 'motif' => "Réservation (jours acquis) : demande {$l->reference} (lot {$l->annee_lot})", 'auteur_id' => null, 'created_at' => now(),
            ]);

            if ($x >= (float) $l->jours - 0.004) {
                DB::table('demande_imputations')->where('id', $l->id)->update(['differee' => 0, 'reservee_le' => now()]);
            } else {
                DB::table('demande_imputations')->where('id', $l->id)->update(['jours' => round((float) $l->jours - $x, 2)]);
                DB::table('demande_imputations')->insert([
                    'demande_id' => $l->demande_id, 'annee_lot' => $l->annee_lot, 'jours' => $x, 'jour_debut' => $l->jour_debut,
                    'jour_fin' => $l->jour_fin, 'differee' => 0, 'reservee_le' => now(), 'created_at' => now(),
                ]);
            }
            $total += $x;
        }

        return round($total, 2);
    }
}
