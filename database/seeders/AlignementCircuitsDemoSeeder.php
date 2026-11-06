<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Services\CircuitRegles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Données de DÉMONSTRATION seulement. À lancer après DonneesDemonstrationSeeder :
 *     php artisan db:seed --class=AlignementCircuitsDemoSeeder
 *
 * Les directeurs (responsables d'une structure de type direction) reçoivent l'avis du Secrétaire Général
 * et leur demande est décidée par le Chef de Région : leur fiche passe donc au circuit « Chef de Région ».
 * Sans effet sur les autres agents ; peut être relancé sans risque.
 */
class AlignementCircuitsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $sg = Agent::query()
            ->whereIn('id', DB::table('habilitations')
                ->join('roles', 'roles.id', '=', 'habilitations.role_id')
                ->where('roles.code', 'secretaire_general')->pluck('habilitations.agent_id'))
            ->first();

        $modifies = 0;
        foreach (Agent::actifs()->get() as $agent) {
            if (! CircuitRegles::estDirecteur($agent->id)) {
                continue;
            }

            $changements = [];
            if ($agent->rattachement !== 'region') {
                $changements['rattachement'] = 'region';
            }
            if ($sg && (int) $agent->responsable_id !== (int) $sg->id) {
                $changements['responsable_id'] = $sg->id;
            }
            if (! $changements) {
                continue;
            }

            $agent->forceFill($changements)->save();
            DB::table('affectations_historique')->where('agent_id', $agent->id)->whereNull('date_fin')
                ->update(array_intersect_key($changements, ['rattachement' => 1, 'responsable_id' => 1]));
            $modifies++;
        }

        $this->command?->info("Directeurs alignés sur la règle (avis du SG, décision du Chef de Région) : {$modifies}.");
    }
}
