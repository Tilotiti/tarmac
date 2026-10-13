<?php

namespace App\Tests\Functional;

use App\Entity\Membership;
use App\Entity\User;
use App\Service\InvitationService;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class InvitationLoginTest extends FunctionalTestCase
{
    public function testExistingUserLoggingInFromInvitationJoinsTheClub(): void
    {
        [$user, $token] = $this->inviteExistingUser();

        $this->client->request('GET', $this->www('/invitation/' . $token));
        $this->assertResponseRedirects($this->www('/login?invitation=' . $token));

        $crawler = $this->client->followRedirect();
        $this->assertSame('pilote@example.test', $crawler->filter('input[name="_username"]')->attr('value'));

        $this->client->submitForm('Se connecter', ['_password' => 'motdepasse']);

        $this->assertResponseRedirects($this->www('/invitation/' . $token));
        $this->assertMembershipCreated($user);
    }

    public function testAccountCreatedAfterTheInvitationGoesToLogin(): void
    {
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $invitation = static::getContainer()->get(InvitationService::class)
            ->createInvitation($club, ['email' => 'pilote@example.test']);
        $this->createUser('pilote@example.test');

        $this->client->request('GET', $this->www('/invitation/' . $invitation->getToken()));

        $this->assertResponseRedirects($this->www('/login?invitation=' . $invitation->getToken()));
    }

    public function testNewEmailStillGetsTheRegistrationForm(): void
    {
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $invitation = static::getContainer()->get(InvitationService::class)
            ->createInvitation($club, ['email' => 'nouveau@example.test']);

        $this->client->request('GET', $this->www('/invitation/' . $invitation->getToken()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('button[type="submit"]', 'Créer mon compte et rejoindre');
    }

    public function testInvitationSurvivesAWrongPassword(): void
    {
        [$user, $token] = $this->inviteExistingUser();

        $this->client->request('GET', $this->www('/login?invitation=' . $token));
        $this->client->submitForm('Se connecter', ['_username' => 'pilote@example.test', '_password' => 'mauvais']);
        $this->client->followRedirect();
        $this->client->submitForm('Se connecter', ['_username' => 'pilote@example.test', '_password' => 'motdepasse']);

        $this->assertResponseRedirects($this->www('/invitation/' . $token));
        $this->assertMembershipCreated($user);
    }

    /**
     * @return array{User, string}
     */
    private function inviteExistingUser(): array
    {
        $user = $this->createUser('pilote@example.test');
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'motdepasse'));
        $this->entityManager()->flush();

        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $invitation = static::getContainer()->get(InvitationService::class)
            ->createInvitation($club, ['email' => 'pilote@example.test']);

        return [$user, $invitation->getToken()];
    }

    private function assertMembershipCreated(User $user): void
    {
        $this->client->followRedirect();

        $membership = $this->entityManager()->getRepository(Membership::class)->findOneBy(['user' => $user]);
        $this->assertNotNull($membership);
        $this->assertSame('beauce', $membership->getClub()->getSubdomain());
    }
}
