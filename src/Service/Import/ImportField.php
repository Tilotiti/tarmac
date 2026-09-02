<?php

namespace App\Service\Import;

final readonly class ImportField
{
    /**
     * @param string[]       $synonyms en-têtes de fichier reconnus
     * @param ImportChoice[] $choices  vide = texte libre
     * @param string|null    $default  valeur retenue quand la cellule est vide (champ à choix)
     */
    public function __construct(
        public string $key,
        public string $labelKey,
        public bool $required,
        public array $synonyms = [],
        public array $choices = [],
        public ?string $default = null,
    ) {
    }

    public function hasChoices(): bool
    {
        return $this->choices !== [];
    }

    public function choice(string $value): ?ImportChoice
    {
        foreach ($this->choices as $choice) {
            if ($choice->value === $value) {
                return $choice;
            }
        }

        return null;
    }
}
