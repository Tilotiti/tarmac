<?php

namespace App\Tests\Command;

use App\Entity\Membership;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeUnverifiedUsersCommandTest extends FunctionalTestCase
{
    public function testPurgesOldUnverifiedAccountsWithoutMembership(): void
    {
        $old = $this->createUser('vieux@example.test', verified: false);
        $old->setCreatedAt(new \DateTimeImmutable('-10 days'));
        $recent = $this->createUser('recent@example.test', verified: false);
        $verified = $this->createUser('verifie@example.test');
        $verified->setCreatedAt(new \DateTimeImmutable('-10 days'));
        $member = $this->createUser('membre@example.test', verified: false);
        $member->setCreatedAt(new \DateTimeImmutable('-10 days'));
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($member)->setClub($club));
        $this->entityManager()->flush();

        $tester = new CommandTester((new Application(static::$kernel))->find('app:users:purge-unverified'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $repository = $this->entityManager()->getRepository(User::class);
        $this->assertNull($repository->findOneBy(['email' => 'vieux@example.test']));
        $this->assertNotNull($repository->findOneBy(['email' => 'recent@example.test']));
        $this->assertNotNull($repository->findOneBy(['email' => 'verifie@example.test']));
        $this->assertNotNull($repository->findOneBy(['email' => 'membre@example.test']));
        $this->assertStringContainsString('1 compte', $tester->getDisplay());
    }
}
