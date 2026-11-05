<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « envois_notifications ». */
class EnvoiNotification extends Model
{
    protected $table = 'envois_notifications';
    const UPDATED_AT = null;
    protected $fillable = ['notification_id', 'canal', 'adresse', 'statut', 'envoye_le', 'erreur'];

    protected function casts(): array
    {
        return [
            'envoye_le' => 'datetime',
        ];
    }

    public function notification(): BelongsTo { return $this->belongsTo(Notification::class, 'notification_id'); }
}
