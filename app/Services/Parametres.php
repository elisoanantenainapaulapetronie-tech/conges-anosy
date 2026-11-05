<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Lecture des paramètres de la table « parametres » (plafonds, délais, validités...).
 * Les valeurs ne sont lues qu'une fois par requête.
 *
 *   Parametres::entier('validite_autorisation_jours', 7);
 */
class Parametres
{
    private static ?array $cache = null;

    public static function valeur(string $cle, ?string $defaut = null): ?string
    {
        self::$cache ??= DB::table('parametres')->pluck('valeur', 'cle')->all();

        return self::$cache[$cle] ?? $defaut;
    }

    public static function entier(string $cle, int $defaut): int
    {
        $v = self::valeur($cle);

        return is_numeric($v) ? (int) $v : $defaut;
    }

    public static function decimal(string $cle, float $defaut): float
    {
        $v = self::valeur($cle);

        return is_numeric($v) ? (float) $v : $defaut;
    }

    /** À appeler après une modification des paramètres, et dans les tests. */
    public static function oublier(): void
    {
        self::$cache = null;
    }
}
