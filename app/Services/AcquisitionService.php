<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Acquisition MENSUELLE des congés (lot 5 ter).
 *
 * Règles décidées avec la RH :
 *  - le droit annuel (paramètre droit_annuel_jours, en JOURS OUVRÉS) s'acquiert mois par mois : 30 / 12 = 2,5 jours par mois ;
 *  - le mois commencé compte : un agent entré le 1er du mois acquiert ce mois-là ; entré un autre jour, il commence
 *    le mois SUIVANT ;
 *  - un agent sorti en cours de mois n'acquiert pas ce mois-là ;
 *  - jamais deux crédits pour le même mois (contrôle dans le code ET index unique en base) ;
 *  - une année qui a déjà un solde initial ou un droit annuel crédité en bloc (ancien mode) n'est pas touchée :
 *    la RH l'a fixée elle-même, on ne la double jamais ;
 *  - les mois avant « acquisition_debut_mois » ne sont pas gérés ici : leurs soldes sont saisis par la RH.
 *
 * Le crédit est un mouvement « droit_annuel » avec une période (AAAA-MM). Les mois peuvent être rattrapés
 * (serveur éteint, premier démarrage) sans rien doubler.
 *
 * Les fonctions « pures » (moisAcquis, premierMois, comparer des périodes) ne touchent pas la base.
 */
class AcquisitionService
{
    /** Jours acquis chaque mois (droit annuel / 12), à 2 décimales. */
    public static function mensualite(?float $droitAnnuel = null): float
    {
        $droitAnnuel ??= Parametres::decimal('droit_annuel_jours', 30);

        return round($droitAnnuel / 12, 2);
    }

    /** Premier mois d'acquisition d'un agent (AAAA-MM) : le mois d'entrée si c'est le 1er, sinon le suivant. */
    public static function premierMois(\DateTimeInterface|string $entree): string
    {
        $e = DecompteService::date($entree);

        return ($e->format('j') === '1' ? $e : $e->modify('first day of next month'))->format('Y-m');
    }

    /** L'agent acquiert-il le mois $periode (AAAA-MM) ? Pur : entré à temps, et pas sorti avant la fin du mois. */
    public static function moisAcquis(\DateTimeInterface|string $entree, \DateTimeInterface|string|null $sortie, string $periode): bool
    {
        if ($periode < self::premierMois($entree)) {
            return false;
        }
        if ($sortie === null || $sortie === '') {
            return true;
        }
        $dernier = (new DateTimeImmutable($periode.'-01'))->modify('last day of this month');

        return DecompteService::date($sortie) >= $dernier;
    }

    /** Libellé « novembre 2026 ». */
    public static function libelleMois(string $periode): string
    {
        $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $mois[(int) substr($periode, 5, 2)].' '.substr($periode, 0, 4);
    }

