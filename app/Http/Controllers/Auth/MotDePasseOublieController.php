<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\AuditLog;
use App\Models\Compte;
use App\Models\ReinitialisationMotDePasse;
use App\Services\EnvoiMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Mot de passe oublié, en deux temps :
 *  1. l'agent donne son identifiant ; s'il existe, un lien à usage unique (60 min) part par e-mail ou SMS ;
 *  2. le lien ouvre le formulaire du nouveau mot de passe.
 * La réponse de l'étape 1 est toujours la même : on ne révèle jamais si un identifiant existe.
 */
class MotDePasseOublieController extends Controller
{
    private const VALIDITE_MINUTES = 60;

    public function formulaire()
    {
        return view('auth.mot-de-passe-oublie');
    }

    public function envoyer(Request $request, EnvoiMessage $messages): RedirectResponse
    {
        $donnees = $request->validate(
            ['identifiant' => ['required', 'string', 'max:30']],
            ['identifiant.required' => 'Saisissez votre identifiant.'],
        );
        $saisie = trim($donnees['identifiant']);

        // 3 demandes par quart d'heure pour un même identifiant depuis une même adresse.
        $cle = 'mdp-oublie:'.Str::lower($saisie).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($cle, 3)) {
            throw ValidationException::withMessages([
                'identifiant' => 'Trop de demandes. Réessayez dans quelques minutes.',
            ]);
        }
        RateLimiter::hit($cle, 900);

        $agent = Agent::trouverPourConnexion($saisie);
        $compte = $agent?->compte;

        if ($compte && $compte->estActif() && $agent->situation === 'actif') {
            $jeton = $this->creerJeton($compte);
            $canal = $messages->envoyer(
                $agent,
                'Réinitialisation de votre mot de passe — Congés Anosy',
                'Vous avez demandé à changer votre mot de passe. Ce lien est valable '.self::VALIDITE_MINUTES.' minutes et ne sert qu’une fois. '
                .'Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.',
                route('reinitialisation', ['jeton' => $jeton]),
                'Choisir un nouveau mot de passe',
            );
            AuditLog::create([
                'auteur_id' => $agent->id, 'action' => 'mdp_oublie_demande', 'objet_type' => 'compte',
                'objet_id' => $compte->id, 'valeur_apres' => ['canal' => $canal ?? 'aucun'],
                'adresse_ip' => $request->ip(),
            ]);
        } else {
            AuditLog::create([
                'action' => 'mdp_oublie_inconnu', 'objet_type' => 'compte', 'refuse' => true,
                'valeur_apres' => ['saisie' => Str::limit($saisie, 30, '')], 'adresse_ip' => $request->ip(),
            ]);
        }

        return redirect()->route('mot-de-passe-oublie.envoye')->with('demande_envoyee', true);
    }

    public function confirmation()
    {
        if (! session('demande_envoyee')) {
            return redirect()->route('mot-de-passe-oublie');
        }
        return view('auth.mot-de-passe-oublie-envoye');
    }

    public function formulaireReinitialisation(string $jeton)
    {
        $reinit = $this->trouverValide($jeton);
        if (! $reinit) {
            return response()->view('auth.reinitialisation-invalide', [], 410);
        }
        return view('auth.reinitialisation', ['jeton' => $jeton, 'agent' => $reinit->compte->agent]);
    }

    public function appliquer(Request $request, string $jeton, EnvoiMessage $messages)
    {
        $donnees = $request->validate([
            'mot_de_passe' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'mot_de_passe.required' => 'Choisissez un mot de passe.',
            'mot_de_passe.confirmed' => 'Les deux mots de passe ne sont pas identiques.',
            'mot_de_passe.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ]);

        // Transaction + verrou : deux clics simultanés ne peuvent pas utiliser le lien deux fois.
        $agent = DB::transaction(function () use ($jeton, $donnees, $request) {
            $reinit = ReinitialisationMotDePasse::with('compte.agent')
                ->where('jeton_hash', hash('sha256', $jeton))
                ->whereNull('utilise_le')
                ->where('expire_le', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $reinit || ! $reinit->compte->estActif() || $reinit->compte->agent->situation !== 'actif') {
                return null;
            }

            $compte = $reinit->compte;

            if ($compte->mot_de_passe_hash && Hash::check($donnees['mot_de_passe'], $compte->mot_de_passe_hash)) {
                throw ValidationException::withMessages([
                    'mot_de_passe' => 'Choisissez un mot de passe différent de l’ancien.',
                ]);
            }

            $compte->forceFill([
                'mot_de_passe_hash' => Hash::make($donnees['mot_de_passe']),
                'echecs_connexion' => 0,
                'bloque_jusqua' => null,
            ])->save();

            $reinit->forceFill(['utilise_le' => now()])->save();
            // Tout autre lien encore en circulation pour ce compte est annulé.
            ReinitialisationMotDePasse::where('compte_id', $compte->id)->whereNull('utilise_le')->update(['expire_le' => now()]);

            AuditLog::create([
                'auteur_id' => $compte->agent_id, 'action' => 'mdp_reinitialise', 'objet_type' => 'compte',
                'objet_id' => $compte->id, 'adresse_ip' => $request->ip(),
            ]);

            return $compte->agent;
        });

        if (! $agent) {
            return response()->view('auth.reinitialisation-invalide', [], 410);
        }

        // Prévient l'agent : s'il n'est pas à l'origine du changement, il saura qu'il doit appeler la RH.
        $messages->envoyer(
            $agent,
            'Votre mot de passe a été modifié — Congés Anosy',
            'Le mot de passe de votre compte vient d’être modifié. Si ce n’est pas vous, contactez la RH sans attendre.',
        );

        return redirect()->route('connexion')->with('statut', 'Mot de passe modifié. Connectez-vous avec le nouveau.');
    }

    /** Crée un jeton à usage unique et renvoie sa valeur en clair ; seule l'empreinte SHA-256 est stockée. */
    private function creerJeton(Compte $compte): string
    {
        // Un seul lien valable à la fois : les précédents sont annulés.
        ReinitialisationMotDePasse::where('compte_id', $compte->id)->whereNull('utilise_le')
            ->where('expire_le', '>', now())->update(['expire_le' => now()]);

        $jeton = Str::random(64);
        ReinitialisationMotDePasse::create([
            'compte_id' => $compte->id,
            'jeton_hash' => hash('sha256', $jeton),
            'expire_le' => now()->addMinutes(self::VALIDITE_MINUTES),
        ]);

        return $jeton;
    }

    private function trouverValide(string $jeton): ?ReinitialisationMotDePasse
    {
        $reinit = ReinitialisationMotDePasse::with('compte.agent')
            ->where('jeton_hash', hash('sha256', $jeton))
            ->whereNull('utilise_le')
            ->where('expire_le', '>', now())
            ->first();

        if (! $reinit || ! $reinit->compte->estActif() || $reinit->compte->agent->situation !== 'actif') {
            return null;
        }
        return $reinit;
    }
}
