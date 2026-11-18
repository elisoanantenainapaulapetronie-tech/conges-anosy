<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot 6.
 * Indicateur « valeur provisoire, à confirmer par la RH » sur les paramètres : colonne parametres.a_confirmer (0 par défaut).
 * Aucun paramètre n'est modifié ; seuls ceux que le dossier désigne comme provisoires reçoivent l'indicateur
 * (s'ils existent). Les autres lots y ajouteront les leurs (préavis, plafonds, seuils de reliquat) à leur création.
 */
return new class extends Migration
{
    private const PROVISOIRES = ['duree_max_delegation_jours', 'alerte_solde_bas_jours'];

    public function up(): void
    {
        if (! Schema::hasColumn('parametres', 'a_confirmer')) {
            Schema::table('parametres', function (Blueprint $table) {
                $table->boolean('a_confirmer')->default(false);
            });
        }
        DB::table('parametres')->whereIn('cle', self::PROVISOIRES)->update(['a_confirmer' => 1]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('parametres', 'a_confirmer')) {
            Schema::table('parametres', fn (Blueprint $table) => $table->dropColumn('a_confirmer'));
        }
    }
};
