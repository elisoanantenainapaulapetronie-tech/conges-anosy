<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\EcritureSeule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « consultations_pieces ». Écriture seule : ni modification ni suppression (doublé par des déclencheurs SQL). */
class ConsultationPiece extends Model
{
    use EcritureSeule;

    protected $table = 'consultations_pieces';
    public $timestamps = false;
    protected $fillable = ['piece_id', 'agent_id', 'consulte_le', 'adresse_ip'];

    protected function casts(): array
    {
        return [
            'consulte_le' => 'datetime',
        ];
    }

    public function piece(): BelongsTo { return $this->belongsTo(PieceJointe::class, 'piece_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }
}
