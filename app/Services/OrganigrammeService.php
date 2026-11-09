<?php

namespace App\Services;

use App\Http\Controllers\Rh\StructureController;
use App\Models\Agent;
use Illuminate\Support\Facades\DB;

/**
 * Organigramme ÉVOLUTIF : l'arbre est calculé à chaque affichage à partir de la base, jamais dessiné à la main.
 *
 *  - la forme vient des structures (parent_id) : une structure ajoutée apparaît toute seule ;
 *  - les responsables viennent des rôles en cours (habilitations) : un changement de rôle, une mutation ou la fin
 *    d'un intérim se voit immédiatement ;
 *  - les postes sans rôle ou sans titulaire (poste vacant, poste à créer) viennent de la table « postes » ;
 *  - les effectifs par fonction viennent des fiches : une nouvelle fonction apparaît dès qu'un agent l'occupe.
 *
 * Au sommet, le Chef de Région. Le service ne modifie rien.
 */
class OrganigrammeService
{
    /** Rôles qui font de leur titulaire le responsable d'une structure (la fonction n'est jamais un droit). */
    private const ROLES_RESPONSABLE = ['responsable_hierarchique', 'secretaire_general', 'chef_region'];

    private const TITRES = [
        'responsable_hierarchique' => 'Responsable',
        'secretaire_general' => 'Secrétaire Général',
        'chef_region' => 'Chef de Région',
    ];

