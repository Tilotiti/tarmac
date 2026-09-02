<?php

namespace App\Tests\Service\Import;

use App\Service\Import\HeaderDetector;
use App\Service\Import\ImportSheet;
use PHPUnit\Framework\TestCase;

class HeaderDetectorTest extends TestCase
{
    public function testDetectsTextualHeader(): void
    {
        $sheet = new ImportSheet([['Immatriculation', 'Type', 'Heures'], ['F-CGAB', 'Planeur', '1200'], ['F-CHXY', 'Planeur', '340']]);

        $this->assertTrue((new HeaderDetector())->looksLikeHeader($sheet));
    }

    public function testRejectsNumericOrDuplicateFirstRow(): void
    {
        $this->assertFalse((new HeaderDetector())->looksLikeHeader(new ImportSheet([['F-CGAB', 'Planeur', '1200'], ['F-CHXY', 'Planeur', '340']])));
        $this->assertFalse((new HeaderDetector())->looksLikeHeader(new ImportSheet([['Nom', 'Nom'], ['a', 'b']])));
        $this->assertFalse((new HeaderDetector())->looksLikeHeader(new ImportSheet([['Nom', ''], ['a', 'b']])));
    }

    public function testSingleRowIsNotAHeader(): void
    {
        $this->assertFalse((new HeaderDetector())->looksLikeHeader(new ImportSheet([['F-CGAB', 'Planeur']])));
    }

    public function testRejectsLongCells(): void
    {
        $longCell = str_repeat('a', 61);
        $sheet = new ImportSheet([[$longCell, 'Type'], ['F-CGAB', 'Planeur']]);

        $this->assertFalse((new HeaderDetector())->looksLikeHeader($sheet));
    }
}
