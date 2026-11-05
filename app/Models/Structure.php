<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table « structures ». */
class Structure extends Model
{
    protected $table = 'structures';
    protected $fillable = ['nom', 'code', 'type', 'parent_id', 'rattachement', 'actif'];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }

    public function parent(): BelongsTo { return $this->belongsTo(Structure::class, 'parent_id'); }

    public function enfants(): HasMany { return $this->hasMany(Structure::class, 'parent_id'); }
    public function agents(): HasMany { return $this->hasMany(Agent::class, 'structure_id'); }

}
