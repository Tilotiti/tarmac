<?php

namespace App\Tests\Functional;

use App\Entity\Aerodrome;

class AdminClubTest extends FunctionalTestCase
{
    public function testDashboardListsRecentClubsAndShowPageDisplaysCreator(): void
    {
        $admin = $this->createUser('admin@example.test', admin: true);
        $president = $this->createUser('president@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->setCreatedBy($president);
        $this->entityManager()->flush();
        $this->client->loginUser($admin);

        $this->client->request('GET', $this->www('/admin'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Planeurs de Beauce');

        $this->client->request('GET', $this->www('/admin/clubs/' . $club->getId()));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'president@example.test');
        $this->assertSelectorTextContains('body', 'Sans aérodrome');
    }

    public function testIndexFiltersClubsWithoutAerodrome(): void
    {
        $this->client->loginUser($this->createUser('admin@example.test', admin: true));

        $aerodrome = (new Aerodrome())
            ->setSourceId(1)
            ->setName('Bailleau')
            ->setLatitude(48.5)
            ->setLongitude(1.6)
            ->refreshSearchIndex();
        $this->entityManager()->persist($aerodrome);

        $withAerodrome = $this->createClub('Avec terrain', 'avec');
        $withAerodrome->setAerodrome($aerodrome);
        $this->createClub('Sans terrain', 'sans');
        $this->entityManager()->flush();

        $this->client->request('GET', $this->www('/admin/clubs?aerodrome=without'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Sans terrain');
        $this->assertSelectorTextNotContains('body', 'Avec terrain');

        $this->client->request('GET', $this->www('/admin/clubs?aerodrome=with'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Avec terrain');
        $this->assertSelectorTextNotContains('body', 'Sans terrain');

        $this->client->request('GET', $this->www('/admin/clubs'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Avec terrain');
        $this->assertSelectorTextContains('body', 'Sans terrain');
    }

    public function testShowPageListsNeighboursOnSameAerodromeExcludingSelfAndInactive(): void
    {
        $this->client->loginUser($this->createUser('admin@example.test', admin: true));

        $aerodrome = (new Aerodrome())
            ->setSourceId(2)
            ->setName('Bailleau')
            ->setLatitude(48.5)
            ->setLongitude(1.6)
            ->refreshSearchIndex();
        $otherAerodrome = (new Aerodrome())
            ->setSourceId(3)
            ->setName('Chartres')
            ->setLatitude(48.4)
            ->setLongitude(1.5)
            ->refreshSearchIndex();
        $this->entityManager()->persist($aerodrome);
        $this->entityManager()->persist($otherAerodrome);

        $clubA = $this->createClub('Club A', 'club-a');
        $clubA->setAerodrome($aerodrome);

        $clubB = $this->createClub('Club B', 'club-b');
        $clubB->setAerodrome($aerodrome);

        $clubC = $this->createClub('Club C', 'club-c');
        $clubC->setAerodrome($aerodrome);
        $clubC->setActive(false);

        $clubD = $this->createClub('Club D', 'club-d');
        $clubD->setAerodrome($otherAerodrome);

        $this->entityManager()->flush();

        $this->client->request('GET', $this->www('/admin/clubs/' . $clubA->getId()));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Sur le même terrain');
        $this->assertSelectorTextContains('body', 'Club B');
        $this->assertSelectorTextNotContains('body', 'Club C');
        $this->assertSelectorTextNotContains('body', 'Club D');
    }
}
