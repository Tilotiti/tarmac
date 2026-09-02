<?php

namespace App\Service\Import;

/** État de l'assistant en session : jamais les lignes du fichier, seulement les choix. */
final class ImportState
{
    /** @var array<string, int|null> colonne choisie par champ */
    public array $columns = [];
    /** @var array<string, array<string, string|null>> valeur Tarmac par valeur brute, par champ à choix */
    public array $values = [];
    /** @var array<string, string> valeur unique par champ à choix sans colonne */
    public array $fixed = [];

    public function __construct(
        public string $definition,
        public string $fileKey,
        public string $originalName,
        public string $extension,
        public bool $hasHeader,
    ) {
    }

    public function columnFor(string $field): ?int
    {
        return $this->columns[$field] ?? null;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        $state = new self($data['definition'], $data['fileKey'], $data['originalName'], $data['extension'], (bool) $data['hasHeader']);
        $state->columns = $data['columns'] ?? [];
        $state->values = $data['values'] ?? [];
        $state->fixed = $data['fixed'] ?? [];

        return $state;
    }
}
