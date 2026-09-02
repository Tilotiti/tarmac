<?php

namespace App\Tests\Functional;

use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Entity\Membership;
use App\Entity\Plan;
use App\Entity\PlanApplication;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\PlanApplicationRepository;
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

    public function testPlanApplicationCounterScopesToClubAndExcludesCancelled(): void
    {
        $president = $this->createUser('president@example.test');

        $clubA = $this->createClub('Club A', 'cluba');
        $clubB = $this->createClub('Club B', 'clubb');

        $equipmentA = (new Equipment())
            ->setName('Planeur A')
            ->setType(EquipmentType::GLIDER)
            ->setOwner(EquipmentOwner::CLUB)
            ->setClub($clubA);
        $equipmentB = (new Equipment())
            ->setName('Planeur B')
            ->setType(EquipmentType::GLIDER)
            ->setOwner(EquipmentOwner::CLUB)
            ->setClub($clubB);

        $planA = (new Plan())
            ->setName('Plan A')
            ->setEquipmentType(EquipmentType::GLIDER)
            ->setClub($clubA)
            ->setCreatedBy($president);
        $planB = (new Plan())
            ->setName('Plan B')
            ->setEquipmentType(EquipmentType::GLIDER)
            ->setClub($clubB)
            ->setCreatedBy($president);

        $this->entityManager()->persist($equipmentA);
        $this->entityManager()->persist($equipmentB);
        $this->entityManager()->persist($planA);
        $this->entityManager()->persist($planB);
        $this->entityManager()->flush();

        // Club A : une application active et une annulée -> une seule active
        $activeApplicationA = (new PlanApplication())
            ->setPlan($planA)
            ->setEquipment($equipmentA)
            ->setAppliedBy($president);
        $cancelledApplicationA = (new PlanApplication())
            ->setPlan($planA)
            ->setEquipment($equipmentA)
            ->setAppliedBy($president)
            ->setCancelledBy($president)
            ->setCancelledAt(new \DateTimeImmutable());

        // Club B : une seule application, active
        $activeApplicationB = (new PlanApplication())
            ->setPlan($planB)
            ->setEquipment($equipmentB)
            ->setAppliedBy($president);

        $this->entityManager()->persist($activeApplicationA);
        $this->entityManager()->persist($cancelledApplicationA);
        $this->entityManager()->persist($activeApplicationB);
        $this->entityManager()->flush();

        $applications = static::getContainer()->get(PlanApplicationRepository::class);
        $this->assertSame(1, $applications->countActiveByClub($clubA));
        $this->assertSame(1, $applications->countActiveByClub($clubB));
    }
}
