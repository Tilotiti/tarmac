<?php

namespace App\Tests\Service\HealthCheck;

use App\Entity\Club;
use App\Service\HealthCheck\ClubHealthCheck;
use App\Service\HealthCheck\HealthCheckInterface;
use App\Service\HealthCheck\HealthCheckItem;
use PHPUnit\Framework\TestCase;

class ClubHealthCheckTest extends TestCase
{
    private function check(string $key, bool $done, bool $essential): HealthCheckInterface
    {
        return new class($key, $done, $essential) implements HealthCheckInterface {
            public function __construct(private readonly string $key, private readonly bool $done, private readonly bool $essential) {}

            public function check(Club $club): HealthCheckItem
            {
                return new HealthCheckItem($this->key, $this->done, 'club_dashboard', [], $this->essential);
            }
        };
    }

    public function testReportCountsAndOrder(): void
    {
        $service = new ClubHealthCheck([$this->check('a', true, true), $this->check('b', false, false), $this->check('c', false, true)]);
        $report = $service->check(new Club());

        $this->assertSame(['a', 'b', 'c'], array_map(static fn ($item) => $item->key, $report->items));
        $this->assertSame(1, $report->doneCount());
        $this->assertSame(3, $report->total());
        $this->assertFalse($report->isComplete());
        $this->assertTrue($report->hasEssentialTodo());
        $this->assertSame(['b', 'c'], array_map(static fn ($item) => $item->key, $report->todo()));
    }

    public function testCheckIsMemoizedPerClub(): void
    {
        $counting = new class implements HealthCheckInterface {
            public int $calls = 0;

            public function check(Club $club): HealthCheckItem
            {
                ++$this->calls;

                return new HealthCheckItem('a', true, 'club_dashboard', [], false);
            }
        };
        $service = new ClubHealthCheck([$counting]);
        $club = new Club();

        $service->check($club);
        $service->check($club);

        $this->assertSame(1, $counting->calls);

        $service->reset();
        $service->check($club);

        $this->assertSame(2, $counting->calls);
    }

    public function testDisplayRules(): void
    {
        $complete = new ClubHealthCheck([$this->check('a', true, true)]);
        $this->assertFalse($complete->shouldDisplay(new Club(), $complete->check(new Club())));

        $pending = new ClubHealthCheck([$this->check('a', false, true)]);
        $club = new Club();
        $this->assertTrue($pending->shouldDisplay($club, $pending->check($club)));

        $club->setHealthCheckDismissedAt(new \DateTimeImmutable('-2 days'));
        $this->assertFalse($pending->shouldDisplay($club, $pending->check($club)));

        $club->setHealthCheckDismissedAt(new \DateTimeImmutable('-40 days'));
        $this->assertTrue($pending->shouldDisplay($club, $pending->check($club)));

        $optionalOnly = new ClubHealthCheck([$this->check('a', false, false)]);
        $this->assertFalse($optionalOnly->shouldDisplay($club, $optionalOnly->check($club)));
    }
}
