<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table « roles ». */
class Role extends Model
{
    protected $table = 'roles';
    public $timestamps = false;
    protected $fillable = ['code', 'libelle', 'description'];

    public function habilitations(): HasMany { return $this->hasMany(Habilitation::class, 'role_id'); }

}
