<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Hash;

/**
 * Compte de connexion d'un agent. Remplace la table « users » de Laravel.
 * Identifiant de connexion = matricule de l'agent ; mot de passe choisi par l'agent à l'activation.
 */
class Compte extends Authenticatable
{
    public const MAX_ECHECS = 5;
    public const MINUTES_BLOCAGE = 15;

    protected $table = 'comptes';

    protected $fillable = [
        'agent_id', 'mot_de_passe_hash', 'statut', 'code_activation_hash', 'code_activation_expire_le',
        'active_le', 'telephone_verifie_le', 'double_auth_active', 'double_auth_secret',
        'echecs_connexion', 'bloque_jusqua', 'derniere_connexion_le', 'langue',
    ];

    protected $hidden = ['mot_de_passe_hash', 'code_activation_hash', 'double_auth_secret'];

    protected function casts(): array
    {
        return [
            'code_activation_expire_le' => 'datetime',
            'active_le' => 'datetime',
            'telephone_verifie_le' => 'datetime',
            'bloque_jusqua' => 'datetime',
            'derniere_connexion_le' => 'datetime',
            'double_auth_active' => 'boolean',
        ];
    }

    public function agent(): BelongsTo { return $this->belongsTo(Agent::class, 'agent_id'); }

    // --- Contrat d'authentification Laravel ----------------------------------
    public function getAuthPassword(): ?string { return $this->mot_de_passe_hash; }
    public function getAuthPasswordName(): string { return 'mot_de_passe_hash'; }
    // Pas de colonne « se souvenir de moi » : on la désactive.
    public function getRememberToken(): ?string { return null; }
    public function setRememberToken($value): void {}
    public function getRememberTokenName(): string { return ''; }

    // --- État du compte ---------------------------------------------------------
    public function estActif(): bool { return $this->statut === 'actif'; }
    public function estBloque(): bool { return $this->bloque_jusqua !== null && $this->bloque_jusqua->isFuture(); }

    public function aLeRole(string ...$codes): bool { return $this->agent->aLeRole(...$codes); }

    /** Vérifie le code d'activation : présent, non expiré, correct. */
    public function codeActivationValide(string $code): bool
    {
        return $this->statut === 'en_attente_activation'
            && $this->code_activation_hash !== null
            && $this->code_activation_expire_le?->isFuture()
            && Hash::check($code, $this->code_activation_hash);
    }

    /** L'agent choisit son mot de passe : le compte devient actif, le code est détruit. */
    public function activer(string $motDePasse): void
    {
        $this->forceFill([
            'mot_de_passe_hash' => Hash::make($motDePasse),
            'statut' => 'actif',
            'active_le' => now(),
            'code_activation_hash' => null,
            'code_activation_expire_le' => null,
            'echecs_connexion' => 0,
            'bloque_jusqua' => null,
        ])->save();
    }

    /** Génère un code d'activation à usage unique et renvoie le code en clair (à remettre à l'agent). */
    public function genererCodeActivation(int $jours = 5): string
    {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $this->forceFill([
            'code_activation_hash' => Hash::make($code),
            'code_activation_expire_le' => now()->addDays($jours),
        ])->save();
        return $code;
    }

    public function enregistrerEchec(): void
    {
        $echecs = $this->echecs_connexion + 1;
        $this->forceFill([
            'echecs_connexion' => $echecs,
            'bloque_jusqua' => $echecs >= self::MAX_ECHECS ? now()->addMinutes(self::MINUTES_BLOCAGE) : $this->bloque_jusqua,
        ])->save();
    }

    public function enregistrerSucces(): void
    {
        $this->forceFill(['echecs_connexion' => 0, 'bloque_jusqua' => null, 'derniere_connexion_le' => now()])->save();
    }
}
