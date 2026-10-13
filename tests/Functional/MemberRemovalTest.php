<?php

namespace App\Tests\Functional;

use App\Entity\Club;
use App\Entity\Membership;
use App\Entity\User;

class MemberRemovalTest extends FunctionalTestCase
{
    private function clubUrl(string $path): string
    {
        return 'https://beauce.' . static::getContainer()->getParameter('domain') . $path;
    }

    private function addMember(Club $club, User $user, bool $manager = false): Membership
    {
        $membership = (new Membership())->setUser($user)->setClub($club)->setIsManager($manager);
        $club->addMembership($membership);
        $this->entityManager()->flush();

        return $membership;
    }

    public function testManagerLeavingClubIsSentBackToClubList(): void
    {
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $this->addMember($club, $this->createUser('autre@example.test'), true);
        $manager = $this->createUser('manager@example.test');
        $membership = $this->addMember($club, $manager, true);

        $this->client->loginUser($manager);
        $this->client->request('GET', $this->clubUrl('/members/member/' . $membership->getId()));
        $this->assertSelectorTextContains('form[action$="/delete"] button', 'Quitter le club');

        $this->client->request('POST', $this->clubUrl('/members/member/' . $membership->getId() . '/delete'));

        $this->assertResponseRedirects($this->www('/clubs'));
        $this->assertNull($this->entityManager()->getRepository(Membership::class)->findOneBy(['user' => $manager, 'club' => $club]));
    }

    public function testAdminOutsideClubRemovesMember(): void
    {
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $member = $this->createUser('membre@example.test');
        $membership = $this->addMember($club, $member);

        $this->client->loginUser($this->createUser('admin@example.test', admin: true));
        $this->client->request('GET', $this->clubUrl('/members/member/' . $membership->getId()));
        $this->assertSelectorTextContains('form[action$="/delete"] button', 'Retirer du club');

        $this->client->request('POST', $this->clubUrl('/members/member/' . $membership->getId() . '/delete'));
        $this->assertResponseRedirects($this->clubUrl('/members'));
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->entityManager()->getRepository(Membership::class)->findOneBy(['user' => $member, 'club' => $club]));
    }
}
