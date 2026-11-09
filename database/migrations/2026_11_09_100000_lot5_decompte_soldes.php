<?php

use App\Services\JoursFeriesService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 5 : décompte des jours et registre des soldes.
 *  1. soldes_propositions.reference_piece : référence du document officiel qui justifie le mouvement
 *     (décision de report, registre des congés...). Le téléversement du fichier viendra avec les pièces jointes.
 *  2. Jours fériés mobiles (lundi de Pâques, Ascension, lundi de Pentecôte) pour 2026 à 2028 : le décompte les ignore.
 * Aucune donnée existante n'est modifiée ni supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('soldes_propositions', 'reference_piece')) {
            DB::statement("ALTER TABLE soldes_propositions
                ADD COLUMN reference_piece VARCHAR(190) NULL COMMENT 'Document officiel justifiant le mouvement (decision, registre...)' AFTER motif");
        }

        if (class_exists(JoursFeriesService::class)) {
            foreach ([2026, 2027, 2028] as $annee) {
                JoursFeriesService::remplir($annee);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('soldes_propositions', 'reference_piece')) {
            DB::statement('ALTER TABLE soldes_propositions DROP COLUMN reference_piece');
        }
        // Les jours fériés ajoutés sont conservés : des décomptes peuvent déjà s'en servir.
    }
};
