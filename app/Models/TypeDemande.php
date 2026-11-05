<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table « types_demande ». */
class TypeDemande extends Model
{
    protected $table = 'types_demande';
    protected $fillable = ['code', 'libelle', 'famille', 'avis_prealable', 'controle_rh_obligatoire', 'decompte_solde', 'circuit', 'plafond_jours', 'pieces_requises', 'actif'];

    protected function casts(): array
    {
        return [
            'avis_prealable' => 'boolean',
            'controle_rh_obligatoire' => 'boolean',
            'plafond_jours' => 'decimal:1',
            'pieces_requises' => 'array',
            'actif' => 'boolean',
        ];
    }

    public function demandes(): HasMany { return $this->hasMany(Demande::class, 'type_demande_id'); }

}
