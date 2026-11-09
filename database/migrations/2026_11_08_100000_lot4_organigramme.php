<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 4 : organigramme évolutif.
 *
 * Les structures (arbre), les rôles (responsables) et les fiches (agents, fonctions) existent déjà : l'organigramme
 * est CALCULÉ à partir d'eux, rien n'est dessiné à la main. Cette migration ajoute seulement la table « postes »,
 * pour les places qui n'ont pas (encore) de titulaire ou qui n'ont pas de rôle : poste vacant, poste à créer,
 * nouvelle fonction. Aucune donnée existante n'est modifiée ni supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('postes')) {
            return;
        }

        DB::statement("CREATE TABLE postes (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            structure_id INT UNSIGNED NOT NULL COMMENT 'Structure où se trouve le poste',
            intitule     VARCHAR(150) NOT NULL COMMENT 'Ex. Directeur de cabinet, Chef de protocole',
            fonction_id  INT UNSIGNED NULL COMMENT 'Fonction correspondante (facultatif)',
            titulaire_id INT UNSIGNED NULL COMMENT 'Agent qui occupe le poste ; vide = poste vacant',
            note         VARCHAR(255) NULL,
            actif        TINYINT(1) NOT NULL DEFAULT 1,
            created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ix_postes_structure (structure_id),
            KEY ix_postes_titulaire (titulaire_id),
            CONSTRAINT fk_postes_structure FOREIGN KEY (structure_id) REFERENCES structures (id) ON DELETE RESTRICT,
            CONSTRAINT fk_postes_fonction  FOREIGN KEY (fonction_id)  REFERENCES fonctions (id)  ON DELETE SET NULL,
            CONSTRAINT fk_postes_titulaire FOREIGN KEY (titulaire_id) REFERENCES agents (id)     ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    public function down(): void
    {
        Schema::dropIfExists('postes');
    }
};
