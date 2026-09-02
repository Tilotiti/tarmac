<?php

namespace App\Tests\Functional;

use App\Entity\Club;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function www(string $path): string
    {
        return 'https://www.' . static::getContainer()->getParameter('domain') . $path;
    }

    protected function createUser(string $email, bool $verified = true, bool $admin = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstname('Test');
        $user->setLastname(ucfirst(explode('@', $email)[0]));
        $user->setPassword('$2y$04$abcdefghijklmnopqrstuu'); // hash factice, jamais vérifié dans ces tests
        $user->setVerified($verified);
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    protected function createClub(string $name, string $subdomain): Club
    {
        $club = new Club();
        $club->setName($name);
        $club->setSubdomain($subdomain);
        $this->entityManager()->persist($club);
        $this->entityManager()->flush();

        return $club;
    }
}
