<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/*
 * Cree toute la base de l'application (27 tables, 3 vues, 8 declencheurs,
 * donnees de reference) a partir du script SQL : database/sql/01_schema_conges_anosy.sql
 *
 * Avantage : la structure est IDENTIQUE a celle testee dans phpMyAdmin,
 * il n'y a qu'une seule source de verite.
 * Pour modifier la structure plus tard, ajoutez une NOUVELLE migration
 * (ne modifiez pas celle-ci apres l'avoir executee).
 */
return new class extends Migration
{
    /** Tables dans l'ordre de creation (supprimees dans l'ordre inverse). */
    private array $tables = [
        'structures', 'fonctions', 'agents', 'affectations_historique', 'roles', 'comptes',
        'reinitialisations_mot_de_passe', 'habilitations', 'types_demande', 'jours_feries',
        'parametres', 'compteurs_references', 'avis_prealables', 'delegations', 'demandes',
        'missions', 'demande_etapes', 'demande_historique', 'indisponibilites',
        'soldes_propositions', 'pieces_jointes', 'consultations_pieces', 'soldes_mouvements',
        'documents', 'notifications', 'envois_notifications', 'audit_logs',
    ];

    public function up(): void
    {
        $chemin = database_path('sql/01_schema_conges_anosy.sql');

        foreach ($this->decouper(File::get($chemin)) as $instruction) {
            DB::unprepared($instruction);
        }
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (['v_etapes_en_retard', 'v_planning_global', 'v_soldes_agents'] as $vue) {
            DB::statement("DROP VIEW IF EXISTS {$vue}");
        }

        foreach (array_reverse($this->tables) as $table) {
            DB::statement("DROP TABLE IF EXISTS {$table}");   // supprime aussi les declencheurs
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
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
                $dansChaine = ! $dansChaine;   // une apostrophe doublee ('') garde l'etat inchange
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
};
