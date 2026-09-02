<?php

namespace App\Tests\Service\Import;

use App\Service\Import\ImportState;
use PHPUnit\Framework\TestCase;

class ImportStateTest extends TestCase
{
    public function testRoundTripsThroughArray(): void
    {
        $state = new ImportState('equipments', 'imports/1/x.csv', 'materiel.csv', 'csv', true);
        $state->columns = ['name' => 0, 'type' => 1, 'description' => null];
        $state->values = ['type' => ['Planeur' => 'glider']];
        $state->fixed = ['owner' => 'club'];
        $state->extra = ['distinctCounts' => ['type' => 25]];

        $copy = ImportState::fromArray($state->toArray());

        $this->assertEquals($state, $copy);
        $this->assertSame(1, $copy->columnFor('type'));
        $this->assertNull($copy->columnFor('missing'));
        $this->assertSame(25, $copy->extra['distinctCounts']['type']);
    }
}
