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
    private const SNIFF_LINES = 20;

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
                // setReadDataOnly() ignore les styles, fusions et mises en forme : seules les
                // valeurs nous intéressent, et c'est nettement plus rapide sur un gros fichier.
                $reader = IOFactory::createReader($extension === 'xls' ? 'Xls' : 'Xlsx');
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($path);
            }
        } catch (\Throwable $exception) {
            throw new ImportReadException('unreadableFile', 0, $exception);
        } finally {
            @unlink($path);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $sheet = new ImportSheet($rows);

        // Les lignes entièrement vides (souvent du formatage résiduel dans les XLSX) sont
        // retirées par ImportSheet avant ce contrôle : le plafond porte sur les lignes utiles.
        // Le « +1 » tolère un fichier de MAX_ROWS lignes de données sans en-tête.
        if ($sheet->rowCount() > self::MAX_ROWS + 1) {
            throw new ImportReadException('tooManyRows');
        }

        return $sheet;
    }

    private function detectDelimiter(string $contents): string
    {
        $lines = array_values(array_filter(preg_split('/\R/', $contents) ?: [], static fn (string $line): bool => trim($line) !== ''));
        $lines = array_slice($lines, 0, self::SNIFF_LINES);

        $best = null;
        $bestShare = -1.0;
        $bestMode = 0;
        foreach (self::DELIMITERS as $delimiter) {
            $counts = [];
            foreach ($lines as $line) {
                $fields = str_getcsv($line, $delimiter, '"', '');
                $counts[] = count($fields);
            }
            if ($counts === []) {
                continue;
            }
            $frequencies = array_count_values($counts);
            arsort($frequencies);
            $mode = (int) array_key_first($frequencies);
            $modeCount = $frequencies[$mode];
            $share = $modeCount / count($counts);

            if ($mode <= 1) {
                continue;
            }
            if ($share > $bestShare || ($share === $bestShare && $mode > $bestMode)) {
                $best = $delimiter;
                $bestShare = $share;
                $bestMode = $mode;
            }
        }

        return $best ?? ';';
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
