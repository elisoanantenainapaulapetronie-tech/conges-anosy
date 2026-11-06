<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Services\AuditService;
use App\Services\OrganisationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attribution des rôles (habilitations). Réservé au RH responsable, jamais pour soi-même.
 * Un rôle est daté et appuyé par un acte (note de service) : il n'est jamais « à vie ».
 */
class HabilitationController extends Controller
{
    use Autorise;

    /** Rôles dont une seule personne peut être titulaire à la fois. */
    private const UNIQUES = ['secretaire_general', 'chef_region'];

    public function store(Request $request, Agent $agent): RedirectResponse
    {
        $this->autoriser('attribuerRole', $agent);

        $donnees = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'acte_reference' => ['required', 'string', 'max:190'],
        ], [
            'role_id.required' => 'Choisissez un rôle.',
            'date_debut.required' => 'Saisissez la date de début.',
            'date_fin.after_or_equal' => 'La date de fin ne peut pas précéder la date de début.',
            'acte_reference.required' => 'Indiquez l’acte qui fonde ce rôle (ex. Note de service n° 12).',
        ]);

        if ($agent->situation !== 'actif') {
            throw ValidationException::withMessages(['role_id' => 'Seul un agent actif peut recevoir un rôle.']);
        }

        $role = DB::table('roles')->where('id', $donnees['role_id'])->first();
        $structure = $donnees['structure_id'] ?? null;

        if ($role->code === 'responsable_hierarchique' && ! $structure) {
            throw ValidationException::withMessages(['structure_id' => 'Indiquez le service ou la direction dont il est responsable.']);
        }

        $this->controlerDoublons($agent, $role, $structure, $donnees['date_debut'], $donnees['date_fin'] ?? null);

        DB::table('habilitations')->insert([
            'agent_id' => $agent->id,
            'role_id' => $role->id,
            'structure_id' => $structure,
            'date_debut' => $donnees['date_debut'],
            'date_fin' => $donnees['date_fin'] ?? null,
            'acte_reference' => $donnees['acte_reference'],
            'attribuee_par' => auth()->user()->agent_id,
        ]);

        AuditService::enregistrer('role_attribue', $agent, null, [
            'role' => $role->code, 'structure_id' => $structure,
            'du' => $donnees['date_debut'], 'au' => $donnees['date_fin'] ?? null, 'acte' => $donnees['acte_reference'],
        ]);

        return redirect()->to(route('rh.agents.show', $agent).'#roles')
            ->with('statut', "Rôle « {$role->libelle} » attribué à {$agent->nom_complet}.");
    }

    /** Retire un rôle : effet immédiat. Un rôle jamais entré en vigueur est simplement annulé. */
    public function retirer(Agent $agent, int $habilitation): RedirectResponse
    {
        $this->autoriser('attribuerRole', $agent);

        $ligne = DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->where('habilitations.id', $habilitation)->where('habilitations.agent_id', $agent->id)
            ->select('habilitations.*', 'roles.code as role_code', 'roles.libelle as role_libelle')
            ->first();
        abort_unless($ligne, 404);

        $enCours = $ligne->date_debut <= today()->toDateString()
            && ($ligne->date_fin === null || $ligne->date_fin >= today()->toDateString());

        // On ne laisse pas l'application sans RH responsable.
        if ($enCours && $ligne->role_code === 'rh_responsable'
            && OrganisationService::autresDetenteurs('rh_responsable', $agent->id) === 0) {
            return back()->with('erreur', 'C’est le seul RH responsable : attribuez d’abord ce rôle à un autre agent.');
        }

        $avant = ['role' => $ligne->role_code, 'du' => $ligne->date_debut, 'au' => $ligne->date_fin];

        if ($ligne->date_debut >= today()->toDateString()) {
            DB::table('habilitations')->where('id', $ligne->id)->delete();
            $apres = ['retire' => 'annule'];
        } else {
            $hier = today()->subDay()->toDateString();
            DB::table('habilitations')->where('id', $ligne->id)->update(['date_fin' => $hier]);
            $apres = ['au' => $hier];
        }

        AuditService::enregistrer('role_retire', $agent, $avant, $apres);

        return redirect()->to(route('rh.agents.show', $agent).'#roles')
            ->with('statut', "Rôle « {$ligne->role_libelle} » retiré.");
    }

    // ------------------------------------------------------------------

    private function controlerDoublons(Agent $agent, object $role, ?int $structure, string $debut, ?string $fin): void
    {
        $chevauche = function ($q) use ($debut, $fin) {
            $q->where(fn ($w) => $w->whereNull('habilitations.date_fin')->orWhere('habilitations.date_fin', '>=', $debut));
            if ($fin) {
                $q->where('habilitations.date_debut', '<=', $fin);
            }
        };

        // Le même rôle, sur le même périmètre, pendant la même période.
        $meme = DB::table('habilitations')->where('agent_id', $agent->id)->where('role_id', $role->id)
            ->when($structure, fn ($q) => $q->where('structure_id', $structure), fn ($q) => $q->whereNull('structure_id'))
            ->where($chevauche)->exists();
        if ($meme) {
            throw ValidationException::withMessages(['role_id' => 'Cet agent a déjà ce rôle sur cette période : retirez-le d’abord ou changez les dates.']);
        }

        // Secrétaire Général et Chef de Région : un seul titulaire à la fois.
        if (in_array($role->code, self::UNIQUES, true)) {
            $autre = DB::table('habilitations')
                ->join('agents', 'agents.id', '=', 'habilitations.agent_id')
                ->where('habilitations.role_id', $role->id)->where('habilitations.agent_id', '!=', $agent->id)
                ->where($chevauche)
                ->selectRaw("TRIM(CONCAT_WS(' ', agents.prenoms, UPPER(agents.nom))) as nom")->first();
            if ($autre) {
                throw ValidationException::withMessages(['role_id' => "« {$role->libelle} » est déjà détenu par {$autre->nom} sur cette période."]);
            }
        }
    }
}
