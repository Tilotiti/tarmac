<?php

namespace App\Service\Onboarding;

use App\Entity\Club;

final readonly class DuplicateReport
{
    /**
     * @param Club[] $duplicates clubs au nom proche et géographiquement proches (ou sans information de distance)
     * @param Club[] $neighbours clubs sur le même aérodrome avec un nom différent : jamais des doublons
     */
    public function __construct(
        public array $duplicates = [],
        public array $neighbours = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->duplicates === [] && $this->neighbours === [];
    }
}
