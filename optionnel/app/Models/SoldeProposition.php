<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Proposition de mouvement de solde (table soldes_propositions). À COPIER SEULEMENT SI app/Models/SoldeProposition.php
 * n'existe pas déjà dans votre projet (SoldePolicy et DroitsServiceProvider s'y réfèrent).
 */
class SoldeProposition extends Model
{
    protected $table = 'soldes_propositions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['jours' => 'decimal:2', 'controle_le' => 'datetime'];
    }
}
