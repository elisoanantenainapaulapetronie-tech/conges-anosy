<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « parametres ». */
class Parametre extends Model
{
    protected $table = 'parametres';
    protected $primaryKey = 'cle';
    public $incrementing = false;
    protected $keyType = 'string';
    const CREATED_AT = null;
    protected $fillable = ['cle', 'valeur', 'description', 'modifie_par'];

    public function modifiePar(): BelongsTo { return $this->belongsTo(Agent::class, 'modifie_par'); }
}
