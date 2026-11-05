<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table « delegations ». */
class Delegation extends Model
{
    protected $table = 'delegations';
    protected $fillable = ['delegant_id', 'delegataire_id', 'date_debut', 'date_fin', 'statut', 'motif'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function delegant(): BelongsTo { return $this->belongsTo(Agent::class, 'delegant_id'); }
    public function delegataire(): BelongsTo { return $this->belongsTo(Agent::class, 'delegataire_id'); }
    public function etapes(): HasMany { return $this->hasMany(DemandeEtape::class, 'delegation_id'); }

}
