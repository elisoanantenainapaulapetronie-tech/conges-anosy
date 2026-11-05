<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « reinitialisations_mot_de_passe ». */
class ReinitialisationMotDePasse extends Model
{
    protected $table = 'reinitialisations_mot_de_passe';
    const UPDATED_AT = null;
    protected $fillable = ['compte_id', 'jeton_hash', 'demande_par', 'expire_le', 'utilise_le'];

    protected function casts(): array
    {
        return [
            'expire_le' => 'datetime',
            'utilise_le' => 'datetime',
        ];
    }

    public function compte(): BelongsTo { return $this->belongsTo(Compte::class, 'compte_id'); }
    public function demandePar(): BelongsTo { return $this->belongsTo(Agent::class, 'demande_par'); }
}
