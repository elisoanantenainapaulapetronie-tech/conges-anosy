<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 5 ter : acquisition mensuelle des congés, lots par année, imputation des demandes.
 *
 *  1. soldes_mouvements.periode (AAAA-MM) : le mois d'acquisition d'un crédit mensuel. L'index UNIQUE (agent_id, periode)
 *     interdit en base deux crédits pour le même agent et le même mois. Les autres lignes ont periode = NULL
 *     (plusieurs NULL sont permis) : le registre existant n'est pas modifié.
 *  2. demande_imputations : pour chaque demande, le lot (année d'acquisition) qui couvre ses jours. Une ligne « différée »
 *     attend que les jours soient acquis (crédit mensuel) avant d'être réservée au registre.
 *  3. Paramètres de la gestion des soldes (valeurs par défaut décidées avec la RH).
 *
 * Aucune donnée existante n'est modifiée ni supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('soldes_mouvements', 'periode')) {
            DB::statement("ALTER TABLE soldes_mouvements
                ADD COLUMN periode CHAR(7) NULL COMMENT 'Mois d acquisition AAAA-MM (credit mensuel)' AFTER annee");
            DB::statement('ALTER TABLE soldes_mouvements ADD UNIQUE KEY uq_acquisition_mensuelle (agent_id, periode)');
        }

        if (! Schema::hasTable('demande_imputations')) {
            DB::statement("CREATE TABLE demande_imputations (
                id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                demande_id  INT UNSIGNED NOT NULL,
                annee_lot   SMALLINT UNSIGNED NOT NULL COMMENT 'Annee d acquisition du lot qui couvre ces jours',
                jours       DECIMAL(5,2) NOT NULL,
                jour_debut  DATE NOT NULL,
                jour_fin    DATE NOT NULL,
                differee    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = jours pas encore acquis : reserves des que le credit mensuel est fait',
                reservee_le DATETIME NULL,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY ix_imput_demande (demande_id),
                KEY ix_imput_differee (differee),
                CONSTRAINT fk_imput_demande FOREIGN KEY (demande_id) REFERENCES demandes (id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        $parametres = [
            ['acquisition_debut_mois', '2026-01', 'Premier mois gere par l acquisition mensuelle (avant : soldes saisis par la RH)'],
            ['anticipation_autorisee', '0', '1 = les jours du mois a venir peuvent etre pris avant d etre acquis ; 0 = jamais'],
            ['solde_insuffisant_mode', 'bloquer', 'bloquer = la soumission est refusee ; alerter = le decideur est prevenu'],
            ['reliquat_validite_annees', null, 'Vide = les reliquats ne perimment jamais (decision de la RH)'],
        ];
        foreach ($parametres as [$cle, $valeur, $description]) {
            DB::table('parametres')->insertOrIgnore(['cle' => $cle, 'valeur' => $valeur, 'description' => $description]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('demande_imputations');

        if (Schema::hasColumn('soldes_mouvements', 'periode')) {
            DB::statement('ALTER TABLE soldes_mouvements DROP INDEX uq_acquisition_mensuelle');
            DB::statement('ALTER TABLE soldes_mouvements DROP COLUMN periode');
        }
        DB::table('parametres')->whereIn('cle', ['acquisition_debut_mois', 'anticipation_autorisee', 'solde_insuffisant_mode', 'reliquat_validite_annees'])->delete();
    }
};
