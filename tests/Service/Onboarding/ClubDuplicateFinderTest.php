<?php

namespace App\Tests\Service\Onboarding;

use App\Entity\Aerodrome;
use App\Entity\Club;
use App\Repository\ClubRepository;
use App\Service\Onboarding\ClubDuplicateFinder;
use App\Service\Onboarding\ClubNameNormalizer;
use PHPUnit\Framework\TestCase;

class ClubDuplicateFinderTest extends TestCase
{
    private function aerodrome(string $name, float $lat, float $lng): Aerodrome
    {
        return (new Aerodrome())->setName($name)->setLatitude($lat)->setLongitude($lng);
    }

    private function club(string $name, ?Aerodrome $aerodrome): Club
    {
        return (new Club())->setName($name)->setSubdomain(strtolower(substr(md5($name), 0, 8)))->setAerodrome($aerodrome);
    }

    /**
     * @param Club[] $existing
     */
    private function finder(array $existing): ClubDuplicateFinder
    {
        $repository = $this->createStub(ClubRepository::class);
        $repository->method('findAllActive')->willReturn($existing);

        return new ClubDuplicateFinder($repository, new ClubNameNormalizer());
    }

    public function testSameAerodromeAndCloseNameIsDuplicate(): void
    {
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);
        $existing = $this->club('Centre de Vol à Voile de Bailleau', $field);

        $report = $this->finder([$existing])->find('CVV Bailleau', $field);

        $this->assertSame([$existing], $report->duplicates);
        $this->assertSame([], $report->neighbours);
    }

    public function testNearbyAerodromeAndCloseNameIsDuplicate(): void
    {
        $existing = $this->club('Aéroclub de Chartres', $this->aerodrome('Chartres', 48.45, 1.52));

        $report = $this->finder([$existing])->find('Aéro-Club de Chartres', $this->aerodrome('Bailleau', 48.5, 1.6));

        $this->assertSame([$existing], $report->duplicates);
    }

    public function testFarAwayHomonymIsIgnored(): void
    {
        $existing = $this->club('Aéroclub du Centre', $this->aerodrome('Vichy', 46.17, 3.40));

        $report = $this->finder([$existing])->find('Aéroclub du Centre', $this->aerodrome('Bailleau', 48.5, 1.6));

        $this->assertTrue($report->isEmpty());
    }

    public function testSameAerodromeDifferentNameIsNeighbour(): void
    {
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);
        $existing = $this->club('Aéroclub de Bailleau (avions)', $field);

        $report = $this->finder([$existing])->find('Planeurs de Beauce', $field);

        $this->assertSame([], $report->duplicates);
        $this->assertSame([$existing], $report->neighbours);
    }

    public function testCloseNameWithoutAnyCoordinatesIsDuplicate(): void
    {
        $existing = $this->club('Planeurs de Beauce', null);

        $report = $this->finder([$existing])->find('Les Planeurs de Beauce', null);

        $this->assertSame([$existing], $report->duplicates);
    }

    public function testCloseNameWithExistingClubWithoutCoordinatesIsDuplicate(): void
    {
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);
        $existing = $this->club('Planeurs de Beauce', null);

        $report = $this->finder([$existing])->find('Les Planeurs de Beauce', $field);

        $this->assertSame([$existing], $report->duplicates);
    }
}
