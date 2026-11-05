<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/** Fiche d'un agent. Elle existe sans compte ; un agent n'est jamais supprimé (situation « sorti »). */
class Agent extends Model
{
    protected $table = 'agents';

    protected $fillable = [
        'identifiant', 'matricule', 'nom', 'prenoms', 'sexe', 'date_naissance', 'telephone', 'email',
        'fonction_id', 'grade_categorie', 'structure_id', 'responsable_id', 'rattachement',
        'date_entree', 'date_sortie', 'situation',
    ];

    protected function casts(): array
    {
        return ['date_naissance' => 'date', 'date_entree' => 'date', 'date_sortie' => 'date'];
    }

    /**
     * Identifiant de connexion : le matricule s'il existe, sinon un identifiant ANO-00045 généré.
     * Créé en deux temps car le numéro vient de l'id de la ligne.
     */
    protected static function booted(): void
    {
        static::creating(function (Agent $agent) {
            if (blank($agent->identifiant)) {
                $agent->identifiant = filled($agent->matricule) ? $agent->matricule : 'TMP-'.Str::upper(Str::random(20));
            }
        });
        static::created(function (Agent $agent) {
            if (str_starts_with($agent->identifiant, 'TMP-')) {
                $agent->identifiant = 'ANO-'.str_pad((string) $agent->id, 5, '0', STR_PAD_LEFT);
                $agent->saveQuietly();
            }
        });
    }

    /**
     * Retrouve l'agent à partir de ce qu'il a saisi : identifiant OU matricule.
     * Renvoie null si rien ne correspond ou si la saisie est ambiguë (deux agents).
     */
    public static function trouverPourConnexion(string $saisie): ?Agent
    {
        $saisie = trim($saisie);
        if ($saisie === '') {
            return null;
        }
        $trouves = static::with('compte')
            ->where(fn (Builder $q) => $q->where('identifiant', $saisie)->orWhere('matricule', $saisie))
            ->limit(2)->get();

        return $trouves->count() === 1 ? $trouves->first() : null;
    }

    // --- Relations ---------------------------------------------------------
    public function fonction(): BelongsTo { return $this->belongsTo(Fonction::class, 'fonction_id'); }
    public function structure(): BelongsTo { return $this->belongsTo(Structure::class, 'structure_id'); }
    public function responsable(): BelongsTo { return $this->belongsTo(Agent::class, 'responsable_id'); }
    public function equipe(): HasMany { return $this->hasMany(Agent::class, 'responsable_id'); }
    public function compte(): HasOne { return $this->hasOne(Compte::class, 'agent_id'); }
    public function habilitations(): HasMany { return $this->hasMany(Habilitation::class, 'agent_id'); }
    public function affectations(): HasMany { return $this->hasMany(AffectationHistorique::class, 'agent_id'); }
    public function demandes(): HasMany { return $this->hasMany(Demande::class, 'agent_id'); }
    public function avisPrealables(): HasMany { return $this->hasMany(AvisPrealable::class, 'agent_id'); }
    public function mouvements(): HasMany { return $this->hasMany(SoldeMouvement::class, 'agent_id'); }
    public function notifications(): HasMany { return $this->hasMany(Notification::class, 'destinataire_id'); }
    public function indisponibilites(): HasMany { return $this->hasMany(Indisponibilite::class, 'agent_id'); }

    // --- Rôles (habilitations en cours) -------------------------------------
    /** Habilitations valables aujourd'hui (début passé, fin vide ou à venir). */
    public function habilitationsActives(): HasMany
    {
        return $this->habilitations()
            ->whereDate('date_debut', '<=', today())
            ->where(fn (Builder $q) => $q->whereNull('date_fin')->orWhereDate('date_fin', '>=', today()));
    }

    /** Codes des rôles actifs, sans le rôle Agent que tout le monde a. */
    public function codesRoles(): array
    {
        return $this->habilitationsActives()
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->pluck('roles.code')->unique()->values()->all();
    }

    /** $agent->aLeRole('rh_responsable') ou aLeRole('rh_gestionnaire', 'rh_responsable'). Le rôle « agent » est toujours vrai. */
    public function aLeRole(string ...$codes): bool
    {
        if (in_array('agent', $codes, true)) {
            return true;
        }
        return count(array_intersect($codes, $this->codesRoles())) > 0;
    }

    public function estRh(): bool { return $this->aLeRole('rh_gestionnaire', 'rh_responsable'); }

    // --- Confort -------------------------------------------------------------
    public function getNomCompletAttribute(): string
    {
        return trim($this->prenoms.' '.mb_strtoupper($this->nom));
    }

    public function scopeActifs(Builder $q): Builder { return $q->where('situation', 'actif'); }
}
