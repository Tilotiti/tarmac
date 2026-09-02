<?php

namespace App\Tests\Service\Import;

use App\Service\Import\ColumnMapper;
use App\Service\Import\ImportField;
use PHPUnit\Framework\TestCase;

class ColumnMapperTest extends TestCase
{
    public function testSuggestsBySynonymsIgnoringAccentsAndCase(): void
    {
        $fields = [
            new ImportField('name', 'name', true, ['nom', 'immatriculation', 'immat']),
            new ImportField('type', 'type', true, ['type', 'categorie']),
            new ImportField('description', 'description', false, ['description', 'modele']),
        ];

        $suggestion = (new ColumnMapper())->suggest(['IMMATRICULATION', 'Catégorie', 'Modèle', 'Propriétaire'], $fields);

        $this->assertSame(['name' => 0, 'type' => 1, 'description' => 2], $suggestion);
    }

    public function testEachColumnIsUsedOnceAndUnknownStaysNull(): void
    {
        $fields = [
            new ImportField('name', 'name', true, ['nom']),
            new ImportField('lastname', 'lastname', true, ['nom']),
        ];

        $suggestion = (new ColumnMapper())->suggest(['Nom', 'Autre'], $fields);

        $this->assertSame(['name' => 0, 'lastname' => null], $suggestion);
    }

    public function testLettersNeverMatch(): void
    {
        $this->assertSame(['name' => null], (new ColumnMapper())->suggest(['A', 'B'], [new ImportField('name', 'name', true, ['nom'])]));
    }

    public function testExactMatchesWinOverSubstringsRegardlessOfFieldOrder(): void
    {
        $fields = [
            new ImportField('lastname', 'lastname', true, ['nom']),
            new ImportField('firstname', 'firstname', true, ['prenom']),
        ];

        $suggestion = (new ColumnMapper())->suggest(['Prénom', 'Nom'], $fields);

        $this->assertSame(['lastname' => 1, 'firstname' => 0], $suggestion);
    }
}
