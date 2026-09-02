<?php

namespace App\Service\Import;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ImportDefinitionRegistry
{
    /** @var array<string, ImportDefinitionInterface> */
    private array $definitions = [];

    /** @param iterable<ImportDefinitionInterface> $definitions */
    public function __construct(#[AutowireIterator('app.import_definition')] iterable $definitions)
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->key()] = $definition;
        }
    }

    public function get(string $key): ?ImportDefinitionInterface
    {
        return $this->definitions[$key] ?? null;
    }
}
