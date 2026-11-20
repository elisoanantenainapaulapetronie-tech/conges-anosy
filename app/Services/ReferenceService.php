<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Numérotation par année : DM-2026-0001, AV-2026-0001, etc. (table compteurs_references). */
class ReferenceService
{
    public static function suivante(string $prefixe, ?int $annee = null): string
    {
        $annee ??= (int) now()->format('Y');

        return DB::transaction(function () use ($prefixe, $annee) {
            $filtre = ['prefixe' => $prefixe, 'annee' => $annee];

            $ligne = DB::table('compteurs_references')->where($filtre)->lockForUpdate()->first();

            if ($ligne === null) {
                $numero = 1;
                DB::table('compteurs_references')->insert($filtre + ['dernier_numero' => $numero]);
            } else {
                $numero = (int) $ligne->dernier_numero + 1;
                DB::table('compteurs_references')->where($filtre)->update(['dernier_numero' => $numero]);
            }

            return sprintf('%s-%d-%04d', $prefixe, $annee, $numero);
        });
    }
}