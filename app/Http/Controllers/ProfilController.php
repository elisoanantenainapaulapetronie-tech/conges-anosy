<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\PhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** « Mon profil » : commun à tous les espaces (agent, responsable, RH...). Chacun gère SA photo. */
class ProfilController extends Controller
{
    public function show()
    {
        $agent = auth()->user()->agent->load(['fonction', 'structure', 'responsable']);

        $roles = DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->leftJoin('structures', 'structures.id', '=', 'habilitations.structure_id')
            ->where('habilitations.agent_id', $agent->id)
            ->whereDate('habilitations.date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()))
            ->orderBy('roles.id')
            ->get(['roles.libelle', 'structures.nom as structure']);

        return view('profil.show', [
            'agent' => $agent,
            'roles' => $roles,
            'modifiable' => PhotoService::modifiableParAgent(),
        ]);
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        $agent = auth()->user()->agent;
        $this->exigerModifiable($agent);

        $request->validate(PhotoService::regles(), PhotoService::messages());
        PhotoService::enregistrer($agent, $request->file('photo'));
        AuditService::enregistrer('photo_modifiee', $agent, null, ['par' => 'agent']);

        return redirect()->route('profil')->with('statut', 'Votre photo est enregistrée.');
    }

    public function supprimer(): RedirectResponse
    {
        $agent = auth()->user()->agent;
        $this->exigerModifiable($agent);

        PhotoService::supprimer($agent);
        AuditService::enregistrer('photo_supprimee', $agent, null, ['par' => 'agent']);

        return redirect()->route('profil')->with('statut', 'Votre photo est supprimée.');
    }

    private function exigerModifiable($agent): void
    {
        if (! PhotoService::modifiableParAgent()) {
            AuditService::refus('photo_refusee', $agent, ['motif' => 'photo reservee a la RH']);
            abort(403, 'La photo de profil est gérée par la RH.');
        }
    }
}
