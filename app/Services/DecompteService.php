<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Décompte des jours d'absence (dossier, partie 8.3).
 *
 *  - on compte en JOURS OUVRÉS : le samedi, le dimanche et les jours fériés du calendrier ne sont pas décomptés ;
 *  - une demi-journée vaut 0,5 : on peut commencer l'après-midi (premier jour) ou finir le matin (dernier jour) ;
 *  - une période à cheval sur deux années est répartie par année (les soldes se tiennent par année).
 *
 * Le calcul (calculer) est PUR : il ne touche pas la base, il reçoit les jours fériés. Il se teste donc sans base.
 * compter() est la version de l'application : elle lit les jours fériés dans la table jours_feries.
 *
 *   DecompteService::compter('2026-12-07', '2026-12-18')['total']                    // 10.0
 *   DecompteService::compter('2026-03-23', '2026-03-27', 'apres_midi', 'matin')      // 4.0
 */
class DecompteService
{
    public const JOURNEE = 'journee';
    public const APRES_MIDI = 'apres_midi';
    public const MATIN = 'matin';

    private const JOURS_SEMAINE = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];

    /**
     * Décompte avec les jours fériés de la base.
     *
     * @return array{total: float, par_annee: array<int, float>, lignes: array<int, array>, calendaires: int, ouvres: int, avertissements: array<int, string>}
     */
    public static function compter(
        DateTimeInterface|string $debut,
        DateTimeInterface|string $fin,
        string $demiDebut = self::JOURNEE,
        string $demiFin = self::JOURNEE,
    ): array {
        $d = self::date($debut);
        $f = self::date($fin);
        $feries = self::feriesEntre($d, $f);

        $resultat = self::calculer($d, $f, $demiDebut, $demiFin, $feries);
        $resultat['avertissements'] = self::avertissements($d, $f);

        return $resultat;
    }

    /**
     * Calcul pur.
     *
     * @param array<string, string> $feries date (Y-m-d) => libellé
     * @return array{total: float, par_annee: array<int, float>, lignes: array<int, array>, calendaires: int, ouvres: int, avertissements: array<int, string>}
     */
    public static function calculer(
        DateTimeInterface|string $debut,
        DateTimeInterface|string $fin,
        string $demiDebut = self::JOURNEE,
        string $demiFin = self::JOURNEE,
        array $feries = [],
    ): array {
        $d = self::date($debut);
        $f = self::date($fin);

        if ($f < $d) {
            throw new InvalidArgumentException('La date de fin est avant la date de début.');
        }
        if (! in_array($demiDebut, [self::JOURNEE, self::APRES_MIDI], true)) {
            throw new InvalidArgumentException('Début de période : « journée entière » ou « après-midi » seulement.');
        }
        if (! in_array($demiFin, [self::JOURNEE, self::MATIN], true)) {
            throw new InvalidArgumentException('Fin de période : « journée entière » ou « matin » seulement.');
        }
        if ($d == $f && $demiDebut === self::APRES_MIDI && $demiFin === self::MATIN) {
            throw new InvalidArgumentException('Une même journée ne peut pas commencer l’après-midi et finir le matin.');
        }

        $lignes = [];
        $parAnnee = [];
        $total = 0.0;
        $ouvres = 0;
        $calendaires = 0;

        for ($jour = $d; $jour <= $f; $jour = $jour->modify('+1 day')) {
            $calendaires++;
            $iso = $jour->format('Y-m-d');
            $numero = (int) $jour->format('N');
            $valeur = 0.0;
            $motif = null;

            if ($numero >= 6) {
                $motif = $numero === 6 ? 'Samedi : non décompté' : 'Dimanche : non décompté';
            } elseif (isset($feries[$iso])) {
                $motif = 'Jour férié : '.$feries[$iso];
            } else {
                $valeur = 1.0;
                $ouvres++;
                if ($jour == $d && $demiDebut === self::APRES_MIDI) {
                    $valeur -= 0.5;
                    $motif = 'Début l’après-midi : demi-journée';
                }
                if ($jour == $f && $demiFin === self::MATIN) {
                    $valeur -= 0.5;
                    $motif = 'Fin le matin : demi-journée';
                }
            }

            $total += $valeur;
            $annee = (int) $jour->format('Y');
            $parAnnee[$annee] = ($parAnnee[$annee] ?? 0.0) + $valeur;
            $lignes[] = ['date' => $iso, 'jour' => self::JOURS_SEMAINE[$numero], 'valeur' => $valeur, 'motif' => $motif];
        }

        return [
            'total' => $total,
            'par_annee' => $parAnnee,
            'lignes' => $lignes,
            'calendaires' => $calendaires,
            'ouvres' => $ouvres,
            'avertissements' => [],
        ];
    }

    /** Le jour est-il travaillé ? (ni week-end, ni férié) */
    public static function estOuvre(DateTimeInterface|string $jour, array $feries = []): bool
    {
        $j = self::date($jour);

        return (int) $j->format('N') < 6 && ! isset($feries[$j->format('Y-m-d')]);
    }

    /**
     * Date obtenue en ajoutant $n jours ouvrés (pour les délais : avis sous 2 jours ouvrés, décision sous 3...).
     * Le jour de départ ne compte pas : vendredi + 2 jours ouvrés = mardi.
     *
     * @param array<string, string> $feries
     */
    public static function ajouterJoursOuvres(DateTimeInterface|string $depart, int $n, array $feries = []): DateTimeImmutable
    {
        $jour = self::date($depart);
        $restant = max(0, $n);

        while ($restant > 0) {
            $jour = $jour->modify('+1 day');
            if (self::estOuvre($jour, $feries)) {
                $restant--;
            }
        }

        return $jour;
    }

    /** Même calcul avec les jours fériés de la base (fenêtre large : 4 jours calendaires par jour ouvré demandé). */
    public static function echeance(DateTimeInterface|string $depart, int $n): DateTimeImmutable
    {
        $d = self::date($depart);

        return self::ajouterJoursOuvres($d, $n, self::feriesEntre($d, $d->modify('+'.(max(1, $n) * 4 + 10).' days')));
    }

    /** @return array<string, string> date (Y-m-d) => libellé */
    public static function feriesEntre(DateTimeInterface|string $debut, DateTimeInterface|string $fin): array
    {
        return DB::table('jours_feries')
            ->whereBetween('date_ferie', [self::date($debut)->format('Y-m-d'), self::date($fin)->format('Y-m-d')])
            ->pluck('libelle', 'date_ferie')
            ->all();
    }

    /** Une année sans aucun jour férié enregistré fausse le décompte : on le signale au lieu de se taire. */
    public static function avertissements(DateTimeInterface|string $debut, DateTimeInterface|string $fin): array
    {
        $a1 = (int) self::date($debut)->format('Y');
        $a2 = (int) self::date($fin)->format('Y');
        $avertissements = [];

        for ($annee = $a1; $annee <= $a2; $annee++) {
            if (! DB::table('jours_feries')->whereYear('date_ferie', $annee)->exists()) {
                $avertissements[] = "Aucun jour férié n’est enregistré pour {$annee} : le décompte peut être surestimé (la RH doit compléter le calendrier).";
            }
        }

        return $avertissements;
    }

    /** Accepte une date texte (Y-m-d) ou un objet date, et renvoie toujours une date sans heure. */
    public static function date(DateTimeInterface|string $valeur): DateTimeImmutable
    {
        $texte = $valeur instanceof DateTimeInterface ? $valeur->format('Y-m-d') : substr(trim($valeur), 0, 10);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $texte);

        if (! $date || $date->format('Y-m-d') !== $texte) {
            throw new InvalidArgumentException("Date invalide : « {$texte} ».");
        }

        return $date;
    }

    /** Écriture lisible : 4 → « 4 jours », 0,5 → « 0,5 jour », 2,5 → « 2,5 jours ». */
    public static function libelle(float $jours): string
    {
        $texte = rtrim(rtrim(number_format($jours, 2, ',', ' '), '0'), ',');

        return $texte.(abs($jours) > 1 ? ' jours' : ' jour');
    }
}
