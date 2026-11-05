<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot 0 : corrections et compléments du schéma (ne modifie PAS la migration d'origine).
 *  1. v_soldes_agents expose « identifiant » (le matricule est devenu facultatif) ;
 *  2. demande_versions : instantané de la demande à chaque soumission (« version figée ») ;
 *  3. demi-journées de début et de fin (demandes et avis) ;
 *  4. indisponibilites.proposition_id : lien avec la proposition de solde de la RH ;
 *  5. deux paramètres manquants.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Vue des soldes : ajout de la colonne identifiant, le reste est inchangé.
        DB::statement($this->vueSoldes(avecIdentifiant: true));

        // 2. Versions figées des demandes (écriture seule, comme le journal d'audit).
        DB::statement("
            CREATE TABLE demande_versions (
              id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              demande_id  INT UNSIGNED NOT NULL,
              version     SMALLINT UNSIGNED NOT NULL,
              instantane  JSON NOT NULL COMMENT 'Contenu de la demande, du circuit et des pieces au moment de la soumission',
              cree_par    INT UNSIGNED NULL,
              created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_versions_demande (demande_id, version),
              CONSTRAINT fk_versions_demande FOREIGN KEY (demande_id) REFERENCES demandes (id) ON DELETE RESTRICT,
              CONSTRAINT fk_versions_auteur  FOREIGN KEY (cree_par)   REFERENCES agents (id)   ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        DB::unprepared("CREATE TRIGGER tr_demande_versions_bu BEFORE UPDATE ON demande_versions
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Version de demande non modifiable'");
        DB::unprepared("CREATE TRIGGER tr_demande_versions_bd BEFORE DELETE ON demande_versions
            FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Version de demande non supprimable'");

        // 3. Demi-journées : « journee » = jour entier ; on peut commencer l'après-midi ou finir le matin.
        foreach (['demandes', 'avis_prealables'] as $table) {
            DB::statement("ALTER TABLE {$table}
                ADD COLUMN demi_journee_debut ENUM('journee','apres_midi') NOT NULL DEFAULT 'journee' AFTER date_fin,
                ADD COLUMN demi_journee_fin   ENUM('journee','matin')      NOT NULL DEFAULT 'journee' AFTER demi_journee_debut");
        }

        // 4. Une indisponibilité de type congé donne lieu à une proposition de solde contrôlée.
        DB::statement('ALTER TABLE indisponibilites
            ADD COLUMN proposition_id INT UNSIGNED NULL AFTER duree_jours,
            ADD CONSTRAINT fk_indispo_proposition FOREIGN KEY (proposition_id) REFERENCES soldes_propositions (id) ON DELETE RESTRICT');

        // 5. Paramètres.
        DB::table('parametres')->insertOrIgnore([
            ['cle' => 'duree_code_activation_jours', 'valeur' => '5',
             'description' => 'Validite du code d activation remis par la RH [A confirmer avec la RH]'],
            ['cle' => 'seuil_alerte_absences_multiples', 'valeur' => '2',
             'description' => 'Nombre d agents d un meme service absents sur la periode a partir duquel le responsable est alerte'],
        ]);
    }

    public function down(): void
    {
        DB::table('parametres')->whereIn('cle', ['duree_code_activation_jours', 'seuil_alerte_absences_multiples'])->delete();

        DB::statement('ALTER TABLE indisponibilites DROP FOREIGN KEY fk_indispo_proposition');
        DB::statement('ALTER TABLE indisponibilites DROP COLUMN proposition_id');

        foreach (['demandes', 'avis_prealables'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN demi_journee_fin, DROP COLUMN demi_journee_debut");
        }

        DB::statement('DROP TABLE IF EXISTS demande_versions'); // supprime aussi ses déclencheurs

        DB::statement($this->vueSoldes(avecIdentifiant: false));
    }

    private function vueSoldes(bool $avecIdentifiant): string
    {
        $identifiant = $avecIdentifiant ? 'a.identifiant,' : '';

        return "
            CREATE OR REPLACE VIEW v_soldes_agents AS
            SELECT a.id AS agent_id,
                   {$identifiant}
                   a.matricule,
                   a.nom,
                   a.prenoms,
                   s.annee,
                   s.solde_disponible,
                   s.jours_reserves,
                   s.jours_consommes,
                   COALESCE(w.jours_en_attente, 0) AS jours_en_attente,
                   s.solde_disponible - COALESCE(w.jours_en_attente, 0) AS solde_previsionnel
            FROM (
                   SELECT agent_id, annee,
                          SUM(delta_disponible) AS solde_disponible,
                          SUM(delta_reserve)    AS jours_reserves,
                          SUM(delta_consomme)   AS jours_consommes
                   FROM soldes_mouvements
                   GROUP BY agent_id, annee
                 ) s
            JOIN agents a ON a.id = s.agent_id
            LEFT JOIN (
                   SELECT d.agent_id, YEAR(d.date_debut) AS annee, SUM(d.duree_jours) AS jours_en_attente
                   FROM demandes d
                   JOIN types_demande t ON t.id = d.type_demande_id
                   WHERE d.statut IN ('soumise','en_controle_rh','a_completer','en_attente_decision')
                     AND t.decompte_solde = 'oui'
                   GROUP BY d.agent_id, YEAR(d.date_debut)
                 ) w ON w.agent_id = s.agent_id AND w.annee = s.annee
        ";
    }
};
