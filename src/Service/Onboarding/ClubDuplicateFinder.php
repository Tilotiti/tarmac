<?php

namespace App\Service\Onboarding;

use App\Entity\Aerodrome;
use App\Repository\ClubRepository;

final class ClubDuplicateFinder
{
    public const NEAR_KM = 25.0;

    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly ClubNameNormalizer $normalizer,
    ) {
    }

    /**
     * Parcourt tous les clubs actifs (quelques dizaines) : pas d'index, tout se fait en PHP.
     */
    public function find(string $name, ?Aerodrome $aerodrome): DuplicateReport
    {
        $duplicates = [];
        $neighbours = [];

        foreach ($this->clubRepository->findAllActive() as $club) {
            $nameClose = $this->normalizer->isSimilar($name, (string) $club->getName());
            $sameAerodrome = $aerodrome !== null && $club->getAerodrome() === $aerodrome;

            $distance = null;
            if ($aerodrome !== null && $club->getLatitude() !== null && $club->getLongitude() !== null) {
                $distance = GeoDistance::kilometers(
                    $aerodrome->getLatitude(),
                    $aerodrome->getLongitude(),
                    $club->getLatitude(),
                    $club->getLongitude(),
                );
            }

            if ($nameClose) {
                // Sans information de distance, le nom seul suffit à signaler.
                if ($sameAerodrome || $distance === null || $distance < self::NEAR_KM) {
                    $duplicates[] = $club;
                }
                continue;
            }

            if ($sameAerodrome) {
                $neighbours[] = $club;
            }
        }

        return new DuplicateReport($duplicates, $neighbours);
    }
}
