<?php

namespace App\Tests\Functional;

use App\Entity\Enum\PurchaseStatus;
use App\Entity\Membership;
use App\Entity\Purchase;

class DashboardPurchasesTest extends FunctionalTestCase
{
    public function testDashboardListsApprovedPurchasesOnly(): void
    {
        $member = $this->createUser('membre@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($member)->setClub($club));
        foreach (['Durite' => PurchaseStatus::APPROVED, 'Pneu' => PurchaseStatus::PURCHASED] as $name => $status) {
            $this->entityManager()->persist((new Purchase())->setClub($club)->setCreatedBy($member)->setName($name)->setStatus($status));
        }
        $this->entityManager()->flush();
        $this->client->loginUser($member);

        $this->client->request('GET', 'https://club.' . static::getContainer()->getParameter('domain') . '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Achats à réaliser');
        $this->assertSelectorTextContains('body', 'Durite');
        $this->assertSelectorTextNotContains('body', 'Pneu');
    }
}
