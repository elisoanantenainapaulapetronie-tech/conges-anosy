<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « habilitations ». */
class Habilitation extends Model
{
    protected $table = 'habilitations';
    protected $fillable = ['agent_id', 'role_id', 'structure_id', 'date_debut', 'date_fin', 'acte_reference', 'attribuee_par'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }
    public function role(): BelongsTo { return $this->belongsTo(Role::class, 'role_id'); }
    public function structure(): BelongsTo { return $this->belongsTo(Structure::class, 'structure_id'); }
    public function attribueePar(): BelongsTo { return $this->belongsTo(Agent::class, 'attribuee_par'); }
}
