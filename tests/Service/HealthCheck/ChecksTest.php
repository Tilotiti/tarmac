<?php

namespace App\Tests\Service\HealthCheck;

use App\Entity\Aerodrome;
use App\Entity\Club;
use App\Entity\User;
use App\Repository\EquipmentRepository;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\PlanApplicationRepository;
use App\Service\HealthCheck\Check\AerodromeCheck;
use App\Service\HealthCheck\Check\EquipmentCheck;
use App\Service\HealthCheck\Check\InspectorCheck;
use App\Service\HealthCheck\Check\MembersCheck;
use App\Service\HealthCheck\Check\PlanCheck;
use App\Service\HealthCheck\Check\SecondManagerCheck;
use App\Service\HealthCheck\Check\WelcomeMessageCheck;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ChecksTest extends TestCase
{
    public function testAerodrome(): void
    {
        $check = new AerodromeCheck();
        $this->assertFalse($check->check(new Club())->done);
        $this->assertTrue($check->check((new Club())->setAerodrome(new Aerodrome()))->done);
        $this->assertSame('club_settings_aerodrome', $check->check(new Club())->route);
        $this->assertFalse($check->check(new Club())->essential);
    }

    public function testEquipment(): void
    {
        $repository = $this->createMock(EquipmentRepository::class);
        $repository->method('countActiveByClub')->willReturnOnConsecutiveCalls(0, 2);
        $check = new EquipmentCheck($repository);

        $this->assertFalse($check->check(new Club())->done);
        $item = $check->check(new Club());
        $this->assertTrue($item->done);
        $this->assertTrue($item->essential);
        $this->assertSame('club_equipments', $item->route);
    }

    public function testMembersCountsInvitationsAndOtherMembershipsThanCreator(): void
    {
        $creator = new User();
        $club = (new Club())->setCreatedBy($creator);
        $memberships = $this->createMock(MembershipRepository::class);
        $memberships->method('countByClubExcludingUser')->with($club, $creator)->willReturnOnConsecutiveCalls(0, 0, 1);
        $invitations = $this->createMock(InvitationRepository::class);
        $invitations->method('countPendingByClub')->willReturnOnConsecutiveCalls(0, 1, 0);
        $check = new MembersCheck($memberships, $invitations);

        $this->assertFalse($check->check($club)->done);
        $this->assertTrue($check->check($club)->done);
        $this->assertTrue($check->check($club)->done);
    }

    public function testMembersWithoutCreatorTreatsSingleMembershipAsNoOtherMember(): void
    {
        // Club créé depuis l'admin : pas de créateur connu, on ne peut exclure personne par identité.
        $club = new Club();
        $memberships = $this->createMock(MembershipRepository::class);
        $memberships->method('countByClubExcludingUser')->with($club, null)->willReturnOnConsecutiveCalls(1, 2);
        $invitations = $this->createMock(InvitationRepository::class);
        $invitations->method('countPendingByClub')->willReturn(0);
        $check = new MembersCheck($memberships, $invitations);

        // 1 adhésion (le seul membre visible) : personne d'autre, pas de fausse détection.
        $this->assertFalse($check->check($club)->done);
        // 2 adhésions : au moins un « autre » membre en plus du premier.
        $this->assertTrue($check->check($club)->done);
    }

    public function testSecondManagerAndInspector(): void
    {
        $memberships = $this->createMock(MembershipRepository::class);
        $memberships->method('countManagersByClub')->willReturnOnConsecutiveCalls(1, 2, 2);
        $memberships->method('countInspectorsByClub')->willReturnOnConsecutiveCalls(0, 1, 1);

        $secondManager = new SecondManagerCheck($memberships);
        $this->assertFalse($secondManager->check(new Club())->done);
        $this->assertTrue($secondManager->check(new Club())->done);
        $this->assertFalse($secondManager->check(new Club())->essential);

        $inspector = new InspectorCheck($memberships);
        $this->assertFalse($inspector->check(new Club())->done);
        $this->assertTrue($inspector->check(new Club())->done);
        $this->assertTrue($inspector->check(new Club())->essential);
    }

    public function testPlanAndWelcomeMessage(): void
    {
        $applications = $this->createMock(PlanApplicationRepository::class);
        $applications->method('countActiveByClub')->willReturnOnConsecutiveCalls(0, 1, 1);
        $plan = new PlanCheck($applications);
        $this->assertFalse($plan->check(new Club())->done);
        $this->assertTrue($plan->check(new Club())->done);
        $this->assertSame('club_plans_explain', $plan->check(new Club())->route);

        $welcome = new WelcomeMessageCheck();
        $this->assertFalse($welcome->check((new Club())->setWelcomeMessage('  '))->done);
        $this->assertTrue($welcome->check((new Club())->setWelcomeMessage('Bienvenue'))->done);
    }
}
