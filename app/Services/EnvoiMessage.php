<?php

namespace App\Services;

use App\Mail\MessageAgent;
use App\Models\Agent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoie un message à un agent par le meilleur canal disponible :
 * e-mail s'il en a un, sinon SMS s'il a un téléphone, sinon rien (la RH doit intervenir).
 *
 * SMS : pas encore de passerelle réelle. Le message est écrit dans storage/logs/laravel.log
 * (« [SMS simulé] »). Pour brancher un vrai opérateur, il suffit de modifier envoyerSms().
 */
class EnvoiMessage
{
    /** @return string|null  'email', 'sms' ou null si aucun canal n'a fonctionné */
    public function envoyer(Agent $agent, string $sujet, string $texte, ?string $lien = null, ?string $libelleLien = null): ?string
    {
        if (filled($agent->email)) {
            try {
                Mail::to($agent->email)->send(new MessageAgent($sujet, $texte, $lien, $libelleLien, $agent->nom_complet));
                return 'email';
            } catch (Throwable $e) {
                Log::warning('Envoi e-mail échoué : '.$e->getMessage());
            }
        }

        if (filled($agent->telephone)) {
            return $this->envoyerSms($agent->telephone, trim($texte.' '.$lien)) ? 'sms' : null;
        }

        return null;
    }

    private function envoyerSms(string $telephone, string $texte): bool
    {
        Log::info("[SMS simulé] vers {$telephone} : {$texte}");
        return true;
    }
}
