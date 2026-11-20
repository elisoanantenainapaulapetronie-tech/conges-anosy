<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demandes', function (Blueprint $table) {
            $table->string('reference', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Impossible tant qu'il existe des brouillons sans référence.
        Schema::table('demandes', function (Blueprint $table) {
            $table->string('reference', 30)->nullable(false)->change();
        });
    }
};