    /** Premier mois géré par l'acquisition mensuelle (paramètre). */
    public static function debutGestion(): string
    {
        $v = (string) Parametres::valeur('acquisition_debut_mois', '2026-01');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $v) ? $v : '2026-01';
    }

    /**
     * Mois qu'un agent actif acquiert encore et qui ne sont pas crédités, par année : [annee => [periode => jours]].
     * C'est la « projection » : ce que le crédit mensuel ajoutera. Rien n'est écrit.
     */
    public static function projections(int $agentId, int $jusquAnnee): array
    {
        $agent = DB::table('agents')->where('id', $agentId)->first();
        if (! $agent || $agent->situation !== 'actif' || ! $agent->date_entree) {
            return [];
        }

        $credites = DB::table('soldes_mouvements')->where('agent_id', $agentId)->whereNotNull('periode')->pluck('periode')->flip()->all();
        $enBloc = self::anneesEnBloc($agentId);
        $debut = self::debutGestion();
        $mensualite = self::mensualite();

        $projections = [];
        for ($annee = (int) substr($debut, 0, 4); $annee <= $jusquAnnee; $annee++) {
            if (isset($enBloc[$annee])) {
                continue;
            }
            for ($m = 1; $m <= 12; $m++) {
                $periode = sprintf('%04d-%02d', $annee, $m);
                if ($periode < $debut || isset($credites[$periode]) || ! self::moisAcquis($agent->date_entree, $agent->date_sortie, $periode)) {
                    continue;
                }
                $projections[$annee][$periode] = $mensualite;
            }
        }

        return $projections;
    }

    /** Années dont le droit a été fixé « en bloc » (solde initial, ou ancien droit annuel sans période) : [annee => true]. */
    public static function anneesEnBloc(int $agentId): array
    {
        return DB::table('soldes_mouvements')->where('agent_id', $agentId)
            ->where(fn ($q) => $q->where('type_mouvement', 'solde_initial')->orWhere(fn ($r) => $r->where('type_mouvement', 'droit_annuel')->whereNull('periode')))
            ->distinct()->pluck('annee')->mapWithKeys(fn ($a) => [(int) $a => true])->all();
    }

    /**
     * Crédite UN mois à UN agent. Retourne les jours crédités, ou null si rien n'est à faire (agent inactif, mois déjà
     * crédité, mois non acquis, année fixée en bloc, mois antérieur au début de la gestion mensuelle).
     */
    public static function acquerirMois(int $agentId, string $periode): ?float
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) || $periode < self::debutGestion()) {
            return null;
        }
        $annee = (int) substr($periode, 0, 4);

        return DB::transaction(function () use ($agentId, $periode, $annee) {
            $agent = DB::table('agents')->where('id', $agentId)->lockForUpdate()->first();
            if (! $agent || $agent->situation !== 'actif' || ! $agent->date_entree) {
                return null;
            }
            if (! self::moisAcquis($agent->date_entree, $agent->date_sortie, $periode)) {
                return null;
            }
            if (DB::table('soldes_mouvements')->where('agent_id', $agentId)->where('periode', $periode)->exists()) {
                return null;
            }
            if (isset(self::anneesEnBloc($agentId)[$annee])) {
                return null;
            }

            $jours = self::mensualite();
            if ($jours <= 0) {
                return null;
            }

            DB::table('soldes_mouvements')->insert([
                'agent_id' => $agentId, 'annee' => $annee, 'periode' => $periode, 'type_mouvement' => 'droit_annuel',
                'delta_disponible' => $jours, 'delta_reserve' => 0, 'delta_consomme' => 0,
                'motif' => 'Acquisition '.self::libelleMois($periode), 'auteur_id' => null, 'created_at' => now(),
            ]);

            // Des jours demandés d'avance attendaient peut-être ce crédit.
            ImputationService::reserverDifferees($agentId);

            return $jours;
        });
    }

    /**
     * Rattrape tous les mois manquants, jusqu'au mois $jusqua (AAAA-MM, par défaut le mois en cours inclus).
     * Peut être relancé sans danger. $agentId limite le calcul à un agent.
     *
     * @return array{agents:int, mois:int, jours:float, jusqua:string}
     */
    public static function rattraper(?string $jusqua = null, ?int $agentId = null): array
    {
        $jusqua ??= date('Y-m');
        $debut = self::debutGestion();
        $agents = DB::table('agents')->where('situation', 'actif')->whereNotNull('date_entree')
            ->when($agentId, fn ($q) => $q->where('id', $agentId))->orderBy('id')->get(['id', 'date_entree']);

        $touches = 0;
        $mois = 0;
        $jours = 0.0;
        foreach ($agents as $a) {
            $premier = max(self::premierMois($a->date_entree), $debut);
            $courant = new DateTimeImmutable($premier.'-01');
            $fin = new DateTimeImmutable($jusqua.'-01');
            $credits = 0;
            for (; $courant <= $fin; $courant = $courant->modify('+1 month')) {
                $c = self::acquerirMois((int) $a->id, $courant->format('Y-m'));
                if ($c !== null) {
                    $credits++;
                    $mois++;
                    $jours += $c;
                }
            }
            if ($credits > 0) {
                $touches++;
            }
        }

        AuditService::enregistrer('acquisition_mensuelle', null, null, ['jusqua' => $jusqua, 'agents' => $touches, 'mois' => $mois, 'jours' => $jours]);

        return ['agents' => $touches, 'mois' => $mois, 'jours' => round($jours, 2), 'jusqua' => $jusqua];
    }
}
