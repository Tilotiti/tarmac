<?php

namespace App\Tests\Entity;

use App\Entity\Aerodrome;
use PHPUnit\Framework\TestCase;

class AerodromeTest extends TestCase
{
    public function testLabelOmitsMunicipalityWhenNameContainsIt(): void
    {
        $aerodrome = (new Aerodrome())
            ->setName('Megève Altiport')
            ->setMunicipality('Megève')
            ->setIcaoCode('LFHM');

        $this->assertSame('Megève Altiport · LFHM', $aerodrome->getLabel());
    }

    public function testLabelAddsMunicipalityAndOmitsMissingIcao(): void
    {
        $withMunicipality = (new Aerodrome())
            ->setName('Altiport')
            ->setMunicipality('Megève');

        $this->assertSame('Altiport (Megève)', $withMunicipality->getLabel());

        $withoutMunicipality = (new Aerodrome())
            ->setName('Bailleau')
            ->setIcaoCode('LFFL');

        $this->assertSame('Bailleau · LFFL', $withoutMunicipality->getLabel());
    }

    public function testNormalizeForSearchStripsAccentsAndCase(): void
    {
        $this->assertSame('megeve altiport', Aerodrome::normalizeForSearch('  Megève   ALTIPORT '));
    }

    public function testRefreshSearchIndexConcatenatesNameMunicipalityIcao(): void
    {
        $aerodrome = (new Aerodrome())
            ->setName('Megève Altiport')
            ->setMunicipality('Megève')
            ->setIcaoCode('LFHM')
            ->refreshSearchIndex();

        $this->assertSame('megeve altiport megeve lfhm', $aerodrome->getSearchIndex());
    }
}
