<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Lot 4 bis : alignement de l'organigramme sur l'annexe officielle de la Région Anosy.
 *   php artisan db:seed --class=OrganigrammeOfficielSeeder
 * Peut être relancé sans danger. N'écrase rien, ne supprime rien.
 *
 *  1. « Service des marchés publics » : l'annexe parle de la « Personne responsable des marchés publics » (une personne,
 *     rattachée au Chef de Région). Renommé seulement si le nom n'a pas déjà été modifié à la main.
 *  2. Cabinet : l'annexe prévoit 2 conseillers techniques, 2 chargés de mission et 2 inspecteurs. Un poste par place,
 *     pour que les places non pourvues apparaissent en « poste vacant » et se remplissent une à une.
 */
class OrganigrammeOfficielSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('structures')
            ->where('code', 'MP')
            ->where('nom', 'Service des marches publics')
            ->update(['nom' => 'Personne responsable des marchés publics']);

        $cabinet = DB::table('structures')->where('code', 'CAB')->first();
        if (! $cabinet) {
            return; // base sans données de démonstration
        }

        $places = [
            ['Conseiller technique', 'Conseiller technique', 2],
            ['Chargé de mission', 'Chargé de mission', 2],
            ['Inspecteur', 'Inspecteur', 2],
        ];

        foreach ($places as [$intitule, $libelleFonction, $nombre]) {
            $fonction = DB::table('fonctions')->where('libelle', $libelleFonction)->first();
            for ($i = 1; $i <= $nombre; $i++) {
                $nom = "{$intitule} ({$i}/{$nombre})";
                if (DB::table('postes')->where('structure_id', $cabinet->id)->where('intitule', $nom)->exists()) {
                    continue;
                }
                DB::table('postes')->insert([
                    'structure_id' => $cabinet->id, 'intitule' => $nom,
                    'fonction_id' => $fonction->id ?? null,
                    'note' => 'Place prévue par l’organigramme officiel', 'actif' => 1,
                ]);
            }
        }
    }
}
