<?php

namespace App\Tests\Functional;

use App\Entity\JoinRequest;
use App\Entity\Membership;

class JoinRequestTest extends FunctionalTestCase
{
    public function testRequestNotifiesManagersAndIsListedForThem(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();

        $requester = $this->createUser('candidat@example.test');
        $this->client->loginUser($requester);

        $this->client->request('GET', $this->www('/creer-mon-club/rejoindre/' . $club->getId()));
        $this->assertResponseIsSuccessful();
        $this->client->submitForm('Envoyer ma demande', ['join_request[message]' => 'Je suis membre depuis 2019.']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'ont été prévenus');
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailAddressContains($email, 'to', 'manager@example.test');
        $this->assertEmailHtmlBodyContains($email, 'candidat@example.test');
        $this->assertEmailHtmlBodyContains($email, 'Je suis membre depuis 2019.');

        $request = $this->entityManager()->getRepository(JoinRequest::class)->findOneBy(['user' => $requester, 'club' => $club]);
        $this->assertNotNull($request);
        $this->assertTrue($request->isOpen());

        $this->client->loginUser($manager);
        $this->client->request('GET', 'https://beauce.' . static::getContainer()->getParameter('domain') . '/invitations');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'candidat@example.test');
    }

    public function testSecondRequestIsNotDuplicated(): void
    {
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $requester = $this->createUser('candidat@example.test');
        $this->client->loginUser($requester);

        $this->client->request('GET', $this->www('/creer-mon-club/rejoindre/' . $club->getId()));
        $this->client->submitForm('Envoyer ma demande', ['join_request[message]' => '']);
        $this->client->request('GET', $this->www('/creer-mon-club/rejoindre/' . $club->getId()));
        $this->client->submitForm('Envoyer ma demande', ['join_request[message]' => '']);

        $this->assertSame(1, $this->entityManager()->getRepository(JoinRequest::class)->count(['user' => $requester, 'club' => $club]));
    }

    public function testMemberIsRedirectedAway(): void
    {
        $member = $this->createUser('membre@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($member)->setClub($club));
        $this->entityManager()->flush();
        $this->client->loginUser($member);

        $this->client->request('GET', $this->www('/creer-mon-club/rejoindre/' . $club->getId()));

        $this->assertResponseRedirects('/clubs');
    }
}
