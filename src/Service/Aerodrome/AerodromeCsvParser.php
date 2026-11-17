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
        // Contrôle du 17/11/2026 : commune de la source comparée aux coordonnées (geo.api.gouv.fr).
        28785 => 'Saint-Junien, Haute-Vienne', // LFBJ ; source : Poitiers
        28787 => 'Dax, Landes', // LFBY ; source : Périgueux
        28793 => 'Jonzac, Charente-Maritime', // LFCJ ; source : Albi
        28799 => 'Villefranche-de-Rouergue', // LFCV ; source : Rodez
        28805 => 'Soulac-sur-Mer', // LFDK ; source : Pamiers
        28806 => 'Loudun', // LFDL ; source : Pamiers
        28816 => 'Montayral, Lot-et-Garonne', // LFDX ; source : Rochefort
        28820 => 'Ouessant', // LFEC ; source : Ushant
        28877 => 'Le Broc, Puy-de-Dôme', // LFHA ; source : Alpes-Maritimes
        28889 => 'Beaumont, Haute-Loire', // LFHR ; source : Puy-de-Dôme
        28900 => 'Clarac, Haute-Garonne', // LFIM ; source : Hautes-Pyrénées
        28920 => "Saint-Jean-d'Avelanne, Isère", // LFKH ; source : Figari
        28953 => 'Uzès, Gard', // LFNU ; source : Mont-Lozère-et-Goulet
        28963 => 'Vernouillet, Eure-et-Loir', // LFON ; source : Yvelines
        28995 => 'Loyat, Morbihan', // LFRP ; source : Rennes
        336355 => 'Le Burgaud, Tarn-et-Garonne', // source : Haute-Garonne
        572127 => 'Vignats, Calvados', // source : Vignat, Orne
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
