<?php

namespace App\Tests\Functional;

use App\Entity\JoinRequest;
use App\Entity\Membership;
use App\Service\InvitationService;
use App\Service\Onboarding\JoinRequestService;

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

    public function testRequestOnInactiveClubReturns404(): void
    {
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->setActive(false);
        $this->entityManager()->flush();

        $requester = $this->createUser('candidat@example.test');
        $this->client->loginUser($requester);

        $this->client->request('GET', $this->www('/creer-mon-club/rejoindre/' . $club->getId()));

        $this->assertResponseStatusCodeSame(404);
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

    public function testAcceptingInvitationClosesOpenJoinRequestEvenWhenAlreadyMember(): void
    {
        $manager = $this->createUser('manager2@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();

        $requester = $this->createUser('deja-membre@example.test');

        /** @var JoinRequestService $joinRequestService */
        $joinRequestService = static::getContainer()->get(JoinRequestService::class);
        $joinRequestService->submit($requester, $club, null);

        // L'utilisateur devient membre par un autre chemin (invitation directe, ajout manuel...)
        // avant que la demande d'adhésion n'ait été traitée par un gestionnaire.
        $club->addMembership((new Membership())->setUser($requester)->setClub($club));
        $this->entityManager()->flush();

        /** @var InvitationService $invitationService */
        $invitationService = static::getContainer()->get(InvitationService::class);
        $invitation = $invitationService->createInvitation($club, ['email' => $requester->getEmail()]);
        $invitationService->acceptInvitation($requester, $invitation);

        $joinRequest = $this->entityManager()->getRepository(JoinRequest::class)->findOneBy(['user' => $requester, 'club' => $club]);
        $this->assertNotNull($joinRequest);
        $this->assertFalse($joinRequest->isOpen());
        $this->assertSame($requester, $joinRequest->getHandledBy());
    }

    public function testManagerCanIgnoreJoinRequest(): void
    {
        $manager = $this->createUser('manager4@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();

        $requester = $this->createUser('candidat4@example.test');
        /** @var JoinRequestService $joinRequestService */
        $joinRequestService = static::getContainer()->get(JoinRequestService::class);
        $joinRequestService->submit($requester, $club, null);
        $joinRequest = $this->entityManager()->getRepository(JoinRequest::class)->findOneBy(['user' => $requester, 'club' => $club]);

        $this->client->loginUser($manager);
        $host = 'https://beauce.' . static::getContainer()->getParameter('domain');
        $crawler = $this->client->request('GET', $host . '/invitations');
        $token = $crawler->filter('form[action$="/invitations/demandes/' . $joinRequest->getId() . '/ignorer"] input[name="_token"]')->attr('value');
        $this->client->request('POST', $host . '/invitations/demandes/' . $joinRequest->getId() . '/ignorer', ['_token' => $token]);

        $this->assertResponseRedirects('/invitations');
        $reloaded = $this->entityManager()->getRepository(JoinRequest::class)->find($joinRequest->getId());
        $this->assertFalse($reloaded->isOpen());
    }

    public function testIgnoringJoinRequestWithWrongTokenIsForbidden(): void
    {
        $manager = $this->createUser('manager5@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();

        $requester = $this->createUser('candidat5@example.test');
        /** @var JoinRequestService $joinRequestService */
        $joinRequestService = static::getContainer()->get(JoinRequestService::class);
        $joinRequestService->submit($requester, $club, null);
        $joinRequest = $this->entityManager()->getRepository(JoinRequest::class)->findOneBy(['user' => $requester, 'club' => $club]);

        $this->client->loginUser($manager);
        $host = 'https://beauce.' . static::getContainer()->getParameter('domain');
        $this->client->request('POST', $host . '/invitations/demandes/' . $joinRequest->getId() . '/ignorer', ['_token' => 'wrong-token']);

        $this->assertResponseStatusCodeSame(403);
        $reloaded = $this->entityManager()->getRepository(JoinRequest::class)->find($joinRequest->getId());
        $this->assertTrue($reloaded->isOpen());
    }

    public function testAcceptingInvitationClosesOpenJoinRequestAndCreatesMembership(): void
    {
        $manager = $this->createUser('manager3@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();

        $requester = $this->createUser('pas-encore-membre@example.test');

        /** @var JoinRequestService $joinRequestService */
        $joinRequestService = static::getContainer()->get(JoinRequestService::class);
        $joinRequestService->submit($requester, $club, null);

        /** @var InvitationService $invitationService */
        $invitationService = static::getContainer()->get(InvitationService::class);
        $invitation = $invitationService->createInvitation($club, ['email' => $requester->getEmail()]);
        $membership = $invitationService->acceptInvitation($requester, $invitation);

        $this->assertSame($club, $membership->getClub());
        $this->assertSame($requester, $membership->getUser());

        $joinRequest = $this->entityManager()->getRepository(JoinRequest::class)->findOneBy(['user' => $requester, 'club' => $club]);
        $this->assertNotNull($joinRequest);
        $this->assertFalse($joinRequest->isOpen());
        $this->assertSame($requester, $joinRequest->getHandledBy());
    }

    public function testManagerAcceptsJoinRequestWithRoles(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();
        $requester = $this->createUser('candidat@example.test');
        $joinRequest = static::getContainer()->get(\App\Service\Onboarding\JoinRequestService::class)->submit($requester, $club, null);

        $this->client->loginUser($manager);
        $crawler = $this->client->request('GET', 'https://beauce.' . static::getContainer()->getParameter('domain') . '/invitations');
        $form = $crawler->selectButton('Accepter et ajouter au club')->form();
        $form['isInspector']->tick();
        $this->client->submit($form);

        $this->assertResponseRedirects('/invitations');
        $this->assertEmailCount(1);
        $this->assertEmailAddressContains($this->getMailerMessage(), 'to', 'candidat@example.test');

        $refreshedClub = $this->entityManager()->getRepository(\App\Entity\Club::class)->find($club->getId());
        $refreshedRequester = $this->entityManager()->getRepository(\App\Entity\User::class)->find($requester->getId());
        $membership = $refreshedClub->getMembership($refreshedRequester);
        $this->assertNotNull($membership);
        $this->assertTrue($membership->isInspector());
        $this->assertFalse($membership->isManager());
        $this->assertFalse($this->entityManager()->getRepository(JoinRequest::class)->find($joinRequest->getId())->isOpen());
    }

    public function testAcceptingJoinRequestWithWrongTokenIsForbidden(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();
        $requester = $this->createUser('candidat@example.test');
        $joinRequest = static::getContainer()->get(\App\Service\Onboarding\JoinRequestService::class)->submit($requester, $club, null);

        $this->client->loginUser($manager);
        $this->client->request('POST', 'https://beauce.' . static::getContainer()->getParameter('domain') . '/invitations/demandes/' . $joinRequest->getId() . '/accepter', ['_token' => 'wrong']);

        $this->assertResponseStatusCodeSame(403);
    }
}
