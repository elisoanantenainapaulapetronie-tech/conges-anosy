<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Compte;
use App\Models\Fonction;
use App\Models\Structure;
use Illuminate\Database\Seeder;

/**
 * Comptes de TEST pour les personnages fictifs. NE PAS UTILISER EN PRODUCTION.
 * À lancer après DonneesDemonstrationSeeder :
 *     php artisan db:seed --class=ComptesDemonstrationSeeder
 *
 * - tous les agents de démonstration sont activés avec le mot de passe « Demo2026 »
 *   (sauf Hery RAKOTOARISOA, matricule AGT-0231) ;
 * - Hery reste « en attente d'activation » avec le code ACTIVE231, pour tester la page d'activation ;
 * - Naly RAZAFINDRABE n'a PAS de matricule : son identifiant ANO-xxxxx est généré par le système.
 *   Elle n’a qu’un téléphone : « Mot de passe oublié » lui envoie un SMS (simulé dans le journal).
 */
class ComptesDemonstrationSeeder extends Seeder
{
    public function run(): void
    {
        $naly = Agent::firstOrCreate(
            ['nom' => 'RAZAFINDRABE', 'prenoms' => 'Naly'],
            [
                'matricule' => null,
                'sexe' => 'F',
                'telephone' => '0340000099',
                'email' => null,
                'fonction_id' => Fonction::where('libelle', 'Assistant')->value('id'),
                'structure_id' => Structure::where('nom', 'Service Comptabilite')->value('id'),
                'responsable_id' => Agent::where('matricule', 'AGT-0104')->value('id'),
                'rattachement' => 'sg',
                'date_entree' => '2024-02-01',
            ]
        );

        foreach (Agent::all() as $agent) {
            $compte = Compte::firstOrNew(['agent_id' => $agent->id]);

            if ($agent->matricule === 'AGT-0231') {
                $compte->forceFill([
                    'statut' => 'en_attente_activation',
                    'mot_de_passe_hash' => null,
                    'code_activation_hash' => bcrypt('ACTIVE231'),
                    'code_activation_expire_le' => now()->addDays(30),
                ])->save();
                continue;
            }

            $compte->forceFill(['statut' => 'en_attente_activation'])->save();
            $compte->activer('Demo2026');
        }

        $this->command?->info('Comptes de démonstration prêts. Mot de passe : Demo2026 ; Hery : code ACTIVE231.');
        $this->command?->info("Agent sans matricule : Naly RAZAFINDRABE, identifiant {$naly->fresh()->identifiant}.");
    }
}
