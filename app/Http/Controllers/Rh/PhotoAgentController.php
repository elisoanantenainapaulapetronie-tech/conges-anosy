<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\PhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** La RH met à jour ou retire la photo d'un agent (jamais la sienne : elle passe par « Mon profil »). */
class PhotoAgentController extends Controller
{
    use Autorise;

    public function enregistrer(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriser('modifier', $agent);

        $request->validate(PhotoService::regles(), PhotoService::messages());
        PhotoService::enregistrer($agent, $request->file('photo'));
        AuditService::enregistrer('photo_modifiee', $agent, null, ['par' => 'rh']);

        return redirect()->to(route('rh.agents.show', $agent).'#photo')->with('statut', 'Photo enregistrée.');
    }

    public function supprimer(Agent $agent): RedirectResponse
    {
        $this->autoriser('modifier', $agent);

        PhotoService::supprimer($agent);
        AuditService::enregistrer('photo_supprimee', $agent, null, ['par' => 'rh']);

        return redirect()->to(route('rh.agents.show', $agent).'#photo')->with('statut', 'Photo supprimée.');
    }
}
