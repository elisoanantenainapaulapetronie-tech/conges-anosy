<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « demande_etapes ». */
class DemandeEtape extends Model
{
    protected $table = 'demande_etapes';
    protected $fillable = ['demande_id', 'ordre', 'role_etape', 'acteur_id', 'agent_effectif_id', 'delegation_id', 'statut', 'resultat', 'commentaire', 'echeance_le', 'rappel_envoye_le', 'accomplie_le'];

    protected function casts(): array
    {
        return [
            'echeance_le' => 'datetime',
            'rappel_envoye_le' => 'datetime',
            'accomplie_le' => 'datetime',
        ];
    }

    public function demande(): BelongsTo { return $this->belongsTo(Demande::class, 'demande_id'); }
    public function acteur(): BelongsTo { return $this->belongsTo(Agent::class, 'acteur_id'); }
    public function agentEffectif(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_effectif_id'); }
    public function delegation(): BelongsTo { return $this->belongsTo(Delegation::class, 'delegation_id'); }
}
