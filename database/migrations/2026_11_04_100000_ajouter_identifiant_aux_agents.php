<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Identifiant de connexion : toujours présent, unique.
 * - le matricule devient facultatif (certains agents n'en ont pas) ;
 * - « identifiant » = matricule quand il existe, sinon ANO-00045 généré par le système.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE agents ADD COLUMN identifiant VARCHAR(30) NULL AFTER id');
        DB::statement('UPDATE agents SET identifiant = matricule');
        DB::statement('ALTER TABLE agents MODIFY identifiant VARCHAR(30) NOT NULL');
        DB::statement('ALTER TABLE agents ADD UNIQUE KEY uq_agents_identifiant (identifiant)');
        DB::statement("ALTER TABLE agents MODIFY matricule VARCHAR(30) NULL COMMENT 'Numero officiel ; facultatif'");
    }

    public function down(): void
    {
        DB::statement('UPDATE agents SET matricule = identifiant WHERE matricule IS NULL');
        DB::statement('ALTER TABLE agents MODIFY matricule VARCHAR(30) NOT NULL');
        DB::statement('ALTER TABLE agents DROP INDEX uq_agents_identifiant');
        DB::statement('ALTER TABLE agents DROP COLUMN identifiant');
    }
};
