<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 3 : améliorations.
 *  - rattachement : 3e valeur « sg_region » (le Secrétaire Général puis le Chef de Région décident, dans cet ordre) ;
 *  - structures.type_precision : texte libre quand le type est « Autre » ;
 *  - fonctions : catégorie, structure de rattachement, autorité par défaut, actif (l'organigramme devient évolutif) ;
 *  - complète les fonctions et les services de l'organigramme officiel (ajout seulement, jamais de suppression).
 * Aucune donnée existante n'est modifiée ni supprimée : les anciennes valeurs « sg » et « region » restent valables.
 */
return new class extends Migration
{
    private const TABLES_RATTACHEMENT = ['agents', 'structures', 'affectations_historique'];

    public function up(): void
    {
        // 1. Trois autorités possibles. On élargit l'ENUM : les lignes existantes ne changent pas.
        foreach (self::TABLES_RATTACHEMENT as $table) {
            $commentaire = $table === 'structures' ? 'Circuit : sg, region, ou sg_region (les deux, dans cet ordre)'
                : ($table === 'agents' ? 'Renseigne par la RH, l agent ne peut pas le changer ; sg_region = SG puis Chef de Region' : null);
            DB::statement("ALTER TABLE {$table} MODIFY rattachement ENUM('sg','region','sg_region') NOT NULL"
                .($commentaire ? " COMMENT '{$commentaire}'" : ''));
        }

        // 2. Type « Autre » : le libellé saisi à la main.
        if (! Schema::hasColumn('structures', 'type_precision')) {
            DB::statement("ALTER TABLE structures ADD COLUMN type_precision VARCHAR(80) NULL COMMENT 'Texte libre quand type = autre' AFTER type");
        }

        // 3. Fonctions : liste ouverte, rattachable à une structure et à une autorité.
        if (! Schema::hasColumn('fonctions', 'categorie')) {
            DB::statement("ALTER TABLE fonctions
                ADD COLUMN categorie VARCHAR(30) NULL COMMENT 'direction, encadrement, cabinet, technique, appui, autre' AFTER libelle,
                ADD COLUMN structure_id INT UNSIGNED NULL COMMENT 'Direction ou service habituel (suggestion)' AFTER categorie,
                ADD COLUMN rattachement ENUM('sg','region','sg_region') NULL COMMENT 'Autorite decisionnaire habituelle (suggestion)' AFTER structure_id,
                ADD COLUMN actif TINYINT(1) NOT NULL DEFAULT 1 AFTER rattachement,
                ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                ADD KEY ix_fonctions_structure (structure_id),
                ADD CONSTRAINT fk_fonctions_structure FOREIGN KEY (structure_id) REFERENCES structures (id) ON DELETE SET NULL");
        }

        // 4. Catégories des fonctions déjà présentes.
        $categories = [
            'direction' => ['Chef de Region', 'Secretaire General', 'Directeur'],
            'encadrement' => ['Chef de service'],
            'cabinet' => ['Directeur de cabinet', 'Conseiller', 'Charge de mission', 'Inspecteur'],
            'technique' => ['Attache administratif', 'Assistant', 'Consultant', 'Expert', 'Secretaire', 'Technicien'],
            'appui' => ['Chauffeur', 'Gardien', 'Magasinier'],
        ];
        foreach ($categories as $categorie => $libelles) {
            DB::table('fonctions')->whereIn('libelle', $libelles)->whereNull('categorie')->update(['categorie' => $categorie]);
        }

        // « Assistant » devient « Assistant(e) » (même identifiant : les agents déjà rattachés ne bougent pas).
        if (! DB::table('fonctions')->where('libelle', 'Assistant(e)')->exists()) {
            DB::table('fonctions')->where('libelle', 'Assistant')->update(['libelle' => 'Assistant(e)']);
        }

        // 5. Fonctions de l'organigramme (annexe : cabinet, personnels techniques et d'appui). Doublons ignorés.
        $nouvelles = [
            'direction' => ['Personne responsable des marchés publics'],
            'cabinet' => ['Conseiller technique', 'Chef de protocole', 'Chef du secrétariat particulier'],
            'technique' => [
                'Comptable', 'Rédacteur',
                'Conducteur d’engin', 'Conducteur de travaux 2e catégorie',
                'Chef de chantier 2e catégorie', 'Chef de chantier 3e catégorie', 'Chef d’équipe (OP2B)',
                'Ouvrier professionnel', 'Ouvrier qualifié', 'Ouvrier ordinaire (OS2)', 'Ouvrier débutant (OS1)',
            ],
            'appui' => [
                'Plombier', 'Maçon', 'Charpentier', 'Planton', 'Fille de salle', 'Chauffeur dépanneur',
                'Responsable comptoir', 'Responsable de barrière de pluie',
            ],
        ];
        foreach ($nouvelles as $categorie => $libelles) {
            foreach ($libelles as $libelle) {
                // La colonne est insensible à la casse et aux accents : « Chargé de mission » = « Charge de mission ».
                if (! DB::table('fonctions')->where('libelle', $libelle)->exists()) {
                    DB::table('fonctions')->insert(['libelle' => $libelle, 'categorie' => $categorie, 'actif' => 1]);
                }
            }
        }

        // 6. Services de l'organigramme absents de la base (rattachés à leur direction, repérée par son code).
        $services = [
            'DDE' => [
                ['DDE-PPP', 'Service du développement du partenariat public et privé'],
                ['DDE-ECO', 'Service des affaires économiques'],
                ['DDE-ENV', 'Service de l’environnement et de l’aménagement du territoire'],
                ['DDE-INV', 'Service des investissements'],
                ['DDE-COOP', 'Service de la promotion de la coopération décentralisée'],
                ['DDE-TRAV', 'Service des travaux et infrastructures'],
            ],
            'DAF' => [
                ['DAF-PB', 'Service de la programmation budgétaire'],
                ['DAF-FL', 'Service des finances locales'],
                ['DAF-LOG', 'Service de la logistique'],
            ],
            'DAS' => [
                ['DAS-CULT', 'Service des affaires culturelles'],
                ['DAS-JS', 'Service de la promotion de la jeunesse et du sport'],
                ['DAS-GENRE', 'Service de la promotion du genre'],
                ['DAS-INCL', 'Service de l’inclusion sociale'],
            ],
        ];
        foreach ($services as $codeDirection => $liste) {
            $direction = DB::table('structures')->where('code', $codeDirection)->first();
            if (! $direction) {
                continue; // base sans les données de démonstration : rien à compléter
            }
            foreach ($liste as [$code, $nom]) {
                $existe = DB::table('structures')->where('code', $code)->orWhere('nom', $nom)->exists();
                if (! $existe) {
                    DB::table('structures')->insert([
                        'nom' => $nom, 'code' => $code, 'type' => 'service',
                        'parent_id' => $direction->id, 'rattachement' => $direction->rattachement,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES_RATTACHEMENT as $table) {
            if (DB::table($table)->where('rattachement', 'sg_region')->exists()) {
                throw new RuntimeException("Annulation impossible : des lignes de {$table} utilisent « sg_region ». Changez-les d'abord.");
            }
        }
        foreach (self::TABLES_RATTACHEMENT as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY rattachement ENUM('sg','region') NOT NULL");
        }
        if (Schema::hasColumn('structures', 'type_precision')) {
            DB::statement('ALTER TABLE structures DROP COLUMN type_precision');
        }
        if (Schema::hasColumn('fonctions', 'categorie')) {
            DB::statement('ALTER TABLE fonctions DROP FOREIGN KEY fk_fonctions_structure');
            DB::statement('ALTER TABLE fonctions DROP INDEX ix_fonctions_structure, DROP COLUMN categorie, DROP COLUMN structure_id, DROP COLUMN rattachement, DROP COLUMN actif, DROP COLUMN updated_at');
        }
        // Les fonctions et services ajoutés sont conservés : des agents peuvent déjà y être rattachés.
    }
};
