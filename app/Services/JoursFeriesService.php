<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Calendrier des jours fériés de Madagascar : propose les dates d'une année (fixes + fêtes mobiles calculées à partir
 * de Pâques) pour que la RH n'ait plus qu'à les vérifier. Rien n'est enregistré sans passer par remplir().
 */
class JoursFeriesService
{
    /** Date de Pâques (calendrier grégorien, méthode de Meeus/Jones/Butcher). */
    public static function paques(int $annee): DateTimeImmutable
    {
        $a = $annee % 19;
        $b = intdiv($annee, 100);
        $c = $annee % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mois = intdiv($h + $l - 7 * $m + 114, 31);
        $jour = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $annee, $mois, $jour));
    }

    /** @return array<string, string> date => libellé, triée par date */
    public static function proposer(int $annee): array
    {
        $paques = self::paques($annee);
        $liste = [
            "{$annee}-01-01" => 'Jour de l’An',
            "{$annee}-03-29" => 'Commémoration de 1947',
            "{$annee}-05-01" => 'Fête du Travail',
            "{$annee}-06-26" => 'Fête de l’Indépendance',
            "{$annee}-08-15" => 'Assomption',
            "{$annee}-11-01" => 'Toussaint',
            "{$annee}-12-25" => 'Noël',
        ];
        // Fêtes mobiles. Si l'une tombe un jour déjà férié, la fête à date fixe est gardée.
        foreach ([
            [$paques->modify('+1 day'), 'Lundi de Pâques'],
            [$paques->modify('+39 days'), 'Ascension'],
            [$paques->modify('+50 days'), 'Lundi de Pentecôte'],
        ] as [$date, $libelle]) {
            $liste[$date->format('Y-m-d')] ??= $libelle;
        }
        ksort($liste);

        return $liste;
    }

    /** Ajoute les jours de la proposition qui manquent. Renvoie le nombre de jours ajoutés ; ne modifie jamais un jour existant. */
    public static function remplir(int $annee): int
    {
        $ajoutes = 0;
        foreach (self::proposer($annee) as $date => $libelle) {
            $ajoutes += DB::table('jours_feries')->insertOrIgnore(['date_ferie' => $date, 'libelle' => $libelle]);
        }

        return $ajoutes;
    }
}
