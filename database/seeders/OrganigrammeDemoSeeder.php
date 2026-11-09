<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Postes de démonstration (facultatif) : quelques places vacantes de l'organigramme officiel.
 *   php artisan db:seed --class=OrganigrammeDemoSeeder
 * Peut être relancé sans danger : un poste déjà présent n'est pas recréé.
 */
class OrganigrammeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $postes = [
            ['CAB', 'Directeur de cabinet', 'Directeur de cabinet', 'À pourvoir : voir la note de service de nomination'],
            ['CAB', 'Chef de protocole', 'Chef de protocole', null],
            ['CAB', 'Chef du secrétariat particulier', 'Chef du secrétariat particulier', null],
            ['MP', 'Personne responsable des marchés publics', 'Personne responsable des marchés publics', null],
            ['SRH', 'Gestionnaire de paie', null, 'Poste à créer (à confirmer avec la RH)'],
        ];

        foreach ($postes as [$codeStructure, $intitule, $libelleFonction, $note]) {
            $structure = DB::table('structures')->where('code', $codeStructure)->first();
            if (! $structure || DB::table('postes')->where('structure_id', $structure->id)->where('intitule', $intitule)->exists()) {
                continue;
            }
            $fonction = $libelleFonction ? DB::table('fonctions')->where('libelle', $libelleFonction)->first() : null;
            DB::table('postes')->insert([
                'structure_id' => $structure->id, 'intitule' => $intitule,
                'fonction_id' => $fonction->id ?? null, 'note' => $note, 'actif' => 1,
            ]);
        }
    }
}
