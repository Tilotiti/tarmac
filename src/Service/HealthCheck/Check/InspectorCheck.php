<?php

namespace App\Service\HealthCheck\Check;

use App\Entity\Club;
use App\Repository\MembershipRepository;
use App\Service\HealthCheck\HealthCheckInterface;
use App\Service\HealthCheck\HealthCheckItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.health_check', ['priority' => 60])]
final class InspectorCheck implements HealthCheckInterface
{
    public function __construct(private readonly MembershipRepository $membershipRepository) {}

    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('inspector', $this->membershipRepository->countInspectorsByClub($club) >= 1, 'club_members', [], true);
    }
}
