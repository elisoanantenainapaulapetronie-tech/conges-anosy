<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table « fonctions ». */
class Fonction extends Model
{
    protected $table = 'fonctions';
    const UPDATED_AT = null;
    protected $fillable = ['libelle'];

    public function agents(): HasMany { return $this->hasMany(Agent::class, 'fonction_id'); }

}
