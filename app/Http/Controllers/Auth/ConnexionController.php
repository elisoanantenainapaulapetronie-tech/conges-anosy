<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ConnexionController extends Controller
{
    public function formulaire()
    {
        return view('auth.connexion');
    }

    public function connecter(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'identifiant' => ['required', 'string', 'max:30'],
            'mot_de_passe' => ['required', 'string'],
        ], [
            'identifiant.required' => 'Saisissez votre identifiant.',
            'mot_de_passe.required' => 'Saisissez votre mot de passe.',
        ]);

        // L'agent saisit son matricule ou, à défaut, l'identifiant remis par la RH.
        $agent = Agent::trouverPourConnexion($donnees['identifiant']);
        $compte = $agent?->compte;
        $message = 'Identifiant ou mot de passe incorrect.'; // même message dans tous les cas d'échec

        if ($compte && $compte->estBloque()) {
            throw ValidationException::withMessages([
                'identifiant' => 'Compte bloqué temporairement. Réessayez dans quelques minutes ou utilisez « Mot de passe oublié ».',
            ]);
        }

        $ok = $compte && $compte->estActif() && $agent->situation === 'actif'
            && Hash::check($donnees['mot_de_passe'], (string) $compte->mot_de_passe_hash);

        if (! $ok) {
            $compte?->enregistrerEchec();
            AuditLog::create([
                'auteur_id' => $agent?->id, 'action' => 'connexion_echec', 'objet_type' => 'compte',
                'objet_id' => $compte?->id, 'refuse' => true, 'adresse_ip' => $request->ip(),
            ]);
            throw ValidationException::withMessages(['identifiant' => $message]);
        }

        $compte->enregistrerSucces();
        Auth::login($compte);
        $request->session()->regenerate();

        AuditLog::create([
            'auteur_id' => $agent->id, 'action' => 'connexion', 'objet_type' => 'compte',
            'objet_id' => $compte->id, 'adresse_ip' => $request->ip(),
        ]);

        return redirect()->intended(route('accueil'));
    }

    public function deconnecter(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion')->with('statut', 'Vous êtes déconnecté.');
    }
}
