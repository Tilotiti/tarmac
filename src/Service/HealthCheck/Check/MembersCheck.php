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
        $creator = $club->getCreatedBy();
        if ($creator !== null) {
            $others = $this->membershipRepository->countByClubExcludingUser($club, $creator);
        } else {
            // Créateur inconnu (ex. club créé depuis l'admin) : on ne peut pas l'exclure par identité,
            // mais un club avec une seule adhésion n'a par définition aucun « autre » membre.
            $others = max(0, $this->membershipRepository->countByClubExcludingUser($club, null) - 1);
        }
        $pending = $this->invitationRepository->countPendingByClub($club);

        return new HealthCheckItem('members', $others + $pending > 0, 'club_invitations', [], true);
    }
}
