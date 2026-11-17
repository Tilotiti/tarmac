<?php

namespace App\Tests\Service\Aerodrome;

use App\Service\Aerodrome\AerodromeCsvParser;
use PHPUnit\Framework\TestCase;

class AerodromeCsvParserTest extends TestCase
{
    public function testKeepsOnlyFrenchAirportsOfAcceptedTypes(): void
    {
        $rows = iterator_to_array((new AerodromeCsvParser())->parse(__DIR__ . '/../../fixtures/airports-sample.csv'), false);

        $this->assertCount(3, $rows);
        $this->assertSame([4145, 4104, 28915], array_column($rows, 'sourceId'));
    }

    public function testMapsColumnsAndNullsEmptyValues(): void
    {
        $rows = iterator_to_array((new AerodromeCsvParser())->parse(__DIR__ . '/../../fixtures/airports-sample.csv'), false);

        $this->assertSame('Megève Altiport', $rows[0]['name']);
        $this->assertSame('Megève', $rows[0]['municipality']);
        $this->assertSame('LFHM', $rows[0]['icaoCode']);
        $this->assertEqualsWithDelta(45.8208, $rows[0]['latitude'], 0.0001);
        $this->assertNull($rows[1]['municipality']);
    }

    public function testFallsBackOnGpsCodeAndFixesKnownSourceErrors(): void
    {
        $rows = iterator_to_array((new AerodromeCsvParser())->parse(__DIR__ . '/../../fixtures/airports-sample.csv'), false);

        $this->assertSame('LFJT', $rows[2]['icaoCode']);
        $this->assertSame('Le Louroux', $rows[2]['municipality']);
    }

    public function testFailsOnMissingColumn(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'airports');
        file_put_contents($path, "\"id\",\"name\"\n1,\"X\"\n");

        $this->expectException(\RuntimeException::class);
        iterator_to_array((new AerodromeCsvParser())->parse($path), false);
    }
}
