<?php

namespace Tests\Unit;

use App\Services\DecompteService;
use App\Services\JoursFeriesService;
use App\Services\SoldeService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Tests du calcul pur (sans base de données) : php artisan test --filter=DecompteServiceTest */
class DecompteServiceTest extends TestCase
{
    public function test_exemple_fil_rouge_dix_jours(): void
    {
        $r = DecompteService::calculer('2026-12-07', '2026-12-18', 'journee', 'journee', JoursFeriesService::proposer(2026));
        $this->assertSame(10.0, $r['total']);
    }

    public function test_semaine_complete_et_avec_ferie(): void
    {
        $this->assertSame(5.0, DecompteService::calculer('2026-03-23', '2026-03-27')['total']);
        $this->assertSame(4.0, DecompteService::calculer('2026-03-23', '2026-03-27', 'journee', 'journee', ['2026-03-27' => 'Férié'])['total']);
    }

    public function test_demi_journees(): void
    {
        $this->assertSame(4.0, DecompteService::calculer('2026-03-23', '2026-03-27', 'apres_midi', 'matin')['total']);
        $this->assertSame(0.5, DecompteService::calculer('2026-03-24', '2026-03-24', 'apres_midi')['total']);
        $this->assertSame(0.5, DecompteService::calculer('2026-03-24', '2026-03-24', 'journee', 'matin')['total']);
    }

    public function test_periode_a_cheval_sur_deux_annees(): void
    {
        $r = DecompteService::calculer('2026-12-28', '2027-01-05', 'journee', 'journee', ['2027-01-01' => 'Jour de l’An']);
        $this->assertSame(6.0, $r['total']);
        $this->assertSame(4.0, $r['par_annee'][2026]);
        $this->assertSame(2.0, $r['par_annee'][2027]);
    }

    public function test_week_end_et_dates_invalides(): void
    {
        $this->assertSame(0.0, DecompteService::calculer('2026-03-28', '2026-03-29')['total']);
        $this->expectException(InvalidArgumentException::class);
        DecompteService::calculer('2026-03-24', '2026-03-23');
    }

    public function test_meme_jour_apres_midi_et_matin_refuse(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DecompteService::calculer('2026-03-24', '2026-03-24', 'apres_midi', 'matin');
    }

    public function test_delais_en_jours_ouvres(): void
    {
        $this->assertSame('2026-03-24', DecompteService::ajouterJoursOuvres('2026-03-20', 2)->format('Y-m-d'));
        $this->assertSame('2026-03-25', DecompteService::ajouterJoursOuvres('2026-03-20', 2, ['2026-03-23' => 'x'])->format('Y-m-d'));
    }

    public function test_paques_et_fetes_mobiles(): void
    {
        $this->assertSame('2026-04-05', JoursFeriesService::paques(2026)->format('Y-m-d'));
        $this->assertSame('2027-03-28', JoursFeriesService::paques(2027)->format('Y-m-d'));
        $f = JoursFeriesService::proposer(2026);
        $this->assertSame('Ascension', $f['2026-05-14']);
        $this->assertSame('Lundi de Pentecôte', $f['2026-05-25']);
    }

    public function test_acquisition_mensuelle(): void
    {
        $this->assertSame(10, SoldeService::moisTravailles('2026-03-01', null, 2026));
        $this->assertSame(25.0, SoldeService::droitAnnee('2026-03-01', null, 2026, 30.0));
        $this->assertSame(30.0, SoldeService::droitAnnee('2020-01-15', null, 2026, 30.0));
        $this->assertSame(9, SoldeService::moisTravailles('2026-03-15', null, 2026));          // mois entamé non compté
        $this->assertSame(6, SoldeService::moisTravailles('2020-01-01', '2026-06-30', 2026));  // sortie fin juin
        $this->assertSame(0, SoldeService::moisTravailles('2027-01-01', null, 2026));
    }
}
