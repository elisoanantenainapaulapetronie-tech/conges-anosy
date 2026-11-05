<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « missions ». */
class Mission extends Model
{
    protected $table = 'missions';
    protected $fillable = ['demande_id', 'objet', 'destination', 'objectifs', 'moyen_transport', 'frais_estimes', 'nature', 'numero_ordre', 'statut_cloture', 'rapport_depose_le', 'rapport_chemin'];

    protected function casts(): array
    {
        return [
            'frais_estimes' => 'decimal:2',
            'rapport_depose_le' => 'datetime',
        ];
    }

    public function demande(): BelongsTo { return $this->belongsTo(Demande::class, 'demande_id'); }
}
