<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Compte;
use App\Services\AuditService;
use App\Services\OrganisationService;
use App\Services\Parametres;
use Illuminate\Http\RedirectResponse;

/**
 * Comptes de connexion. La RH ne choisit jamais le mot de passe : elle remet un code d'activation
 * à usage unique, et l'agent choisit lui-même son mot de passe sur la page d'activation.
 */
class CompteController extends Controller
{
    use Autorise;

    /** Crée le compte (ou en régénère le code) et affiche le code UNE seule fois. */
    public function genererCode(Agent $agent): RedirectResponse
    {
        $this->autoriser('genererCodeActivation', $agent);

        if ($agent->situation !== 'actif') {
            return back()->with('erreur', 'Seul un agent en situation « actif » peut recevoir un compte.');
        }

        $code = OrganisationService::codeActivation();
        $jours = Parametres::entier('duree_code_activation_jours', 5);
        $expire = now()->addDays($jours)->endOfDay();

        $compte = Compte::firstOrNew(['agent_id' => $agent->id]);
        $nouveau = ! $compte->exists;
        $avant = $nouveau ? null : ['statut' => $compte->statut];

        $compte->forceFill([
            'statut' => 'en_attente_activation',
            'mot_de_passe_hash' => null,
            'code_activation_hash' => bcrypt($code),
            'code_activation_expire_le' => $expire,
            'echecs_connexion' => 0,
            'bloque_jusqua' => null,
        ])->save();

        // Le code lui-même n'est JAMAIS écrit dans le journal : seule son existence l'est.
        AuditService::enregistrer($nouveau ? 'compte_cree' : 'code_activation_regenere', $agent, $avant,
            ['statut' => 'en_attente_activation', 'expire_le' => $expire->toDateString()]);

        return redirect()->route('rh.agents.show', $agent)
            ->with('code_activation', $code)
            ->with('code_expire', $expire->translatedFormat('j F Y'));
    }

    public function desactiver(Agent $agent): RedirectResponse
    {
        $this->autoriser('changerEtatCompte', $agent);

        $compte = $agent->compte;
        if (! $compte || $compte->statut !== 'actif') {
            return back()->with('erreur', 'Seul un compte actif peut être désactivé.');
        }

        $compte->forceFill(['statut' => 'desactive'])->save();
        AuditService::enregistrer('compte_desactive', $agent, ['statut' => 'actif'], ['statut' => 'desactive']);

        return back()->with('statut', 'Compte désactivé : l’agent ne peut plus se connecter.');
    }

    public function reactiver(Agent $agent): RedirectResponse
    {
        $this->autoriser('changerEtatCompte', $agent);

        $compte = $agent->compte;
        if (! $compte || $compte->statut !== 'desactive') {
            return back()->with('erreur', 'Seul un compte désactivé peut être réactivé.');
        }

        // Sans mot de passe déjà choisi, le compte repasse par l'activation (nouveau code à générer).
        $statut = filled($compte->mot_de_passe_hash) ? 'actif' : 'en_attente_activation';
        $compte->forceFill(['statut' => $statut, 'echecs_connexion' => 0, 'bloque_jusqua' => null])->save();
        AuditService::enregistrer('compte_reactive', $agent, ['statut' => 'desactive'], ['statut' => $statut]);

        return back()->with('statut', $statut === 'actif'
            ? 'Compte réactivé.'
            : 'Compte réactivé en attente d’activation : générez un nouveau code d’activation.');
    }
}
