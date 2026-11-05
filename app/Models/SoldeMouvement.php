<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\EcritureSeule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « soldes_mouvements ». Écriture seule : ni modification ni suppression (doublé par des déclencheurs SQL). */
class SoldeMouvement extends Model
{
    use EcritureSeule;

    protected $table = 'soldes_mouvements';
    const UPDATED_AT = null;
    protected $fillable = ['agent_id', 'annee', 'type_mouvement', 'delta_disponible', 'delta_reserve', 'delta_consomme', 'demande_id', 'proposition_id', 'motif', 'auteur_id'];

    protected function casts(): array
    {
        return [
            'delta_disponible' => 'decimal:2',
            'delta_reserve' => 'decimal:2',
            'delta_consomme' => 'decimal:2',
        ];
    }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }
    public function demande(): BelongsTo { return $this->belongsTo(Demande::class, 'demande_id'); }
    public function proposition(): BelongsTo { return $this->belongsTo(SoldeProposition::class, 'proposition_id'); }
    public function auteur(): BelongsTo { return $this->belongsTo(Agent::class, 'auteur_id'); }
}
