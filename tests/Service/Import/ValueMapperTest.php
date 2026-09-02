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

    public function testYesNoValuesMatchExactlyBeforeInclusion(): void
    {
        $field = new ImportField('isManager', 'manager', false, ['gestionnaire'], [
            new ImportChoice('yes', 'yes', ['oui', 'yes', 'x', '1', 'vrai', 'true']),
            new ImportChoice('no', 'no', ['non', 'no', '0', 'faux', 'false']),
        ]);

        $suggestion = (new ValueMapper())->suggest(['faux', 'FAUX', 'vrai', 'Expert', 'x', 'X'], $field);

        $this->assertSame([
            'faux' => 'no',
            'FAUX' => 'no',
            'vrai' => 'yes',
            'Expert' => null,
            'x' => 'yes',
            'X' => 'yes',
        ], $suggestion);
    }
}
