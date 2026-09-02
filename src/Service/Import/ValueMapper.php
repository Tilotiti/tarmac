<?php

namespace App\Service\Import;

/** Pré-affecte une valeur Tarmac à chaque valeur distincte d'une colonne à choix. */
final class ValueMapper
{
    /**
     * @param string[] $distinctValues
     *
     * @return array<string, string|null>
     */
    public function suggest(array $distinctValues, ImportField $field): array
    {
        $result = [];
        foreach ($distinctValues as $raw) {
            $result[$raw] = null;
            $normalized = ColumnMapper::normalize($raw);
            foreach ($field->choices as $choice) {
                foreach ($choice->synonyms as $synonym) {
                    $needle = ColumnMapper::normalize($synonym);
                    if ($normalized === $needle || str_contains($normalized, $needle)) {
                        $result[$raw] = $choice->value;
                        continue 3;
                    }
                }
            }
        }

        return $result;
    }
}
