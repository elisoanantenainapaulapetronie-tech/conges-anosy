<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Table « jours_feries ». */
class JourFerie extends Model
{
    protected $table = 'jours_feries';
    const UPDATED_AT = null;
    protected $fillable = ['date_ferie', 'libelle'];

    protected function casts(): array
    {
        return [
            'date_ferie' => 'date',
        ];
    }

}
