<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage : ->middleware('role:rh_responsable,rh_gestionnaire'). Refus journalisé dans audit_logs. */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $compte = $request->user();

        if (! $compte || ! $compte->aLeRole(...$roles)) {
            AuditLog::create([
                'auteur_id' => $compte?->agent_id,
                'action' => 'acces_refuse',
                'objet_type' => 'route',
                'valeur_apres' => ['url' => $request->path(), 'roles_requis' => $roles],
                'refuse' => true,
                'adresse_ip' => $request->ip(),
            ]);
            abort(403, 'Accès refusé.');
        }

        return $next($request);
    }
}
