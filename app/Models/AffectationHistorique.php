<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « affectations_historique ». */
class AffectationHistorique extends Model
{
    protected $table = 'affectations_historique';
    const UPDATED_AT = null;
    protected $fillable = ['agent_id', 'structure_id', 'responsable_id', 'rattachement', 'date_debut', 'date_fin', 'motif', 'modifie_par'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }
    public function structure(): BelongsTo { return $this->belongsTo(Structure::class, 'structure_id'); }
    public function responsable(): BelongsTo { return $this->belongsTo(Agent::class, 'responsable_id'); }
    public function modifiePar(): BelongsTo { return $this->belongsTo(Agent::class, 'modifie_par'); }
}
