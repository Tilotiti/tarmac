<?php

namespace App\Service\HealthCheck\Check;

use App\Entity\Club;
use App\Repository\MembershipRepository;
use App\Service\HealthCheck\HealthCheckInterface;
use App\Service\HealthCheck\HealthCheckItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.health_check', ['priority' => 70])]
final class SecondManagerCheck implements HealthCheckInterface
{
    public function __construct(private readonly MembershipRepository $membershipRepository) {}

    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('secondManager', $this->membershipRepository->countManagersByClub($club) >= 2, 'club_members', [], false);
    }
}
