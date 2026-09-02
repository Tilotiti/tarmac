<?php

namespace App\Service\HealthCheck;

use App\Entity\Club;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ClubHealthCheck
{
    public const REDISPLAY_AFTER_DAYS = 30;

    /** @var array<int|string, HealthReport> rapport mémoïsé par club (clé : id du club, ou spl_object_id si pas encore persisté) */
    private array $cache = [];

    /** @param iterable<HealthCheckInterface> $checks */
    public function __construct(
        #[AutowireIterator('app.health_check')] private readonly iterable $checks,
    ) {
    }

    /** Un même club ne doit être contrôlé qu'une fois par requête (tableau de bord + badge de menu, ou plusieurs pages consultant le badge). */
    public function check(Club $club): HealthReport
    {
        $key = $club->getId() ?? spl_object_id($club);

        if (!isset($this->cache[$key])) {
            $items = [];
            foreach ($this->checks as $check) {
                $items[] = $check->check($club);
            }
            $this->cache[$key] = new HealthReport($items);
        }

        return $this->cache[$key];
    }

    public function reset(): void
    {
        $this->cache = [];
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
