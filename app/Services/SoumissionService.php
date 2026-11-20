<?php

namespace App\Services;

use App\Exceptions\SoldeException;
use App\Models\Agent;
use App\Models\Demande;
use App\Models\DemandeEtape;
use App\Models\DemandeHistorique;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soumission d'une demande (brouillon -> soumise).
 * Première tranche : absence courte uniquement (approbation par le responsable direct).
 */
class SoumissionService
{
    /** Statuts qui ne comptent pas pour les chevauchements. */
    private const STATUTS_NON_COMPTES = ['brouillon', 'refusee', 'irrecevable', 'annulee', 'caduque'];

    public static function soumettre(Demande $demande, Agent $auteur): Demande
    {
        return DB::transaction(function () use ($demande, $auteur) {
            $d = Demande::whereKey($demande->id)->lockForUpdate()->firstOrFail();

            if ($d->statut !== 'brouillon') {
                self::refuser('Cette demande a déjà été envoyée.');
            }

            $type = $d->typeDemande;
            $debut = $d->date_debut->format('Y-m-d');
            $fin = $d->date_fin->format('Y-m-d');

            try {
                $controle = SoldeService::controlerSoumission(
                    (int) $d->agent_id, $type->code, $debut, $fin,
                    $d->demi_journee_debut ?? 'journee', $d->demi_journee_fin ?? 'journee', (int) $d->id
                );
            } catch (SoldeException|\InvalidArgumentException $e) {
                self::refuser($e->getMessage());
            }

            if ($controle['bloquant']) {
                self::refuser(implode(' ', $controle['raisons']) ?: 'Solde insuffisant.');
            }

            // Première tranche : seule l'absence courte est gérée.
            $seuil = Parametres::entier('seuil_absence_courte_jours', 1);
            if ($type->code !== 'autorisation_absence' || $controle['duree'] > $seuil) {
                self::refuser('L’envoi de ce type de demande n’est pas encore disponible : seule l’absence de '.$seuil.' jour au plus peut être envoyée.');
            }

            $chevauche = DB::table('demandes')
                ->where('agent_id', $d->agent_id)->where('id', '<>', $d->id)
                ->whereNotIn('statut', self::STATUTS_NON_COMPTES)
                ->whereDate('date_debut', '<=', $fin)->whereDate('date_fin', '>=', $debut)
                ->exists();
            if ($chevauche) {
                self::refuser('Cette période chevauche une autre de vos demandes.');
            }

            $responsableId = (int) DB::table('agents')->where('id', $d->agent_id)->value('responsable_id');
            if ($responsableId === 0) {
                self::refuser('Aucun responsable direct n’est renseigné sur votre fiche : contactez la RH.');
            }

            $reference = ReferenceService::suivante('DM');

            $d->update([
                'reference' => $reference,
                'duree_jours' => $controle['duree'],
                'circuit' => 'responsable',
                'statut' => 'soumise',
                'derogation' => $controle['derogation'],
                'soumise_le' => now(),
            ]);

            DemandeEtape::create([
                'demande_id' => $d->id,
                'ordre' => 1,
                'role_etape' => 'approbation_responsable',
                'acteur_id' => $responsableId,
                'statut' => 'en_attente',
                'echeance_le' => now()->addWeekdays(Parametres::entier('delai_avis_jours_ouvres', 2)),
            ]);

            DemandeHistorique::create([
                'demande_id' => $d->id,
                'ancien_statut' => 'brouillon',
                'nouveau_statut' => 'soumise',
                'auteur_id' => $auteur->id,
                'commentaire' => 'Demande envoyée.',
            ]);

            return $d;
        });
    }

    private static function refuser(string $message): never
    {
        throw ValidationException::withMessages(['soumission' => $message]);
    }
}