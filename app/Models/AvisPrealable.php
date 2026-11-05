<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Table « avis_prealables ». */
class AvisPrealable extends Model
{
    protected $table = 'avis_prealables';
    protected $fillable = ['reference', 'agent_id', 'type_demande_id', 'responsable_id', 'avis_donne_par', 'date_debut', 'date_fin', 'duree_jours', 'motif', 'continuite_service', 'statut', 'sens_avis', 'motif_avis', 'autres_dates_proposees', 'envoye_le', 'avis_rendu_le', 'autorisation_emise_le', 'autorisation_expire_le', 'autorisation_utilisee_le'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'duree_jours' => 'decimal:1',
            'envoye_le' => 'datetime',
            'avis_rendu_le' => 'datetime',
            'autorisation_emise_le' => 'datetime',
            'autorisation_expire_le' => 'datetime',
            'autorisation_utilisee_le' => 'datetime',
        ];
    }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }
    public function typeDemande(): BelongsTo { return $this->belongsTo(TypeDemande::class, 'type_demande_id'); }
    public function responsable(): BelongsTo { return $this->belongsTo(Agent::class, 'responsable_id'); }
    public function avisDonnePar(): BelongsTo { return $this->belongsTo(Agent::class, 'avis_donne_par'); }
    public function demande(): HasOne { return $this->hasOne(Demande::class, 'avis_id'); }
    public function piecesJointes(): HasMany { return $this->hasMany(PieceJointe::class, 'avis_id'); }

}
