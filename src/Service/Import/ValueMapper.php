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
        $matched = [];
        foreach ($distinctValues as $raw) {
            $result[$raw] = null;
        }

        // Passe 1 : correspondances exactes uniquement, priorité absolue.
        foreach ($distinctValues as $raw) {
            $normalized = ColumnMapper::normalize($raw);
            foreach ($field->choices as $choice) {
                foreach ($choice->synonyms as $synonym) {
                    $needle = ColumnMapper::normalize($synonym);
                    if ($normalized === $needle) {
                        $result[$raw] = $choice->value;
                        $matched[$raw] = true;
                        continue 3;
                    }
                }
            }
        }

        // Passe 2 : pour les valeurs encore non affectées, correspondances par inclusion
        // (synonymes d'au moins deux caractères seulement, pour éviter les faux positifs).
        foreach ($distinctValues as $raw) {
            if (isset($matched[$raw])) {
                continue;
            }
            $normalized = ColumnMapper::normalize($raw);
            foreach ($field->choices as $choice) {
                foreach ($choice->synonyms as $synonym) {
                    $needle = ColumnMapper::normalize($synonym);
                    if (mb_strlen($needle) < 2) {
                        continue;
                    }
                    if (str_contains($normalized, $needle)) {
                        $result[$raw] = $choice->value;
                        $matched[$raw] = true;
                        continue 3;
                    }
                }
            }
        }

        return $result;
    }
}
