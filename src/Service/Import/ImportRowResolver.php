<?php

namespace App\Service\Import;

/**
 * Applique l'état (colonnes, valeurs, valeurs fixes) à chaque ligne du fichier et
 * demande à la définition de valider la ligne. Ne persiste rien.
 */
final class ImportRowResolver
{
    public function resolve(ImportSheet $sheet, ImportState $state, ImportDefinitionInterface $definition, ImportContext $context): ImportPreview
    {
        $rows = [];
        $line = 0;
        foreach ($sheet->rows() as $index => $raw) {
            if ($state->hasHeader && $index === 0) {
                continue;
            }
            $line++;
            $values = [];
            $reason = null;
            $reasonField = null;

            foreach ($definition->fields() as $field) {
                $column = $state->columnFor($field->key);
                $cell = $column === null ? '' : ($raw[$column] ?? '');

                if ($field->hasChoices()) {
                    if ($column === null) {
                        $value = $state->fixed[$field->key] ?? $field->default ?? '';
                    } elseif ($cell === '') {
                        $value = $field->default ?? '';
                    } else {
                        $value = $state->values[$field->key][$cell] ?? null;
                        if ($value === null && $reason === null) {
                            $reason = 'importReasonValueNotMapped';
                            $reasonField = $field->key;
                        }
                        $value = (string) $value;
                    }
                } else {
                    $value = $cell;
                }

                if ($field->required && $value === '' && $reason === null) {
                    $reason = 'importReasonMissing';
                    $reasonField = $field->key;
                }
                $values[$field->key] = $value;
            }

            $values['_line'] = (string) $line;
            if ($reason === null) {
                $reason = $definition->validate($values, $context);
            }
            $rows[] = new ImportPreviewRow($line, $raw, $values, $reason, $reasonField);
        }

        return new ImportPreview($rows);
    }
}
