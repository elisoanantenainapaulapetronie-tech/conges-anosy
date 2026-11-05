<?php

namespace App\Services;

use App\Mail\MessageAgent;
use App\Models\Agent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Notifications du circuit (décision n° 1 du dossier) :
 *   - notification INTERNE obligatoire pour tout compte (table notifications) ;
 *   - E-MAIL si l'agent en a un ;
 *   - SMS pour les messages « importants » si l'agent n'a pas d'e-mail ;
 *   - chaque envoi est tracé par canal dans envois_notifications (envoyé ou échec).
 *
 * Utilisation :
 *   $notifications->notifier($responsable, 'avis_demande', 'Avis demandé',
 *       'M. RAKOTOARISOA demande votre avis (AV-2026-0087).',
 *       ['avis_id' => $avis->id, 'importance' => 'importante', 'lien' => route('accueil')]);
 *
 * Les écritures passent par DB::table() : elles ne dépendent pas des réglages des modèles.
 * L'envoi est immédiat ; il pourra passer en file d'attente sans changer les appels.
 */
class NotificationService
{
    public function __construct(private PasserelleSms $sms) {}

    /**
     * @param array{importance?:string, demande_id?:int, avis_id?:int, lien?:string, libelle_lien?:string} $options
     * @return int identifiant de la notification interne
     */
    public function notifier(Agent|int $destinataire, string $type, string $titre, ?string $message = null, array $options = []): int
    {
        $agent = $destinataire instanceof Agent ? $destinataire : Agent::findOrFail($destinataire);
        $importance = ($options['importance'] ?? 'normale') === 'importante' ? 'importante' : 'normale';

        $id = DB::table('notifications')->insertGetId([
            'destinataire_id' => $agent->id,
            'type_evenement' => $type,
            'titre' => $titre,
            'message' => $message,
            'importance' => $importance,
            'demande_id' => $options['demande_id'] ?? null,
            'avis_id' => $options['avis_id'] ?? null,
            'created_at' => now(),
        ]);

        $this->tracer($id, 'interne', null, 'envoye');

        $texte = $message ?: $titre;

        if (filled($agent->email)) {
            try {
                Mail::to($agent->email)->send(new MessageAgent(
                    $titre, $texte, $options['lien'] ?? null, $options['libelle_lien'] ?? null, $agent->nom_complet
                ));
                $this->tracer($id, 'email', $agent->email, 'envoye');
            } catch (Throwable $e) {
                Log::warning('Envoi e-mail échoué : '.$e->getMessage());
                $this->tracer($id, 'email', $agent->email, 'echec', $e->getMessage());
            }
        } elseif ($importance === 'importante' && filled($agent->telephone)) {
            $ok = $this->sms->envoyer($agent->telephone, trim($titre.' : '.$texte.' '.($options['lien'] ?? '')));
            $this->tracer($id, 'sms', $agent->telephone, $ok ? 'envoye' : 'echec', $ok ? null : 'Passerelle SMS indisponible');
        }

        return $id;
    }

    /** Même notification pour plusieurs personnes ; chacune est notifiée une seule fois. */
    public function notifierPlusieurs(iterable $destinataires, string $type, string $titre, ?string $message = null, array $options = []): void
    {
        $vus = [];
        foreach ($destinataires as $d) {
            $agent = $d instanceof Agent ? $d : Agent::find($d);
            if (! $agent || isset($vus[$agent->id])) {
                continue;
            }
            $vus[$agent->id] = true;
            $this->notifier($agent, $type, $titre, $message, $options);
        }
    }

    /** Agents actifs détenant aujourd'hui l'un des rôles donnés (ex. ['rh_gestionnaire', 'rh_responsable']). */
    public function agentsAyantRole(array $codes): Collection
    {
        $ids = DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->whereIn('roles.code', $codes)
            ->whereDate('habilitations.date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()))
            ->pluck('habilitations.agent_id')->unique();

        return Agent::actifs()->whereIn('id', $ids)->get();
    }

    /** Notifie toute la RH (gestionnaires et responsable). */
    public function notifierRh(string $type, string $titre, ?string $message = null, array $options = []): void
    {
        $this->notifierPlusieurs($this->agentsAyantRole(['rh_gestionnaire', 'rh_responsable']), $type, $titre, $message, $options);
    }

    private function tracer(int $notificationId, string $canal, ?string $adresse, string $statut, ?string $erreur = null): void
    {
        DB::table('envois_notifications')->insert([
            'notification_id' => $notificationId,
            'canal' => $canal,
            'adresse' => $adresse,
            'statut' => $statut,
            'envoye_le' => $statut === 'envoye' ? now() : null,
            'erreur' => $erreur ? mb_substr($erreur, 0, 255) : null,
            'created_at' => now(),
        ]);
    }
}
