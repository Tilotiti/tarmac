<?php

namespace App\Service\Import;

final readonly class ImportPreviewRow
{
    /**
     * @param int                   $line   numéro de ligne dans le fichier (1 = première ligne de données)
     * @param array<string, string> $values valeurs résolues par champ
     * @param string|null           $reason clé de traduction de la raison d'ignorance
     * @param string|null           $field  champ concerné par la raison
     */
    public function __construct(public int $line, public array $raw, public array $values, public ?string $reason, public ?string $field = null)
    {
    }

    public function isImportable(): bool
    {
        return $this->reason === null;
    }
}
