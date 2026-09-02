<?php

namespace App\Tests\Service\Import;

use App\Entity\Club;
use App\Entity\User;
use App\Service\Import\ImportChoice;
use App\Service\Import\ImportContext;
use App\Service\Import\ImportDefinitionInterface;
use App\Service\Import\ImportField;
use App\Service\Import\ImportRowResolver;
use App\Service\Import\ImportSheet;
use App\Service\Import\ImportState;
use PHPUnit\Framework\TestCase;

class ImportRowResolverTest extends TestCase
{
    private function definition(): ImportDefinitionInterface
    {
        return new class implements ImportDefinitionInterface {
            public array $imported = [];
            public function key(): string { return 'fake'; }
            public function titleKey(): string { return 'fake'; }
            public function listRoute(): string { return 'club_dashboard'; }
            public function previewTemplate(): string { return 'club/import/preview.html.twig'; }
            public function fields(): array
            {
                return [
                    new ImportField('name', 'name', true, ['nom']),
                    new ImportField('type', 'type', true, ['type'], [new ImportChoice('glider', 'glider', ['planeur']), new ImportChoice('airplane', 'airplane', ['avion'])]),
                    new ImportField('note', 'note', false, ['note']),
                ];
            }
            public function previewFields(): array { return $this->fields(); }
            public function validate(array $values, ImportContext $context): ?string
            {
                return $context->markSeen('name:' . mb_strtolower($values['name'])) ? null : 'importReasonDuplicateInFile';
            }
            public function import(array $values, ImportContext $context): void { $this->imported[] = $values; }
        };
    }

    private function state(bool $hasHeader = true): ImportState
    {
        $state = new ImportState('fake', 'k', 'f.csv', 'csv', $hasHeader);
        $state->columns = ['name' => 0, 'type' => 1, 'note' => null];
        $state->values = ['type' => ['Planeur' => 'glider', 'Avion' => 'airplane', 'Cuisine' => null]];

        return $state;
    }

    private function context(): ImportContext
    {
        return new ImportContext(new Club(), new User());
    }

    public function testResolvesRowsWithReasons(): void
    {
        $sheet = new ImportSheet([
            ['Nom', 'Type'],
            ['F-CGAB', 'Planeur'],
            ['', 'Avion'],
            ['F-CHXY', 'Cuisine'],
            ['f-cgab', 'Avion'],
            ['F-GKLM', ''],
        ]);

        $preview = (new ImportRowResolver())->resolve($sheet, $this->state(), $this->definition(), $this->context());

        $this->assertSame(5, count($preview->rows));
        $this->assertNull($preview->rows[0]->reason);
        $this->assertSame(['name' => 'F-CGAB', 'type' => 'glider', 'note' => '', '_line' => '1'], $preview->rows[0]->values);
        $this->assertSame('importReasonMissing', $preview->rows[1]->reason);
        $this->assertSame('name', $preview->rows[1]->field);
        $this->assertSame('importReasonValueNotMapped', $preview->rows[2]->reason);
        $this->assertSame('importReasonDuplicateInFile', $preview->rows[3]->reason);
        $this->assertSame('importReasonMissing', $preview->rows[4]->reason);
        $this->assertSame(1, $preview->importableCount());
        $this->assertSame(4, $preview->ignoredCount());
    }

    public function testFixedValueWhenNoColumn(): void
    {
        $state = $this->state(hasHeader: false);
        $state->columns['type'] = null;
        $state->fixed = ['type' => 'airplane'];
        $sheet = new ImportSheet([['F-GKLM', 'x']]);

        $preview = (new ImportRowResolver())->resolve($sheet, $state, $this->definition(), $this->context());

        $this->assertSame('airplane', $preview->rows[0]->values['type']);
        $this->assertSame(1, $preview->rows[0]->line, 'numéro de ligne du fichier, à partir de 1');
    }
}
