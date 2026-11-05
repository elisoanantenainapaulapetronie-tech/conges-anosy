<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Table « demandes ». */
class Demande extends Model
{
    protected $table = 'demandes';
    protected $fillable = ['reference', 'avis_id', 'agent_id', 'type_demande_id', 'chef_projet_id', 'date_debut', 'date_fin', 'heure_debut', 'heure_fin', 'duree_jours', 'motif', 'contact_pendant_absence', 'circuit', 'statut', 'urgence', 'regularisation', 'motif_regularisation', 'derogation', 'conformite_rh', 'conformite_commentaire', 'version', 'soumise_le', 'decidee_le', 'enregistree_le', 'reponse_attendue_avant', 'annulation_demandee_le', 'motif_annulation'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'duree_jours' => 'decimal:1',
            'urgence' => 'boolean',
            'regularisation' => 'boolean',
            'derogation' => 'boolean',
            'soumise_le' => 'datetime',
            'decidee_le' => 'datetime',
            'enregistree_le' => 'datetime',
            'reponse_attendue_avant' => 'datetime',
            'annulation_demandee_le' => 'datetime',
        ];
    }

    public function avis(): BelongsTo { return $this->belongsTo(AvisPrealable::class, 'avis_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }
    public function typeDemande(): BelongsTo { return $this->belongsTo(TypeDemande::class, 'type_demande_id'); }
    public function chefProjet(): BelongsTo { return $this->belongsTo(Agent::class, 'chef_projet_id'); }
    public function etapes(): HasMany { return $this->hasMany(DemandeEtape::class, 'demande_id')->orderBy('ordre'); }
    public function historique(): HasMany { return $this->hasMany(DemandeHistorique::class, 'demande_id')->orderBy('created_at'); }
    public function mission(): HasOne { return $this->hasOne(Mission::class, 'demande_id'); }
    public function piecesJointes(): HasMany { return $this->hasMany(PieceJointe::class, 'demande_id'); }
    public function documents(): HasMany { return $this->hasMany(Document::class, 'demande_id'); }
    public function mouvements(): HasMany { return $this->hasMany(SoldeMouvement::class, 'demande_id'); }

}
