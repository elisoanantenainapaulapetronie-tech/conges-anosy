<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnexion après N minutes SANS activité (N = config('conges.inactivite_minutes'), 30 par défaut).
 *
 * « Sans activité » : chaque requête de l'utilisateur repousse l'échéance de N minutes. Ce n'est donc pas
 * une durée de connexion : quelqu'un qui travaille n'est jamais déconnecté. Le script session.js prévient
 * 2 minutes avant et signale au serveur (au plus une fois par minute) que l'utilisateur est toujours là.
 * Appliqué à TOUTES les routes web (groupe « web »), donc à tous les espaces.
 */
class InactiviteSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $limite = (int) config('conges.inactivite_minutes', 30) * 60;
        $derniere = (int) $request->session()->get('derniere_activite', 0);

        if ($derniere > 0 && (time() - $derniere) > $limite) {
            AuditService::enregistrer('deconnexion_inactivite', null, null, ['minutes' => $limite / 60]);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => 'Session expirée par inactivité.'], 401);
            }

            return redirect()->route('connexion', ['expiree' => 1]);
        }

        $request->session()->put('derniere_activite', time());

        return $next($request);
    }
}
