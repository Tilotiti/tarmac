<?php

namespace App\Tests\Service\Import;

use App\Service\Import\ImportChoice;
use App\Service\Import\ImportField;
use App\Service\Import\ValueMapper;
use PHPUnit\Framework\TestCase;

class ValueMapperTest extends TestCase
{
    public function testSuggestsChoiceBySynonym(): void
    {
        $field = new ImportField('type', 'type', true, ['type'], [
            new ImportChoice('glider', 'glider', ['planeur', 'glider', 'motoplaneur']),
            new ImportChoice('airplane', 'airplane', ['avion', 'ulm', 'remorqueur']),
            new ImportChoice('facility', 'infrastructure', ['treuil', 'remorque', 'hangar', 'infra']),
        ]);

        $suggestion = (new ValueMapper())->suggest(['PLANEUR', 'Avion remorqueur', 'Treuil n°2', 'Cuisine'], $field);

        $this->assertSame(['PLANEUR' => 'glider', 'Avion remorqueur' => 'airplane', 'Treuil n°2' => 'facility', 'Cuisine' => null], $suggestion);
    }
}
