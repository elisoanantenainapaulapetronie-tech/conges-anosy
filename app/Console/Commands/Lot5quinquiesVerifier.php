<?php

namespace App\Console\Commands;

use App\Exceptions\SoldeException;
use App\Models\Agent;
use App\Services\ImputationService;
use App\Services\NotificationService;
use App\Services\SoldeJustificatifService;
use App\Services\SoldeService;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * php artisan lot5quinquies:verifier
 * Contrôle le lot 5 quinquies : centre de notifications, statut « retirée », historique des corrections,
 * justificatifs (fichiers), report rattaché à l'année d'origine, affichage distinct du solde.
 * Les scénarios s'exécutent dans une transaction ANNULÉE ; les fichiers de test sont supprimés. Aucun e-mail n'est envoyé.
 */
class Lot5quinquiesVerifier extends Command
{
    protected $signature = 'lot5quinquies:verifier';

    protected $description = 'Vérifie le lot 5 quinquies (notifications, retrait, historique, justificatifs, report) sans rien conserver';

    private int $echecs = 0;

    /** @var string[] fichiers créés par le test, supprimés à la fin */
    private array $fichiers = [];

    public function handle(): int
    {
        $this->info('--- Base de données ---');
        $this->controle('notifications.lien', Schema::hasColumn('notifications', 'lien'));
        $this->controle('soldes_propositions.retire_le et motif_retrait', Schema::hasColumn('soldes_propositions', 'retire_le') && Schema::hasColumn('soldes_propositions', 'motif_retrait'));
        $enum = (string) (DB::selectOne("SHOW COLUMNS FROM soldes_propositions LIKE 'statut'")->Type ?? '');
        $this->controle('Statut « retiree » présent', str_contains($enum, 'retiree'));
        $this->controle('Table pieces_jointes (justificatifs)', Schema::hasColumn('pieces_jointes', 'proposition_id'));

        $this->info('--- Routes ---');
        foreach (['notifications', 'notifications.ouvrir', 'notifications.lue', 'notifications.tout-lire', 'rh.soldes.retirer', 'rh.historique-corrections', 'rh.soldes.justificatif'] as $nom) {
            $this->controle("route {$nom}", Route::has($nom));
        }
        $mw = implode(',', Route::getRoutes()->getByName('rh.soldes.justificatif')?->gatherMiddleware() ?? []);
        $this->controle('Un justificatif n\'est servi qu\'à une personne connectée ayant un rôle de contrôle', str_contains($mw, 'auth') && str_contains($mw, 'role:'));

        $this->info('--- Liens des notifications (chemin interne uniquement) ---');
        foreach ([['/solde', '/solde'], ['https://exemple.mg/rh/soldes?x=1', '/rh/soldes?x=1'], ['//evil.test/x', null], ['javascript:alert(1)', null], ['', null], [null, null]] as [$entree, $attendu]) {
            $this->controle('Lien « '.var_export($entree, true).' » → '.var_export($attendu, true), NotificationService::cheminInterne($entree) === $attendu);
        }

        $this->info('--- Justificatifs : type réel du fichier ---');
        $this->controle('PDF reconnu', $this->type("%PDF-1.4\n...") === 'application/pdf');
        $this->controle('JPEG reconnu', $this->type("\xFF\xD8\xFF\xE0abc") === 'image/jpeg');
        $this->controle('PNG reconnu', $this->type("\x89PNG\r\n\x1A\nabc") === 'image/png');
        $this->controle('Un script renommé en .pdf est refusé', $this->type("<?php echo 1;") === null);
        $this->controle('Un fichier vide est refusé', $this->type('') === null);

        $this->info('--- Report, retrait, historique (transaction annulée) ---');
        DB::beginTransaction();
        try {
            $this->scenario();
        } catch (Throwable $e) {
            $this->controle('Scénario terminé sans erreur : '.$e->getMessage(), false);
        } finally {
            DB::rollBack();
            foreach ($this->fichiers as $f) {
                Storage::disk('local')->delete($f);
            }
        }

        $this->newLine();
        $this->echecs === 0 ? $this->info('Tout est conforme (rien n\'a été conservé).') : $this->error($this->echecs.' contrôle(s) en échec.');

        return $this->echecs === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function scenario(): void
    {
        $structure = DB::table('structures')->value('id');
        if (! $structure) {
            $this->line('  [IGNORE] Aucune structure : lancer les seeders de démonstration.');

            return;
        }
        $nouveau = fn (string $m) => Agent::create([
            'matricule' => $m, 'nom' => 'TESTLOT5QUINQ', 'prenoms' => $m, 'sexe' => 'M', 'structure_id' => $structure,
            'rattachement' => 'sg', 'date_entree' => '2020-01-01', 'situation' => 'actif',
        ]);
        $cible = $nouveau('T5Q5-A');
        $auteur = $nouveau('T5Q5-B');
        $autre = $nouveau('T5Q5-C');

        // Report = solde initial de l'année d'origine
        $this->refuse('Le report n\'est plus une proposition (il ne crée pas de jours)', fn () => SoldeService::proposer($auteur, $cible, 2025, 'report', 5, 'Report 2025', 'Décision'));
        $id = SoldeService::proposer($auteur, $cible, 2024, 'solde_initial', 5, 'Reliquat 2024 conservé dans son lot', 'Registre des congés 2024');
        SoldeService::controler($autre, $id, true, 'Conforme au registre');
        $s = SoldeService::situation($cible->id, 2024);
        $this->controle('Reliquat 2024 : inscrit sur le lot 2024 (solde initial 5, acquisitions 0)', $s['initial'] === 5.0 && $s['acquisitions'] === 0.0 && $s['acquis'] === 5.0 && $s['disponible'] === 5.0);
        $this->controle('Aucun jour ne se crée sur le lot 2025', ! isset(ImputationService::lots($cible->id)[2025]));
        $this->controle('L\'observation du contrôleur est conservée à la validation', DB::table('soldes_propositions')->where('id', $id)->value('commentaire_controle') === 'Conforme au registre');
        $this->refuse('Un second solde initial 2024 est refusé (pas de double crédit)', fn () => SoldeService::proposer($auteur, $cible, 2024, 'solde_initial', 5, 'Doublon', 'Registre'));

        // Retrait
        $p = SoldeService::proposer($auteur, $cible, 2024, 'correction', 1.5, 'Régularisation à retirer', 'Note n° 7');
        $this->refuse('Seul l\'auteur retire sa proposition', fn () => SoldeService::retirer($autre, $p, 'Pas à moi'));
        $this->refuse('Le motif du retrait est obligatoire', fn () => SoldeService::retirer($auteur, $p, ' '));
        SoldeService::retirer($auteur, $p, 'Erreur de saisie');
        $ligne = DB::table('soldes_propositions')->where('id', $p)->first();
        $this->controle('Proposition retirée : statut, date et motif conservés', $ligne->statut === 'retiree' && $ligne->retire_le !== null && $ligne->motif_retrait === 'Erreur de saisie');
        $this->controle('Retirer ne touche pas au registre', SoldeService::situation($cible->id, 2024)['disponible'] === 5.0 && ! DB::table('soldes_mouvements')->where('proposition_id', $p)->exists());
        $this->refuse('Une proposition retirée ne peut plus être contrôlée', fn () => SoldeService::controler($autre, $p, true));
        $this->refuse('Une proposition retirée ne se retire pas deux fois', fn () => SoldeService::retirer($auteur, $p, 'Encore'));
        $this->refuse('Une proposition déjà validée ne se retire pas', fn () => SoldeService::retirer($auteur, $id, 'Trop tard'));
        try {
            DB::table('soldes_propositions')->where('id', $p)->update(['statut' => 'retiree', 'retire_le' => null]);
            $this->controle('La base refuse un retrait sans date', false);
        } catch (Throwable) {
            $this->controle('La base refuse un retrait sans date', true);
        }

        // Rejet et historique
        $r = SoldeService::proposer($auteur, $cible, 2024, 'correction', -2, 'Retrait à rejeter', 'Note n° 8');
        $this->refuse('Un rejet exige un motif', fn () => SoldeService::controler($autre, $r, false, ''));
        SoldeService::controler($autre, $r, false, 'Pièce illisible');
        $attente = SoldeService::propositionsEnAttente($cible->id)->pluck('id')->all();
        $this->controle('Les propositions traitées ne sont plus « en attente »', ! in_array($id, $attente, true) && ! in_array($p, $attente, true) && ! in_array($r, $attente, true));
        $histo = SoldeService::historique(['agent' => 'T5Q5-A'])->get()->keyBy('id');
        $this->controle('L\'historique contient la validée, la retirée et la rejetée', $histo->has($id) && $histo->has($p) && $histo->has($r));
        $this->controle('L\'historique indique le contrôleur et l\'observation', ($histo[$r]->commentaire_controle ?? '') === 'Pièce illisible' && ($histo[$r]->controleur_prenoms ?? '') === 'T5Q5-C');
        $this->controle('Filtre « retirées » : une seule ligne', SoldeService::historique(['agent' => 'T5Q5-A', 'statut' => 'retiree'])->count() === 1);
        $this->controle('Filtre « rejetées » : une seule ligne', SoldeService::historique(['agent' => 'T5Q5-A', 'statut' => 'rejete'])->count() === 1);

        // Justificatif
        $tmp = tempnam(sys_get_temp_dir(), 'j5q');
        file_put_contents($tmp, "%PDF-1.4\n%test\n");
        $pieceId = SoldeJustificatifService::attacher($r, new UploadedFile($tmp, 'Décision n°12 (2024).pdf', 'application/pdf', null, true), $auteur);
        $piece = DB::table('pieces_jointes')->where('id', $pieceId)->first();
        $this->fichiers[] = $piece->chemin;
        $this->controle('Justificatif enregistré hors de public/, sous un nom aléatoire', Storage::disk('local')->exists($piece->chemin) && str_starts_with($piece->chemin, SoldeJustificatifService::DOSSIER.'/') && ! str_contains($piece->chemin, 'Décision'));
        $this->controle('Type, taille et auteur du dépôt conservés', $piece->mime === 'application/pdf' && (int) $piece->taille_octets > 0 && (int) $piece->depose_par === (int) $auteur->id);
        $this->controle('Le justificatif est retrouvé par proposition', isset(SoldeJustificatifService::parProposition([$r])[$r]));
        file_put_contents($tmp, '<?php echo 1;');
        try {
            SoldeJustificatifService::attacher($r, new UploadedFile($tmp, 'faux.pdf', 'application/pdf', null, true), $auteur);
            $this->controle('Un faux PDF est refusé', false);
        } catch (\RuntimeException) {
            $this->controle('Un faux PDF est refusé', true);
        }
        @unlink($tmp);
    }

    private function type(string $contenu): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'j5q');
        file_put_contents($tmp, $contenu);
        $t = SoldeJustificatifService::typeReel($tmp);
        @unlink($tmp);

        return $t;
    }

    private function refuse(string $libelle, \Closure $action): void
    {
        try {
            $action();
            $this->controle($libelle, false);
        } catch (SoldeException) {
            $this->controle($libelle, true);
        }
    }

    private function controle(string $libelle, bool $ok): void
    {
        $this->line(($ok ? '  [OK]     ' : '  [ECHEC]  ').$libelle);
        if (! $ok) {
            $this->echecs++;
        }
    }
}
