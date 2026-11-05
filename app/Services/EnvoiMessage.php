<?php

namespace App\Services;

use App\Mail\MessageAgent;
use App\Models\Agent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Messages de SÉCURITÉ (mot de passe oublié, mot de passe modifié) : e-mail s'il existe, sinon SMS.
 * Ils n'apparaissent pas dans les notifications internes. Pour les événements du circuit
 * (avis, décision, rappel...), utiliser NotificationService.
 */
class EnvoiMessage
{
    public function __construct(private PasserelleSms $sms) {}

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
            return $this->sms->envoyer($agent->telephone, trim($texte.' '.$lien)) ? 'sms' : null;
        }

        return null;
    }
}
