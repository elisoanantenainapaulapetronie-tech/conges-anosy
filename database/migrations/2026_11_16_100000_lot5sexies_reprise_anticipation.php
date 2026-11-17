<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot 5 sexies.
 *  1. soldes_propositions.nature : nouvelle valeur « reprise_historique » (jours déjà pris avant la mise en service).
 *     Le mouvement écrit au registre reste de type « consommation » : aucune modification du registre.
 *  2. Paramètre anticipation_max_jours (0 = aucune anticipation, valeur de la V1) à la place de anticipation_autorisee (0 ou 1).
 *     Le comportement ne change pas tant que la valeur est 0 ; toute autre valeur est réservée à la V2 et sans effet en V1.
 *     L'ancien paramètre n'est supprimé que s'il valait 0 ; sinon il est conservé et doit être examiné à la main.
 * Aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE soldes_propositions MODIFY nature
            ENUM('solde_initial','correction','report','indemnisation','perte','reprise_historique') NOT NULL");

        DB::table('parametres')->insertOrIgnore([
            'cle' => 'anticipation_max_jours', 'valeur' => '0',
            'description' => '0 = aucune anticipation (V1). Une valeur superieure est reservee a la V2 et sans effet en V1',
        ]);

        if ((string) DB::table('parametres')->where('cle', 'anticipation_autorisee')->value('valeur') === '0') {
            DB::table('parametres')->where('cle', 'anticipation_autorisee')->delete();
        }
    }

    public function down(): void
    {
        if (DB::table('soldes_propositions')->where('nature', 'reprise_historique')->exists()) {
            throw new RuntimeException('Des reprises de l’historique existent : retour en arrière refusé pour ne rien perdre de l’historique.');
        }
        DB::statement("ALTER TABLE soldes_propositions MODIFY nature
            ENUM('solde_initial','correction','report','indemnisation','perte') NOT NULL");

        DB::table('parametres')->insertOrIgnore([
            'cle' => 'anticipation_autorisee', 'valeur' => '0',
            'description' => '1 = les jours du mois a venir peuvent etre pris avant d etre acquis ; 0 = jamais',
        ]);
        DB::table('parametres')->where('cle', 'anticipation_max_jours')->delete();
    }
};
