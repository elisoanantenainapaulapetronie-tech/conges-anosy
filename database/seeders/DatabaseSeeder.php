<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Les donnees de reference (roles, structures, types de demande, parametres,
     * jours feries) sont deja inserees par la migration.
     * Les donnees de demonstration se chargent a part :
     *     php artisan db:seed --class=DonneesDemonstrationSeeder
     */
    public function run(): void
    {
        //
    }
}
