<?php

namespace App\Tests\Functional;

use App\Entity\Membership;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Service\InvitationService;

class ClubCountersTest extends FunctionalTestCase
{
    public function testMembershipAndInvitationCounters(): void
    {
        $president = $this->createUser('president@example.test');
        $inspector = $this->createUser('qualifie@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($president)->setClub($club)->setIsManager(true));
        $club->addMembership((new Membership())->setUser($inspector)->setClub($club)->setIsInspector(true));
        $this->entityManager()->flush();
        static::getContainer()->get(InvitationService::class)->createInvitation($club, ['email' => 'invite@example.test']);

        $memberships = static::getContainer()->get(MembershipRepository::class);
        $this->assertSame(1, $memberships->countByClubExcludingUser($club, $president));
        $this->assertSame(2, $memberships->countByClubExcludingUser($club, null));
        $this->assertSame(1, $memberships->countManagersByClub($club));
        $this->assertSame(1, $memberships->countInspectorsByClub($club));
        $this->assertSame(1, static::getContainer()->get(InvitationRepository::class)->countPendingByClub($club));
    }
}
