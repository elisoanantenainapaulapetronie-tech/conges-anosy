<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\EcritureSeule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Table « demande_historique ». Écriture seule : ni modification ni suppression (doublé par des déclencheurs SQL). */
class DemandeHistorique extends Model
{
    use EcritureSeule;

    protected $table = 'demande_historique';
    const UPDATED_AT = null;
    protected $fillable = ['demande_id', 'ancien_statut', 'nouveau_statut', 'auteur_id', 'commentaire'];

    public function demande(): BelongsTo { return $this->belongsTo(Demande::class, 'demande_id'); }
    public function auteur(): BelongsTo { return $this->belongsTo(Agent::class, 'auteur_id'); }
}
