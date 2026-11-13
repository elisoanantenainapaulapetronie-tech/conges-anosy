<?php

namespace App\Support;

/**
 * Lit un nombre de jours saisi par un humain : « 2,5 », « 2.5 », « -1,5 », « − 1,5 » (signe typographique),
 * « 1 000 », « +3 ». Renvoie une chaîne numérique avec un point (« -1.5 »), ou null si le texte n'est pas un nombre.
 * Les contrôles de fond (pas de 0,5, non nul) restent faits par SoldeService.
 */
class NombreSaisi
{
    public static function normaliser(mixed $texte): ?string
    {
        if ($texte === null) {
            return null;
        }
        $t = trim((string) $texte);
        $t = str_replace(["\u{2212}", "\u{2013}", "\u{2014}"], '-', $t);   // − – — deviennent -
        $t = preg_replace('/[\s\x{00A0}\x{202F}]+/u', '', $t);               // espaces, espaces insécables
        if ($t === '' || $t === null) {
            return null;
        }
        if (substr_count($t, ',') + substr_count($t, '.') > 1) {
            return null;                                                      // une seule marque décimale
        }
        $t = str_replace(',', '.', $t);

        return preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $t) ? (string) ((float) $t) : null;
    }
}
