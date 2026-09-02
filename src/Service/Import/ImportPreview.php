<?php

namespace App\Service\Import;

final readonly class ImportPreview
{
    /** @param ImportPreviewRow[] $rows */
    public function __construct(public array $rows)
    {
    }

    public function importableCount(): int
    {
        return count(array_filter($this->rows, static fn (ImportPreviewRow $row): bool => $row->isImportable()));
    }

    public function ignoredCount(): int
    {
        return count($this->rows) - $this->importableCount();
    }

    /** @return ImportPreviewRow[] */
    public function importable(): array
    {
        return array_values(array_filter($this->rows, static fn (ImportPreviewRow $row): bool => $row->isImportable()));
    }
}
