<?php

namespace App\Service\Import;

/**
 * Feuille lue : lignes rectangulaires de chaînes, lignes entièrement vides retirées.
 */
final class ImportSheet
{
    /** @var array<int, array<int, string>> */
    private array $rows;
    private int $columnCount;

    /** @param array<int, array<int, string|null>> $rows */
    public function __construct(array $rows)
    {
        $this->columnCount = 0;
        foreach ($rows as $row) {
            $this->columnCount = max($this->columnCount, count($row));
        }
        $this->rows = [];
        foreach ($rows as $row) {
            $cells = array_map(static fn ($cell): string => trim((string) $cell), array_values($row));
            $cells = array_pad($cells, $this->columnCount, '');
            if (implode('', $cells) === '') {
                continue;
            }
            $this->rows[] = $cells;
        }
    }

    /** @return array<int, array<int, string>> */
    public function rows(): array { return $this->rows; }
    public function rowCount(): int { return count($this->rows); }
    public function columnCount(): int { return $this->columnCount; }

    /** @return array<int, string> */
    public function column(int $index, bool $skipFirstRow = false): array
    {
        $rows = $skipFirstRow ? array_slice($this->rows, 1) : $this->rows;

        return array_map(static fn (array $row): string => $row[$index] ?? '', $rows);
    }

    /** Premières valeurs non vides distinctes, dans l'ordre du fichier. */
    public function sample(int $index, int $limit = 3, bool $skipFirstRow = false): array
    {
        return array_slice($this->distinctValues($index, PHP_INT_MAX, $skipFirstRow), 0, $limit);
    }

    public function distinctValues(int $index, int $limit = 20, bool $skipFirstRow = false): array
    {
        $seen = [];
        foreach ($this->column($index, $skipFirstRow) as $value) {
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            if (count($seen) >= $limit) {
                break;
            }
        }

        return array_keys($seen);
    }

    /** En-têtes du fichier, ou lettres de colonnes (A, B, … AA). */
    public function columnLabels(bool $hasHeader): array
    {
        $labels = [];
        for ($i = 0; $i < $this->columnCount; $i++) {
            $header = $hasHeader ? ($this->rows[0][$i] ?? '') : '';
            $labels[$i] = $header !== '' ? $header : self::letter($i);
        }

        return $labels;
    }

    public static function letter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
