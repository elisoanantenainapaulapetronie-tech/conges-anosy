<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ActivationController extends Controller
{
    public function formulaire()
    {
        return view('auth.activation');
    }

    public function activer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'identifiant' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'max:20'],
            'mot_de_passe' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'identifiant.required' => 'Saisissez votre identifiant.',
            'code.required' => 'Saisissez le code d’activation remis par la RH.',
            'mot_de_passe.required' => 'Choisissez un mot de passe.',
            'mot_de_passe.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        // Limite les essais de code par identifiant (5 essais ratés, puis 15 minutes d'attente).
        $cle = 'activation:'.Str::lower(trim($donnees['identifiant']));
        if (RateLimiter::tooManyAttempts($cle, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Trop d’essais. Réessayez dans quelques minutes ou contactez la RH.',
            ]);
        }

        $compte = Agent::trouverPourConnexion($donnees['identifiant'])?->compte;

        if (! $compte || ! $compte->codeActivationValide(strtoupper(trim($donnees['code'])))) {
            RateLimiter::hit($cle, 900);
            throw ValidationException::withMessages([
                'code' => 'Identifiant ou code d’activation invalide ou expiré. Contactez la RH.',
            ]);
        }

        RateLimiter::clear($cle);
        $compte->activer($donnees['mot_de_passe']);

        AuditLog::create([
            'auteur_id' => $compte->agent_id, 'action' => 'compte_active', 'objet_type' => 'compte',
            'objet_id' => $compte->id, 'adresse_ip' => $request->ip(),
        ]);

        return redirect()->route('connexion')->with('statut', 'Compte activé. Vous pouvez vous connecter.');
    }
}
