<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sert la photo d'un agent. La photo n'est jamais dans public/ : elle n'est envoyée qu'à celui qui a le droit
 * de voir la fiche (lui-même, la RH, son responsable, son autorité). Sinon : une silhouette neutre, jamais
 * d'erreur ni d'indication sur l'existence de la photo.
 */
class PhotoController extends Controller
{
    public function afficher(Agent $agent): Response
    {
        $moi = auth()->user();
        $autorise = (int) $moi->agent_id === (int) $agent->id || Gate::allows('voir', $agent);

        if ($autorise && $agent->photo_chemin && Storage::disk('local')->exists($agent->photo_chemin)) {
            return response(Storage::disk('local')->get($agent->photo_chemin), 200, [
                'Content-Type' => 'image/jpeg',
                'Cache-Control' => 'private, max-age=86400',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response($this->silhouette(), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function silhouette(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#E1ECE6"/>'
            .'<circle cx="50" cy="38" r="17" fill="#8FB3A1"/><path d="M16 100c0-22 15-34 34-34s34 12 34 34z" fill="#8FB3A1"/></svg>';
    }
}
