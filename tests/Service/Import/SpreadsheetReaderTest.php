<?php

namespace App\Tests\Service\Import;

use App\Service\Import\ImportReadException;
use App\Service\Import\SpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class SpreadsheetReaderTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../fixtures/imports/' . $name);
    }

    public function testReadsSemicolonCsv(): void
    {
        $sheet = (new SpreadsheetReader())->read($this->fixture('materiel-semicolon.csv'), 'csv');

        $this->assertSame(5, $sheet->rowCount());
        $this->assertSame(4, $sheet->columnCount());
        $this->assertSame(['Immatriculation', 'Type', 'Modèle', 'Propriétaire'], $sheet->rows()[0]);
        $this->assertSame('', $sheet->rows()[4][2]);
    }

    public function testReadsCommaCsvWithoutHeader(): void
    {
        $sheet = (new SpreadsheetReader())->read($this->fixture('materiel-comma-noheader.csv'), 'csv');

        $this->assertSame(2, $sheet->rowCount());
        $this->assertSame(['F-CGAB', 'Planeur', 'ASK 21'], $sheet->rows()[0]);
    }

    public function testConvertsWindows1252(): void
    {
        $sheet = (new SpreadsheetReader())->read($this->fixture('materiel-cp1252.csv'), 'csv');

        $this->assertSame('Pégase', $sheet->rows()[1][0]);
    }

    public function testReadsXlsx(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['Nom', 'Type'], ['F-CGAB', 'Planeur'], [null, null]]);
        $path = tempnam(sys_get_temp_dir(), 'import') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $sheet = (new SpreadsheetReader())->read((string) file_get_contents($path), 'xlsx');
        unlink($path);

        $this->assertSame(2, $sheet->rowCount(), 'les lignes entièrement vides sont ignorées');
        $this->assertSame('F-CGAB', $sheet->rows()[1][0]);
    }

    public function testSamplesAndDistinctValues(): void
    {
        $sheet = (new SpreadsheetReader())->read($this->fixture('materiel-semicolon.csv'), 'csv');

        $this->assertSame(['Planeur', 'Avion', 'Treuil'], $sheet->distinctValues(1, 20, skipFirstRow: true));
        $this->assertSame(['ASK 21', 'Pégase', 'DR 400'], $sheet->sample(2, 3, skipFirstRow: true));
        $this->assertSame(['Immatriculation', 'Type', 'Modèle', 'Propriétaire'], $sheet->columnLabels(true));
        $this->assertSame(['A', 'B', 'C', 'D'], $sheet->columnLabels(false));
    }

    public function testRejectsUnknownExtension(): void
    {
        $this->expectException(ImportReadException::class);
        (new SpreadsheetReader())->read('x', 'pdf');
    }
}
