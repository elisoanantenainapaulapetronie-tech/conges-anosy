<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « documents ». */
class Document extends Model
{
    protected $table = 'documents';
    public $timestamps = false;
    protected $fillable = ['type_document', 'numero', 'code_verification', 'demande_id', 'chemin', 'genere_le', 'genere_par'];

    protected function casts(): array
    {
        return [
            'genere_le' => 'datetime',
        ];
    }

    public function demande(): BelongsTo { return $this->belongsTo(Demande::class, 'demande_id'); }
    public function generePar(): BelongsTo { return $this->belongsTo(Agent::class, 'genere_par'); }
}
