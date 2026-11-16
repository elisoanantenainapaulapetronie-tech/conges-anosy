<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 5 quinquies : corrections de solde et centre de notifications.
 *  1. notifications.lien : page à ouvrir depuis le centre de notifications (chemin interne, jamais une adresse externe).
 *  2. soldes_propositions : nouveau statut « retiree » (l'auteur retire sa proposition avant le contrôle), avec la date
 *     et le motif du retrait ; la contrainte ck_prop_statut est élargie en conséquence.
 * Aucune donnée existante n'est modifiée ni supprimée. Les justificatifs (fichiers) utilisent la table pieces_jointes
 * qui existe déjà (colonne proposition_id) : rien à créer pour eux.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('notifications', 'lien')) {
            DB::statement("ALTER TABLE notifications ADD COLUMN lien VARCHAR(255) NULL COMMENT 'Chemin interne a ouvrir (ex. /solde)' AFTER message");
        }

        if (! Schema::hasColumn('soldes_propositions', 'retire_le')) {
            DB::statement("ALTER TABLE soldes_propositions
                ADD COLUMN retire_le DATETIME NULL COMMENT 'Date du retrait par son auteur' AFTER commentaire_controle,
                ADD COLUMN motif_retrait VARCHAR(500) NULL COMMENT 'Pourquoi l auteur retire sa proposition' AFTER retire_le");
        }

        $this->retirerContrainte();
        DB::statement("ALTER TABLE soldes_propositions MODIFY statut ENUM('en_attente','valide','rejete','retiree') NOT NULL DEFAULT 'en_attente'");
        DB::statement("ALTER TABLE soldes_propositions ADD CONSTRAINT ck_prop_statut CHECK (
            statut = 'en_attente'
            OR (statut = 'retiree' AND retire_le IS NOT NULL AND motif_retrait IS NOT NULL)
            OR (statut IN ('valide','rejete') AND controle_par IS NOT NULL AND controle_le IS NOT NULL))");
    }

    public function down(): void
    {
        if (DB::table('soldes_propositions')->where('statut', 'retiree')->exists()) {
            throw new RuntimeException('Des propositions retirées existent : retour en arrière refusé pour ne rien perdre de l’historique.');
        }

        $this->retirerContrainte();
        DB::statement("ALTER TABLE soldes_propositions MODIFY statut ENUM('en_attente','valide','rejete') NOT NULL DEFAULT 'en_attente'");
        DB::statement("ALTER TABLE soldes_propositions ADD CONSTRAINT ck_prop_statut CHECK (statut = 'en_attente' OR (controle_par IS NOT NULL AND controle_le IS NOT NULL))");
        DB::statement('ALTER TABLE soldes_propositions DROP COLUMN motif_retrait, DROP COLUMN retire_le');
        if (Schema::hasColumn('notifications', 'lien')) {
            DB::statement('ALTER TABLE notifications DROP COLUMN lien');
        }
    }

    /** MariaDB : DROP CONSTRAINT ; MySQL 8 : DROP CHECK. */
    private function retirerContrainte(): void
    {
        try {
            DB::statement('ALTER TABLE soldes_propositions DROP CONSTRAINT ck_prop_statut');
        } catch (Throwable) {
            DB::statement('ALTER TABLE soldes_propositions DROP CHECK ck_prop_statut');
        }
    }
};
