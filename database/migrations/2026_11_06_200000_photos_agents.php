<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Photo de profil : deux colonnes sur agents et un paramètre.
 * Le fichier lui-même est stocké hors de public/ (storage/app/private/photos-agents/{id}.jpg).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('agents', 'photo_chemin')) {
            DB::statement("ALTER TABLE agents
                ADD COLUMN photo_chemin VARCHAR(255) NULL COMMENT 'Chemin prive de la photo de profil' AFTER email,
                ADD COLUMN photo_mise_a_jour_le DATETIME NULL COMMENT 'Sert a rafraichir le cache du navigateur' AFTER photo_chemin");
        }

        DB::table('parametres')->insertOrIgnore([
            'cle' => 'photo_modifiable_par_agent',
            'valeur' => '1',
            'description' => '1 = chaque agent change sa photo ; 0 = photo reservee a la RH',
        ]);
    }

    public function down(): void
    {
        DB::table('parametres')->where('cle', 'photo_modifiable_par_agent')->delete();

        if (Schema::hasColumn('agents', 'photo_chemin')) {
            DB::statement('ALTER TABLE agents DROP COLUMN photo_mise_a_jour_le, DROP COLUMN photo_chemin');
        }
    }
};
