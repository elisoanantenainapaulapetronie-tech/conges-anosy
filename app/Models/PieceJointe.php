<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table « pieces_jointes ». */
class PieceJointe extends Model
{
    protected $table = 'pieces_jointes';
    const UPDATED_AT = null;
    protected $fillable = ['demande_id', 'avis_id', 'proposition_id', 'nom_original', 'chemin', 'mime', 'taille_octets', 'medicale', 'depose_par'];

    protected function casts(): array
    {
        return [
            'medicale' => 'boolean',
        ];
    }

    public function demande(): BelongsTo { return $this->belongsTo(Demande::class, 'demande_id'); }
    public function avis(): BelongsTo { return $this->belongsTo(AvisPrealable::class, 'avis_id'); }
    public function proposition(): BelongsTo { return $this->belongsTo(SoldeProposition::class, 'proposition_id'); }
    public function deposePar(): BelongsTo { return $this->belongsTo(Agent::class, 'depose_par'); }
    public function consultations(): HasMany { return $this->hasMany(ConsultationPiece::class, 'piece_id'); }

}
