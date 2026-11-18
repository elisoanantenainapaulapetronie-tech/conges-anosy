<?php

namespace App\Http\Controllers\Rh;

use App\Http\Controllers\Concerns\Autorise;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Compte;
use App\Services\AuditService;
use App\Services\CodeActivationService;
use App\Services\ContinuiteService;
use App\Services\EnvoiMessage;
use App\Services\ReinitialisationService;
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

        $emis = CodeActivationService::emettre($agent);
        $code = $emis['code'];
        $expire = $emis['expire'];

        // Le code lui-même n'est JAMAIS écrit dans le journal : seule son existence l'est.
        AuditService::enregistrer($emis['nouveau'] ? 'compte_cree' : 'code_activation_regenere', $agent, $emis['avant'],
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

        // Continuité : on ne désactive pas le dernier compte de RH responsable (personne ne pourrait plus attribuer les rôles).
        if (ContinuiteService::estLeSeulResponsable((int) $agent->id)) {
            AuditService::refus('desactivation_refusee_dernier_responsable', $agent, []);

            return back()->with('erreur', 'C’est la seule RH responsable avec un compte actif : attribuez d’abord ce rôle à un autre agent (ou une suppléance), puis désactivez ce compte.');
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

    /**
     * Lien de réinitialisation remis par la RH (agent actif sans e-mail ni téléphone, ou qui ne reçoit plus ses messages).
     * Valable 2 heures, à usage unique, affiché une seule fois à la RH ; l'agent est prévenu s'il a un canal. Aucun rôle n'est touché.
     */
    public function genererLien(Agent $agent, EnvoiMessage $messages): RedirectResponse
    {
        $this->autoriser('genererLienReinitialisation', $agent);

        $compte = $agent->compte;
        if (! $compte || $compte->statut !== 'actif' || $agent->situation !== 'actif') {
            return back()->with('erreur', 'Seul un compte actif d’un agent actif peut recevoir un lien de réinitialisation.');
        }

        $minutes = 120;
        $jeton = ReinitialisationService::creerJeton($compte, $minutes);
        $expire = now()->addMinutes($minutes);

        // Le lien lui-même n'est jamais journalisé.
        AuditService::enregistrer('lien_reinitialisation_remis', $agent, null, ['expire_le' => $expire->format('Y-m-d H:i'), 'remis_par_rh' => true]);

        $messages->envoyer($agent, 'Un lien de réinitialisation a été généré pour votre compte — Congés Anosy',
            'La RH a généré un lien pour changer votre mot de passe. Si vous ne l’avez pas demandé, prévenez la RH.');

        return redirect()->to(route('rh.agents.show', $agent).'#compte')
            ->with('lien_reinitialisation', route('reinitialisation', ['jeton' => $jeton]))
            ->with('lien_expire', $expire->format('d/m/Y à H:i'));
    }
}
