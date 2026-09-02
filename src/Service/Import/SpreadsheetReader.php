<?php

namespace App\Service\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

/**
 * Lit un CSV, XLS ou XLSX en mémoire. CSV : séparateur et encodage détectés.
 */
final class SpreadsheetReader
{
    public const MAX_ROWS = 2000;
    private const DELIMITERS = [';', ',', "\t"];

    public function read(string $contents, string $extension): ImportSheet
    {
        $extension = strtolower($extension);
        if (!in_array($extension, ['csv', 'xls', 'xlsx'], true)) {
            throw new ImportReadException('unsupportedFormat');
        }

        $path = tempnam(sys_get_temp_dir(), 'tarmac-import');
        try {
            if ($extension === 'csv') {
                $contents = $this->toUtf8($contents);
                file_put_contents($path, $contents);
                $reader = new CsvReader();
                $reader->setDelimiter($this->detectDelimiter($contents));
                $reader->setInputEncoding('UTF-8');
                $spreadsheet = $reader->load($path);
            } else {
                file_put_contents($path, $contents);
                $spreadsheet = IOFactory::load($path, 0, [$extension === 'xls' ? IOFactory::READER_XLS : IOFactory::READER_XLSX]);
            }
        } catch (\Throwable $exception) {
            throw new ImportReadException('unreadableFile', 0, $exception);
        } finally {
            @unlink($path);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        if (count($rows) > self::MAX_ROWS + 1) {
            throw new ImportReadException('tooManyRows');
        }

        return new ImportSheet($rows);
    }

    private function detectDelimiter(string $contents): string
    {
        $head = implode("\n", array_slice(preg_split('/\R/', $contents) ?: [], 0, 20));
        $best = ';';
        $bestCount = -1;
        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($head, $delimiter);
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        return mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }
}
