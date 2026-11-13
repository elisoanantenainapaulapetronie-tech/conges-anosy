<?php

namespace Tests\Unit;

use App\Support\NombreSaisi;
use PHPUnit\Framework\TestCase;

class NombreSaisiTest extends TestCase
{
    public function test_formats_acceptes(): void
    {
        foreach (['2,5' => '2.5', '2.5' => '2.5', '-1,5' => '-1.5', "\u{2212}1,5" => '-1.5', '+3' => '3', ' 1 000 ' => '1000', '0,5' => '0.5'] as $in => $out) {
            $this->assertSame($out, NombreSaisi::normaliser($in), "« {$in} »");
        }
    }

    public function test_formats_refuses(): void
    {
        foreach (['abc', '', '1,2,3', '2,5,', '--1', '1e3'] as $in) {
            $this->assertNull(NombreSaisi::normaliser($in), "« {$in} »");
        }
        $this->assertNull(NombreSaisi::normaliser(null));
    }
}
