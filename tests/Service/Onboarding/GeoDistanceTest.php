<?php

namespace App\Tests\Service\Onboarding;

use App\Service\Onboarding\GeoDistance;
use PHPUnit\Framework\TestCase;

class GeoDistanceTest extends TestCase
{
    public function testParisLyon(): void
    {
        $this->assertEqualsWithDelta(392.0, GeoDistance::kilometers(48.8566, 2.3522, 45.7640, 4.8357), 3.0);
    }

    public function testSamePointIsZero(): void
    {
        $this->assertSame(0.0, GeoDistance::kilometers(45.0, 5.0, 45.0, 5.0));
    }
}
