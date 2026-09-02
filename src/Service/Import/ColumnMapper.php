<?php

namespace App\Service\Import;

use function Symfony\Component\String\u;

/** Pré-affecte une colonne à chaque champ d'après les en-têtes du fichier. */
final class ColumnMapper
{
    /**
     * @param array<int, string> $labels
     * @param ImportField[]      $fields
     *
     * @return array<string, int|null>
     */
    public function suggest(array $labels, array $fields): array
    {
        $normalized = array_map([self::class, 'normalize'], $labels);
        $used = [];
        $result = [];
        foreach ($fields as $field) {
            $result[$field->key] = null;
        }

        // Passe 1 : correspondances exactes uniquement, priorité absolue.
        foreach ($fields as $field) {
            foreach ($field->synonyms as $synonym) {
                $needle = self::normalize($synonym);
                foreach ($normalized as $index => $label) {
                    if (isset($used[$index]) || $label === '' || strlen($label) < 2) {
                        continue;
                    }
                    if ($label === $needle) {
                        $result[$field->key] = $index;
                        $used[$index] = true;
                        continue 3;
                    }
                }
            }
        }

        // Passe 2 : pour les champs encore non affectés, correspondances par inclusion.
        foreach ($fields as $field) {
            if ($result[$field->key] !== null) {
                continue;
            }
            foreach ($field->synonyms as $synonym) {
                $needle = self::normalize($synonym);
                foreach ($normalized as $index => $label) {
                    if (isset($used[$index]) || $label === '' || strlen($label) < 2) {
                        continue;
                    }
                    if (str_contains($label, $needle)) {
                        $result[$field->key] = $index;
                        $used[$index] = true;
                        continue 3;
                    }
                }
            }
        }

        return $result;
    }

    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', u($text)->ascii()->lower()->toString()));
    }
}
