<?php

namespace App\Service\HealthCheck\Check;

use App\Entity\Club;
use App\Repository\PlanApplicationRepository;
use App\Service\HealthCheck\HealthCheckInterface;
use App\Service\HealthCheck\HealthCheckItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.health_check', ['priority' => 50])]
final class PlanCheck implements HealthCheckInterface
{
    public function __construct(private readonly PlanApplicationRepository $planApplicationRepository) {}

    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('plan', $this->planApplicationRepository->countActiveByClub($club) >= 1, 'club_plans_explain', [], false);
    }
}
