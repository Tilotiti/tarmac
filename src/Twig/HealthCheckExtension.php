<?php

namespace App\Twig;

use App\Entity\Club;
use App\Service\HealthCheck\ClubHealthCheck;
use App\Service\HealthCheck\HealthReport;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Expose le rapport de configuration au layout (badge du menu). Mémoïsé par club dans ClubHealthCheck. */
final class HealthCheckExtension extends AbstractExtension
{
    public function __construct(private readonly ClubHealthCheck $clubHealthCheck)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('club_health_report', $this->report(...))];
    }

    public function report(Club $club): HealthReport
    {
        return $this->clubHealthCheck->check($club);
    }
}
