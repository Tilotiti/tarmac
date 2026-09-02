<?php

namespace App\Service\Import;

/** Une valeur Tarmac possible pour un champ à choix, avec ses synonymes de fichier. */
final readonly class ImportChoice
{
    /** @param string[] $synonyms */
    public function __construct(public string $value, public string $labelKey, public array $synonyms = [])
    {
    }
}