    /**
     * @return array{
     *   chefs: array<int, array{agent: Agent, titre: string}>,
     *   racines: array<int, array>,
     *   stats: array<string, int>,
     *   anomalies: array<int, array{type: string, structure_id: int, libelle: string}>,
     *   orphelines: array<int, string>
     * }
     */
    public static function construire(bool $inactives = false, ?int $maStructureId = null): array
    {
        $structures = DB::table('structures')->orderBy('id')->get();
        $parParent = $structures->groupBy(fn ($s) => $s->parent_id ?? 0);
        $types = StructureController::TYPES;

        // Effectifs actifs par structure et par fonction (une nouvelle fonction apparaît d'elle-même).
        $effectifs = [];
        $lignes = DB::table('agents')
            ->leftJoin('fonctions', 'fonctions.id', '=', 'agents.fonction_id')
            ->where('agents.situation', 'actif')->whereNotNull('agents.structure_id')
            ->groupBy('agents.structure_id', 'fonctions.libelle')
            ->get(['agents.structure_id', DB::raw("COALESCE(fonctions.libelle, 'Sans fonction') AS libelle"), DB::raw('COUNT(*) AS n')]);
        foreach ($lignes as $l) {
            $effectifs[$l->structure_id][$l->libelle] = (int) $l->n;
        }

        // Rôles de responsable en cours, d'agents encore en activité.
        $roles = DB::table('habilitations')
            ->join('roles', 'roles.id', '=', 'habilitations.role_id')
            ->join('agents', 'agents.id', '=', 'habilitations.agent_id')
            ->whereIn('roles.code', self::ROLES_RESPONSABLE)
            ->where('agents.situation', 'actif')
            ->whereDate('habilitations.date_debut', '<=', today())
            ->where(fn ($q) => $q->whereNull('habilitations.date_fin')->orWhereDate('habilitations.date_fin', '>=', today()))
            ->orderBy('habilitations.id')
            ->get(['habilitations.agent_id', 'habilitations.structure_id', 'roles.code']);

        // Un Secrétaire Général sans structure précisée est rattaché à la première structure racine du circuit SG.
        $structureSg = optional($structures->first(fn ($s) => $s->parent_id === null && $s->rattachement === 'sg'))->id;

        $chefsIds = [];
        $titulaires = []; // structure_id => [agent_id => code du rôle]
        foreach ($roles as $r) {
            if ($r->code === 'chef_region') {
                $chefsIds[(int) $r->agent_id] = 'chef_region';
                continue;
            }
            $cible = $r->structure_id ?: ($r->code === 'secretaire_general' ? $structureSg : null);
            if ($cible) {
                $titulaires[(int) $cible][(int) $r->agent_id] ??= $r->code;
            }
        }

        $postes = DB::table('postes')->where('actif', 1)->orderBy('id')->get()->groupBy('structure_id');
        $fonctions = DB::table('fonctions')->pluck('libelle', 'id');

        // Une seule requête pour toutes les personnes affichées.
        $ids = array_keys($chefsIds);
        foreach ($titulaires as $liste) {
            $ids = array_merge($ids, array_keys($liste));
        }
        foreach ($postes->flatten(1) as $p) {
            if ($p->titulaire_id) {
                $ids[] = (int) $p->titulaire_id;
            }
        }
        $agents = $ids ? Agent::with('fonction')->whereIn('id', array_unique($ids))->get()->keyBy('id') : collect();

        $anomalies = [];
        $visites = [];
        $stats = ['structures' => 0, 'postes_vacants' => 0, 'sans_responsable' => 0];

        $construire = function (object $s, int $niveau) use (&$construire, &$visites, &$anomalies, &$stats, $parParent, $effectifs, $titulaires, $postes, $fonctions, $agents, $types, $inactives, $maStructureId): ?array {
            if (isset($visites[$s->id])) {
                return null; // garde-fou contre une boucle de parenté
            }
            $visites[$s->id] = true;

            $enfants = [];
            foreach ($parParent->get($s->id, collect()) as $e) {
                if ($n = $construire($e, $niveau + 1)) {
                    $enfants[] = $n;
                }
            }
            if (! $inactives && ! $s->actif && ! $enfants) {
                return null;
            }

            $responsables = [];
            foreach ($titulaires[$s->id] ?? [] as $agentId => $code) {
                if ($a = $agents->get($agentId)) {
                    $responsables[] = ['agent' => $a, 'titre' => self::TITRES[$code] ?? 'Responsable'];
                }
            }

            $listePostes = [];
            foreach ($postes->get($s->id, collect()) as $p) {
                $titulaire = $p->titulaire_id ? $agents->get((int) $p->titulaire_id) : null;
                if ($titulaire && $titulaire->situation !== 'actif') {
                    $titulaire = null;
                }
                $listePostes[] = [
                    'id' => (int) $p->id, 'intitule' => $p->intitule, 'note' => $p->note,
                    'fonction' => $p->fonction_id ? ($fonctions[$p->fonction_id] ?? null) : null,
                    'titulaire' => $titulaire, 'vacant' => $titulaire === null,
                ];
            }

            $fonctionsEffectif = $effectifs[$s->id] ?? [];
            arsort($fonctionsEffectif);
            $direct = array_sum($fonctionsEffectif);
            $vacants = count(array_filter($listePostes, fn ($p) => $p['vacant']));

            $alertes = [];
            if ($s->actif) {
                $stats['structures']++;
                $stats['postes_vacants'] += $vacants;
                if (! $responsables) {
                    $alertes[] = 'Aucun responsable désigné';
                    $stats['sans_responsable']++;
                    $anomalies[] = ['type' => 'sans_responsable', 'structure_id' => (int) $s->id, 'libelle' => $s->nom];
                }
                if ($vacants > 0) {
                    $anomalies[] = ['type' => 'poste_vacant', 'structure_id' => (int) $s->id,
                        'libelle' => $s->nom.' : '.$vacants.' poste'.($vacants > 1 ? 's' : '').' vacant'.($vacants > 1 ? 's' : '')];
                }
            }

            $texte = [$s->nom, $s->code, $types[$s->type] ?? $s->type, $s->type_precision ?? null, ...array_keys($fonctionsEffectif)];
            foreach ($responsables as $r) {
                $texte[] = $r['agent']->nom_complet;
            }
            foreach ($listePostes as $p) {
                $texte[] = $p['intitule'];
                $texte[] = $p['titulaire']?->nom_complet;
            }

            return [
                'id' => (int) $s->id,
                'nom' => $s->nom,
                'code' => $s->code,
                'type' => $s->type,
                'type_libelle' => $s->type === 'autre' && $s->type_precision ? $s->type_precision : ($types[$s->type] ?? $s->type),
                'circuit' => $s->rattachement,
                'circuit_libelle' => CircuitRegles::libelleCourt($s->rattachement),
                'actif' => (bool) $s->actif,
                'niveau' => $niveau,
                'responsables' => $responsables,
                'postes' => $listePostes,
                'postes_vacants' => $vacants,
                'fonctions' => $fonctionsEffectif,
                'effectif' => $direct,
                'effectif_total' => $direct + array_sum(array_column($enfants, 'effectif_total')),
                'alertes' => $alertes,
                'enfants' => $enfants,
                'moi' => $maStructureId !== null && (int) $s->id === $maStructureId,
                'contient_moi' => ($maStructureId !== null && (int) $s->id === $maStructureId)
                    || (bool) array_filter($enfants, fn ($e) => $e['contient_moi']),
                'recherche' => implode(' ', array_filter($texte)),
            ];
        };

        $racines = [];
        foreach ($parParent->get(0, collect()) as $s) {
            if ($n = $construire($s, 1)) {
                $racines[] = $n;
            }
        }

        // Structures jamais atteintes depuis le sommet (boucle de parenté) : à corriger.
        $orphelines = $structures->filter(fn ($s) => ! isset($visites[$s->id]))->pluck('nom')->values()->all();

        $chefs = [];
        foreach (array_keys($chefsIds) as $agentId) {
            if ($a = $agents->get($agentId)) {
                $chefs[] = ['agent' => $a, 'titre' => self::TITRES['chef_region']];
            }
        }

        $stats['agents'] = Agent::actifs()->count();

        return ['chefs' => $chefs, 'racines' => $racines, 'stats' => $stats, 'anomalies' => $anomalies, 'orphelines' => $orphelines];
    }

    /** Nombre de structures placées sous ce nœud, à tous les niveaux. */
    public static function descendants(array $noeud): int
    {
        $n = 0;
        foreach ($noeud['enfants'] as $e) {
            $n += 1 + self::descendants($e);
        }

        return $n;
    }
}
