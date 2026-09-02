<?php

namespace App\Twig;

use App\Entity\Club;
use App\Service\HealthCheck\ClubHealthCheck;
use App\Service\HealthCheck\HealthReport;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Expose le rapport de configuration au layout (badge du menu), calculé une fois par requête. */
final class HealthCheckExtension extends AbstractExtension
{
    private ?HealthReport $cached = null;
    private ?int $cachedClubId = null;

    public function __construct(private readonly ClubHealthCheck $clubHealthCheck)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('club_health_report', $this->report(...))];
    }

    public function report(Club $club): HealthReport
    {
        if ($this->cached === null || $this->cachedClubId !== $club->getId()) {
            $this->cached = $this->clubHealthCheck->check($club);
            $this->cachedClubId = $club->getId();
        }

        return $this->cached;
    }
}
