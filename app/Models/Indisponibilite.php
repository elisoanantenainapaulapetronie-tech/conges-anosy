<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « indisponibilites ». */
class Indisponibilite extends Model
{
    protected $table = 'indisponibilites';
    protected $fillable = ['agent_id', 'nature', 'date_debut', 'date_fin', 'motif', 'delegation_id', 'duree_jours', 'notifiee_le', 'annulee_le'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'duree_jours' => 'decimal:1',
            'notifiee_le' => 'datetime',
            'annulee_le' => 'datetime',
        ];
    }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }
    public function delegation(): BelongsTo { return $this->belongsTo(Delegation::class, 'delegation_id'); }
}
