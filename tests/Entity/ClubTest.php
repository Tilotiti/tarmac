<?php

namespace App\Tests\Entity;

use App\Entity\Aerodrome;
use App\Entity\Club;
use PHPUnit\Framework\TestCase;

class ClubTest extends TestCase
{
    public function testSetAerodromeCopiesCoordinates(): void
    {
        $aerodrome = (new Aerodrome())->setLatitude(48.5)->setLongitude(1.6);
        $club = new Club();

        $club->setAerodrome($aerodrome);

        $this->assertEqualsWithDelta(48.5, $club->getLatitude(), 0.0001);
        $this->assertEqualsWithDelta(1.6, $club->getLongitude(), 0.0001);
        $this->assertSame($aerodrome->getLabel(), $club->getLocationText());
    }

    public function testUnsettingAerodromeClearsCoordinates(): void
    {
        $aerodrome = (new Aerodrome())->setLatitude(48.5)->setLongitude(1.6);
        $club = new Club();
        $club->setAerodrome($aerodrome);

        $club->setAerodrome(null);

        $this->assertNull($club->getLatitude());
        $this->assertNull($club->getLongitude());
        $this->assertSame('', $club->getLocationText());

        $club->setLocationLabel('Saint-Rémy');
        $this->assertSame('Saint-Rémy', $club->getLocationText());
    }
}
