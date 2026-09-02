<?php

namespace App\Service\HealthCheck;

use App\Entity\Club;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ClubHealthCheck
{
    public const REDISPLAY_AFTER_DAYS = 30;

    /** @param iterable<HealthCheckInterface> $checks */
    public function __construct(
        #[AutowireIterator('app.health_check')] private readonly iterable $checks,
    ) {
    }

    public function check(Club $club): HealthReport
    {
        $items = [];
        foreach ($this->checks as $check) {
            $items[] = $check->check($club);
        }

        return new HealthReport($items);
    }

    /**
     * La carte disparaît quand tout est fait ; masquée, elle revient si un contrôle
     * essentiel manque et que le masquage date de plus de 30 jours.
     */
    public function shouldDisplay(Club $club, HealthReport $report): bool
    {
        if ($report->isComplete()) {
            return false;
        }

        $dismissedAt = $club->getHealthCheckDismissedAt();
        if ($dismissedAt === null) {
            return true;
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d days', self::REDISPLAY_AFTER_DAYS));

        return $report->hasEssentialTodo() && $dismissedAt < $threshold;
    }
}
