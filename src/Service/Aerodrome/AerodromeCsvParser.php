<?php

namespace App\Service\Aerodrome;

/**
 * Lit le fichier airports.csv d'OurAirports (domaine public) et ne garde
 * que les terrains d'un pays, des types susceptibles d'accueillir un club.
 */
final class AerodromeCsvParser
{
    private const TYPES = ['small_airport', 'medium_airport', 'large_airport'];
    private const COLUMNS = ['id', 'type', 'name', 'latitude_deg', 'longitude_deg', 'iso_country', 'municipality', 'icao_code', 'gps_code'];

    /**
     * Communes fausses dans OurAirports, corrigées à chaque import (clé : id OurAirports).
     * Signaler aussi l'erreur sur ourairports.com pour pouvoir retirer la ligne un jour.
     */
    private const MUNICIPALITY_FIXES = [
        28915 => 'Le Louroux', // LFJT, en Indre-et-Loire ; la source indique Angers
    ];

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
                // Beaucoup de terrains français n'ont leur code OACI que dans gps_code (ex. LFJT).
                if ($icao === '' && preg_match('/^[A-Z]{4}$/', $gps = trim((string) ($row[$index['gps_code']] ?? '')))) {
                    $icao = $gps;
                }
                $municipality = trim((string) ($row[$index['municipality']] ?? ''));

                $sourceId = (int) $row[$index['id']];
                $municipality = self::MUNICIPALITY_FIXES[$sourceId] ?? $municipality;

                yield [
                    'sourceId' => $sourceId,
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
