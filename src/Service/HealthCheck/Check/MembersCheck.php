<?php

namespace App\Service\HealthCheck\Check;

use App\Entity\Club;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Service\HealthCheck\HealthCheckInterface;
use App\Service\HealthCheck\HealthCheckItem;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.health_check', ['priority' => 80])]
final class MembersCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly MembershipRepository $membershipRepository,
        private readonly InvitationRepository $invitationRepository,
    ) {}

    public function check(Club $club): HealthCheckItem
    {
        $others = $this->membershipRepository->countByClubExcludingUser($club, $club->getCreatedBy());
        $pending = $this->invitationRepository->countPendingByClub($club);

        return new HealthCheckItem('members', $others + $pending > 0, 'club_invitations', [], true);
    }
}
