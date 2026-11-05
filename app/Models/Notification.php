<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Table « notifications ». */
class Notification extends Model
{
    protected $table = 'notifications';
    const UPDATED_AT = null;
    protected $fillable = ['destinataire_id', 'type_evenement', 'titre', 'message', 'importance', 'demande_id', 'avis_id', 'lue_le'];

    protected function casts(): array
    {
        return [
            'lue_le' => 'datetime',
        ];
    }

    public function destinataire(): BelongsTo { return $this->belongsTo(Agent::class, 'destinataire_id'); }
    public function demande(): BelongsTo { return $this->belongsTo(Demande::class, 'demande_id'); }
    public function avis(): BelongsTo { return $this->belongsTo(AvisPrealable::class, 'avis_id'); }
    public function envois(): HasMany { return $this->hasMany(EnvoiNotification::class, 'notification_id'); }

}
