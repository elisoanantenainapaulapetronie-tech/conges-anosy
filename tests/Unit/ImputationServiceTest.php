<?php

namespace Tests\Unit;

use App\Services\AcquisitionService;
use App\Services\DecompteService;
use App\Services\ImputationService;
use PHPUnit\Framework\TestCase;

/** Tests du calcul pur des lots et de l'acquisition (sans base de données) : php artisan test --filter=ImputationServiceTest */
class ImputationServiceTest extends TestCase
{
    private const FERIES = ['2026-12-25' => 'Noël', '2027-01-01' => 'Jour de l’An'];

    private function jours(string $debut, string $fin, string $demiDebut = 'journee', string $demiFin = 'journee'): array
    {
        return ImputationService::joursDecomptes(DecompteService::calculer($debut, $fin, $demiDebut, $demiFin, self::FERIES));
    }

    public function test_premier_mois_d_acquisition(): void
    {
        $this->assertSame('2026-03', AcquisitionService::premierMois('2026-03-01'));
        $this->assertSame('2026-12', AcquisitionService::premierMois('2026-11-15'));
        $this->assertSame('2027-01', AcquisitionService::premierMois('2026-12-31'));
    }

    public function test_mois_acquis_selon_entree_et_sortie(): void
    {
        $this->assertFalse(AcquisitionService::moisAcquis('2026-03-01', null, '2026-02'));
        $this->assertTrue(AcquisitionService::moisAcquis('2026-03-01', null, '2026-03'));
        $this->assertFalse(AcquisitionService::moisAcquis('2020-01-01', '2026-06-15', '2026-06'));
        $this->assertTrue(AcquisitionService::moisAcquis('2020-01-01', '2026-06-30', '2026-06'));
        $this->assertSame(2.5, AcquisitionService::mensualite(30.0));
    }

    public function test_le_plus_ancien_lot_est_consomme_en_premier(): void
    {
        $r = ImputationService::repartir($this->jours('2026-12-28', '2027-01-08'), [2025 => 3.0, 2026 => 10.0], [2027 => ['2027-01' => 2.5]]);
        $this->assertSame(3.0, $r['lots'][2025]['total']);
        $this->assertSame(6.0, $r['lots'][2026]['total']);
        $this->assertArrayNotHasKey(2027, $r['lots']);
        $this->assertSame(0.0, $r['manque']);
    }

    public function test_un_lot_ne_couvre_jamais_une_annee_anterieure(): void
    {
        $r = ImputationService::repartir($this->jours('2026-12-07', '2026-12-08'), [2027 => 30.0]);
        $this->assertSame(2.0, $r['manque']);
        $this->assertNull($r['couvert_jusqu']);
    }

    public function test_manque_et_date_atteignable(): void
    {
        $r = ImputationService::repartir($this->jours('2026-12-28', '2027-01-18'), [2026 => 12.0], [2027 => ['2027-01' => 2.5]]);
        $this->assertSame(12.0, $r['lots'][2026]['reel']);
        $this->assertSame(2.5, $r['lots'][2027]['provisoire']);
        $this->assertSame(0.5, $r['manque']);
        $this->assertSame('2027-01-15', $r['couvert_jusqu']);
        $this->assertSame('Solde insuffisant : il manque 0,5 jour. Votre solde couvre jusqu’au 15/01/2027.', ImputationService::messageManque($r));
    }

    public function test_les_jours_a_venir_ne_comptent_que_dans_leur_mois(): void
    {
        // Le crédit de janvier 2027 n'existe pas pour un jour de décembre 2026.
        $r = ImputationService::repartir($this->jours('2026-12-07', '2026-12-09'), [], [2027 => ['2027-01' => 2.5]]);
        $this->assertSame(3.0, $r['manque']);
    }

    public function test_demandes_en_attente_deja_engagees(): void
    {
        $r = ImputationService::repartir($this->jours('2026-12-07', '2026-12-18'), [2026 => 12.0], [], [2026 => 5.0]);
        $this->assertSame(3.0, $r['manque']);
        $this->assertSame('2026-12-15', $r['couvert_jusqu']);
    }

    public function test_demi_journees(): void
    {
        $r = ImputationService::repartir($this->jours('2026-12-07', '2026-12-11', 'apres_midi', 'matin'), [2026 => 10.0]);
        $this->assertSame(4.0, $r['lots'][2026]['reel']);
    }
}
