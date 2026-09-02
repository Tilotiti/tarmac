<?php

namespace App\Service\Aerodrome;

/**
 * Lit le fichier airports.csv d'OurAirports (domaine public) et ne garde
 * que les terrains d'un pays, des types susceptibles d'accueillir un club.
 */
final class AerodromeCsvParser
{
    private const TYPES = ['small_airport', 'medium_airport', 'large_airport'];
    private const COLUMNS = ['id', 'type', 'name', 'latitude_deg', 'longitude_deg', 'iso_country', 'municipality', 'icao_code'];

    /**
     * @return iterable<array{sourceId: int, icaoCode: ?string, name: string, municipality: ?string, latitude: float, longitude: float}>
     */
    public function parse(string $path, string $country = 'FR'): iterable
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Impossible d\'ouvrir %s', $path));
        }

        try {
            $headers = fgetcsv($handle, escape: '');
            if ($headers === false) {
                throw new \RuntimeException('Fichier vide');
            }
            $index = array_flip($headers);
            foreach (self::COLUMNS as $column) {
                if (!isset($index[$column])) {
                    throw new \RuntimeException(sprintf('Colonne manquante : %s', $column));
                }
            }

            while (($row = fgetcsv($handle, escape: '')) !== false) {
                if (($row[$index['iso_country']] ?? '') !== $country) {
                    continue;
                }
                if (!in_array($row[$index['type']] ?? '', self::TYPES, true)) {
                    continue;
                }

                $icao = trim((string) ($row[$index['icao_code']] ?? ''));
                $municipality = trim((string) ($row[$index['municipality']] ?? ''));

                yield [
                    'sourceId' => (int) $row[$index['id']],
                    'icaoCode' => $icao === '' ? null : $icao,
                    'name' => trim((string) $row[$index['name']]),
                    'municipality' => $municipality === '' ? null : $municipality,
                    'latitude' => (float) $row[$index['latitude_deg']],
                    'longitude' => (float) $row[$index['longitude_deg']],
                ];
            }
        } finally {
            fclose($handle);
        }
    }
}
