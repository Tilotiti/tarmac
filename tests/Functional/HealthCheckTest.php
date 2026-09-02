<?php

namespace App\Tests\Functional;

use App\Entity\Aerodrome;
use App\Entity\Club;
use App\Entity\Membership;

class HealthCheckTest extends FunctionalTestCase
{
    protected function clubUrl(Club $club, string $path = '/'): string
    {
        return 'https://' . $club->getSubdomain() . '.' . static::getContainer()->getParameter('domain') . $path;
    }

    public function testManagerSetsAerodrome(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $aerodrome = (new Aerodrome())->setSourceId(1)->setName('Bailleau')->setLatitude(48.5)->setLongitude(1.6)->refreshSearchIndex();
        $this->entityManager()->persist($aerodrome);
        $this->entityManager()->flush();
        $this->client->loginUser($manager);

        $this->client->request('GET', $this->clubUrl($club, '/reglages/aerodrome'));
        $this->assertResponseIsSuccessful();
        $this->client->submitForm('Enregistrer', ['club_aerodrome[aerodrome]' => (string) $aerodrome->getId()]);

        $this->assertResponseRedirects('/');
        // Après deux requêtes, le kernel a redémarré : $club n'est plus géré par l'EntityManager
        // courant (nouvelle instance). On le recharge via son id plutôt que refresh() (cf. le
        // même commentaire dans OnboardingClubTest).
        $club = $this->entityManager()->getRepository(Club::class)->find($club->getId());
        $this->assertSame('Bailleau', $club->getAerodrome()->getName());
        $this->assertEqualsWithDelta(48.5, $club->getLatitude(), 0.001);
    }
}
