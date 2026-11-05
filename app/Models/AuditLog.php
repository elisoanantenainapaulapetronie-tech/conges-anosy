<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\EcritureSeule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « audit_logs ». Écriture seule : ni modification ni suppression (doublé par des déclencheurs SQL). */
class AuditLog extends Model
{
    use EcritureSeule;

    protected $table = 'audit_logs';
    const UPDATED_AT = null;
    protected $fillable = ['auteur_id', 'action', 'objet_type', 'objet_id', 'valeur_avant', 'valeur_apres', 'refuse', 'adresse_ip'];

    protected function casts(): array
    {
        return [
            'valeur_avant' => 'array',
            'valeur_apres' => 'array',
            'refuse' => 'boolean',
        ];
    }

    public function auteur(): BelongsTo { return $this->belongsTo(Agent::class, 'auteur_id'); }
}
