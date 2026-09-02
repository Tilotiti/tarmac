<?php

namespace App\Tests\Functional;

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
        $this->createClub('Sans terrain', 'sans');

        $this->client->request('GET', $this->www('/admin/clubs?aerodrome=without'));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Sans terrain');
    }
}
