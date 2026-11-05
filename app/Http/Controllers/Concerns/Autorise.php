<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AuditService;
use Illuminate\Support\Facades\Gate;

/**
 * À utiliser dans les contrôleurs : refuse (403) ET inscrit le refus dans le journal d'audit.
 *
 *   use Autorise;
 *   $this->autoriser('decider', $demande);
 *
 * Pour afficher ou masquer un bouton, utiliser @can / Gate::allows : cela ne journalise rien.
 */
trait Autorise
{
    protected function autoriser(string $droit, mixed $objet = null): void
    {
        $reponse = Gate::inspect($droit, $objet);

        if ($reponse->denied()) {
            AuditService::refus('acces_refuse_'.$droit, is_object($objet) && method_exists($objet, 'getKey') ? $objet : null, [
                'droit' => $droit,
                'url' => request()->path(),
            ]);
            abort(403, $reponse->message() ?: 'Accès refusé.');
        }
    }
}
