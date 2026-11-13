<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * php artisan conges:verifier-tout
 * Lance en une fois tous les contrôles automatiques (lots 0 à 5 quater + cohérence du registre) et résume.
 * Un contrôle absent du projet est signalé « ignoré », jamais en échec. Ne modifie rien durablement
 * (les scénarios des vérificateurs s'annulent d'eux-mêmes).
 * Option --detail : affiche aussi les lignes [OK] de chaque contrôle.
 */
class VerifierTout extends Command
{
    protected $signature = 'conges:verifier-tout {--detail : afficher toutes les lignes}';

    protected $description = 'Lance tous les contrôles automatiques de l’application et affiche un résumé';

    private const CONTROLES = [
        'lot0:verifier', 'lot1bis:verifier', 'lot3:verifier', 'lot4:verifier',
        'lot5:verifier', 'lot5ter:verifier', 'lot5quater:verifier', 'conges:reconcilier',
    ];

    public function handle(): int
    {
        $disponibles = array_keys(Artisan::all());
        $echecs = 0;
        $lances = 0;

        foreach (self::CONTROLES as $commande) {
            if (! in_array($commande, $disponibles, true)) {
                $this->line("  [IGNORE]  {$commande} : commande absente du projet");
                continue;
            }

            $sortie = new BufferedOutput();
            try {
                $code = Artisan::call($commande, [], $sortie);
            } catch (\Throwable $e) {
                $code = 1;
                $sortie->writeln('[ECHEC] Exception : '.$e->getMessage());
            }

            $texte = $sortie->fetch();
            $lignes = preg_split('/\R/', $texte) ?: [];
            $mauvaises = array_values(array_filter($lignes, fn ($l) => str_contains($l, 'ECHEC') || str_contains($l, 'ANOMALIE')));
            $ok = $code === 0 && $mauvaises === [];
            $lances++;

            $this->line(($ok ? '  [OK]      ' : '  [ECHEC]   ').$commande);
            if (! $ok) {
                $echecs++;
                foreach ($mauvaises ?: array_slice($lignes, -5) as $l) {
                    if (trim($l) !== '') {
                        $this->line('            '.trim($l));
                    }
                }
            } elseif ($this->option('detail')) {
                foreach ($lignes as $l) {
                    if (trim($l) !== '') {
                        $this->line('            '.trim($l));
                    }
                }
            }
        }

        $this->newLine();
        $this->line($echecs === 0
            ? "Tous les contrôles passent ({$lances} lancés)."
            : "{$echecs} contrôle(s) en échec sur {$lances}. Relancez-en un seul pour le détail, par exemple : php artisan lot5:verifier");

        return $echecs === 0 ? self::SUCCESS : self::FAILURE;
    }
}
