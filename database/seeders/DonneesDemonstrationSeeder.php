<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
 * Charge les personnages fictifs du dossier (Hery RAKOTOARISOA, etc.)
 * depuis database/sql/02_donnees_demonstration.sql.
 *
 * A lancer UNE SEULE FOIS, apres php artisan migrate :
 *     php artisan db:seed --class=DonneesDemonstrationSeeder
 * Pour recommencer : php artisan migrate:fresh puis relancer cette commande.
 */
class DonneesDemonstrationSeeder extends Seeder
{
    public function run(): void
    {
        $sql = File::get(database_path('sql/02_donnees_demonstration.sql'));

        foreach ($this->decouper($sql) as $instruction) {
            DB::unprepared($instruction);
        }

        $this->command?->info('Donnees de demonstration chargees.');
    }

    /** Decoupe le script en instructions : ignore les commentaires et les ";" entre apostrophes. */
    private function decouper(string $sql): array
    {
        $lignes = array_filter(
            preg_split('/\R/', $sql),
            fn ($ligne) => ! str_starts_with(trim($ligne), '--')
        );
        $texte = implode("\n", $lignes);

        $instructions = [];
        $courante = '';
        $dansChaine = false;

        for ($i = 0, $n = strlen($texte); $i < $n; $i++) {
            $c = $texte[$i];

            if ($c === "'") {
                $dansChaine = ! $dansChaine;
            }

            if ($c === ';' && ! $dansChaine) {
                if (trim($courante) !== '') {
                    $instructions[] = trim($courante);
                }
                $courante = '';
                continue;
            }

            $courante .= $c;
        }

        if (trim($courante) !== '') {
            $instructions[] = trim($courante);
        }

        return $instructions;
    }
}
