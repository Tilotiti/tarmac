<?php

namespace App\Tests\Functional;

use App\Entity\Aerodrome;
use App\Entity\Club;
use App\Entity\User;

class OnboardingClubTest extends FunctionalTestCase
{
    private function aerodrome(string $name, float $lat, float $lng): Aerodrome
    {
        $aerodrome = (new Aerodrome())->setSourceId(random_int(1, 1_000_000))->setName($name)->setLatitude($lat)->setLongitude($lng)->refreshSearchIndex();
        $this->entityManager()->persist($aerodrome);
        $this->entityManager()->flush();

        return $aerodrome;
    }

    public function testUnverifiedUserIsSentBackToVerification(): void
    {
        $this->client->loginUser($this->createUser('nonverifie@example.test', verified: false));

        $this->client->request('GET', $this->www('/creer-mon-club/club'));

        $this->assertResponseRedirects('/creer-mon-club/verification');
    }

    public function testClubCreationMakesUserManagerAndNotifiesAdmins(): void
    {
        $this->createUser('admin@example.test', admin: true);
        $president = $this->createUser('president@example.test');
        $this->client->loginUser($president);
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);

        $this->client->request('GET', $this->www('/creer-mon-club/club'));
        $this->assertResponseIsSuccessful();
        $this->client->submitForm('Créer mon club', [
            'onboarding_club[name]' => 'Planeurs de Beauce',
            'onboarding_club[subdomain]' => 'beauce',
            'onboarding_club[aerodrome]' => (string) $field->getId(),
        ]);

        $club = $this->entityManager()->getRepository(Club::class)->findOneBy(['subdomain' => 'beauce']);
        $this->assertNotNull($club);
        $this->assertResponseRedirects('/creer-mon-club/bienvenue/' . $club->getId());
        $this->assertSame($president->getId(), $club->getCreatedBy()->getId());
        // Après plusieurs requêtes, le ContextListener recharge l'utilisateur en session : ce
        // n'est plus le même objet PHP que $president (même ligne en base). On le rafraîchit
        // pour comparer les bonnes instances via l'identity map de Doctrine.
        $reloadedPresident = $this->entityManager()->getRepository(User::class)->find($president->getId());
        $this->assertTrue($club->getMembership($reloadedPresident)->isManager());
        $this->assertEqualsWithDelta(48.5, $club->getLatitude(), 0.001);
        $this->assertEmailCount(2);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'beauce.' . static::getContainer()->getParameter('domain'));
    }

    public function testReservedSubdomainIsRejected(): void
    {
        $this->client->loginUser($this->createUser('president@example.test'));
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);

        $this->client->request('GET', $this->www('/creer-mon-club/club'));
        $this->client->submitForm('Créer mon club', [
            'onboarding_club[name]' => 'Planeurs de Beauce',
            'onboarding_club[subdomain]' => 'admin',
            'onboarding_club[aerodrome]' => (string) $field->getId(),
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'Cette adresse est réservée');
    }

    public function testDuplicateScreenThenConfirmedCreation(): void
    {
        $this->client->loginUser($this->createUser('president@example.test'));
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);
        $existing = $this->createClub('Centre de Vol à Voile de Bailleau', 'cvvb');
        $existing->setAerodrome($field);
        $this->entityManager()->flush();

        $this->client->request('GET', $this->www('/creer-mon-club/club'));
        $this->client->submitForm('Créer mon club', [
            'onboarding_club[name]' => 'CVV Bailleau',
            'onboarding_club[subdomain]' => 'cvv-bailleau',
            'onboarding_club[aerodrome]' => (string) $field->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Ce club existe déjà');
        $this->assertSelectorTextContains('body', 'Centre de Vol à Voile de Bailleau');
        $this->assertNull($this->entityManager()->getRepository(Club::class)->findOneBy(['subdomain' => 'cvv-bailleau']));

        $this->client->submitForm("Ce n'est pas mon club, continuer");

        $this->assertNotNull($this->entityManager()->getRepository(Club::class)->findOneBy(['subdomain' => 'cvv-bailleau']));
    }

    public function testNeighbourOnSameAerodromeIsShownAsInformation(): void
    {
        $this->client->loginUser($this->createUser('president@example.test'));
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);
        $neighbour = $this->createClub('Aéroclub de Bailleau', 'acb');
        $neighbour->setAerodrome($field);
        $this->entityManager()->flush();

        $this->client->request('GET', $this->www('/creer-mon-club/club'));
        $this->client->submitForm('Créer mon club', [
            'onboarding_club[name]' => 'Planeurs de Beauce',
            'onboarding_club[subdomain]' => 'beauce',
            'onboarding_club[aerodrome]' => (string) $field->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', "D'autres clubs sont déjà sur ce terrain");
        $this->assertSelectorTextNotContains('body', 'Ce club existe déjà');
    }
}
