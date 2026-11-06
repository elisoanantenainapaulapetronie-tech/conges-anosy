<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot 1 bis : règles de circuit validées avec l'encadreur.
 *  1. demande_etapes.motif_ecart : motif obligatoire quand la décision accepte malgré un avis défavorable ;
 *  2. demandes.hors_region et avis_prealables.hors_region : mission hors de la Région (décision du Chef de Région) ;
 *  3. quatre paramètres (modifiables par le RH responsable, sans toucher au code).
 * Ne modifie aucune migration existante.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE demande_etapes
            ADD COLUMN motif_ecart TEXT NULL COMMENT 'Motif obligatoire si la decision accepte malgre un avis defavorable' AFTER commentaire");

        foreach (['avis_prealables', 'demandes'] as $table) {
            DB::statement("ALTER TABLE {$table}
                ADD COLUMN hors_region TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Mission hors de la Region Anosy' AFTER motif");
        }

        DB::table('parametres')->insertOrIgnore([
            ['cle' => 'motif_obligatoire_si_avis_defavorable', 'valeur' => '1',
             'description' => '1 = la decision qui accepte malgre un avis defavorable exige un motif'],
            ['cle' => 'directeur_avis_par', 'valeur' => 'secretaire_general',
             'description' => 'Qui donne l avis sur la demande d un Directeur [A valider par l administration]'],
            ['cle' => 'directeur_decision_par', 'valeur' => 'chef_region',
             'description' => 'Qui decide de la demande d un Directeur [A valider par l administration]'],
            ['cle' => 'mission_hors_region_decision_par', 'valeur' => 'chef_region',
             'description' => 'Qui decide d une mission hors de la Region [A valider par l administration]'],
        ]);
    }

    public function down(): void
    {
        DB::table('parametres')->whereIn('cle', [
            'motif_obligatoire_si_avis_defavorable', 'directeur_avis_par',
            'directeur_decision_par', 'mission_hors_region_decision_par',
        ])->delete();

        foreach (['demandes', 'avis_prealables'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN hors_region");
        }
        DB::statement('ALTER TABLE demande_etapes DROP COLUMN motif_ecart');
    }
};
