<?php

namespace App\Service\Onboarding;

final class GeoDistance
{
    private const EARTH_RADIUS_KM = 6371.0;

    /** Formule de Haversine, résultat en kilomètres. */
    public static function kilometers(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(sqrt($a));
    }
}
