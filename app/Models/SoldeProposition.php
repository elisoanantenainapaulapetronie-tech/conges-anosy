<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Table « soldes_propositions ». */
class SoldeProposition extends Model
{
    protected $table = 'soldes_propositions';
    protected $fillable = ['agent_id', 'annee', 'nature', 'jours', 'motif', 'propose_par', 'statut', 'controle_par', 'controle_le', 'commentaire_controle'];

    protected function casts(): array
    {
        return [
            'jours' => 'decimal:2',
            'controle_le' => 'datetime',
        ];
    }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }
    public function proposePar(): BelongsTo { return $this->belongsTo(Agent::class, 'propose_par'); }
    public function controlePar(): BelongsTo { return $this->belongsTo(Agent::class, 'controle_par'); }
    public function mouvement(): HasOne { return $this->hasOne(SoldeMouvement::class, 'proposition_id'); }
    public function piecesJointes(): HasMany { return $this->hasMany(PieceJointe::class, 'proposition_id'); }

}
