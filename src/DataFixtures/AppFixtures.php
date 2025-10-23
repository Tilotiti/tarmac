<?php

namespace App\DataFixtures;

use App\Entity\Club;
use App\Entity\User;
use App\Entity\UserClub;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Create the admin user (Thibault HENRY)
        $admin = new User();
        $admin->setEmail('thibault@henry.pro');
        $admin->setFirstname('Thibault');
        $admin->setLastname('HENRY');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin'));
        $admin->setActive(true);
        $admin->setVerified(true);
        $manager->persist($admin);

        // 2. Create the club "Les planeurs de Bailleau"
        $club = new Club();
        $club->setName('Les planeurs de Bailleau');
        $club->setSubdomain('cvve');
        $club->setActive(true);
        $manager->persist($club);

        // 3. Add Thibault as manager and inspector in the cvve club
        $userClub1 = new UserClub();
        $userClub1->setUser($admin);
        $userClub1->setClub($club);
        $userClub1->setIsManager(true);
        $userClub1->setIsInspector(true);
        $manager->persist($userClub1);

        // 4. Create other users and add them to the cvve club
        // User 2: Manager only
        $user2 = new User();
        $user2->setEmail('manager@cvve.fr');
        $user2->setFirstname('Jean');
        $user2->setLastname('DUPONT');
        $user2->setRoles(['ROLE_USER']);
        $user2->setPassword($this->passwordHasher->hashPassword($user2, 'manager123'));
        $user2->setActive(true);
        $user2->setVerified(true);
        $manager->persist($user2);

        $userClub2 = new UserClub();
        $userClub2->setUser($user2);
        $userClub2->setClub($club);
        $userClub2->setIsManager(true);
        $userClub2->setIsInspector(false);
        $manager->persist($userClub2);

        // User 3: Inspector only
        $user3 = new User();
        $user3->setEmail('inspector@cvve.fr');
        $user3->setFirstname('Marie');
        $user3->setLastname('MARTIN');
        $user3->setRoles(['ROLE_USER']);
        $user3->setPassword($this->passwordHasher->hashPassword($user3, 'inspector123'));
        $user3->setActive(true);
        $user3->setVerified(true);
        $manager->persist($user3);

        $userClub3 = new UserClub();
        $userClub3->setUser($user3);
        $userClub3->setClub($club);
        $userClub3->setIsManager(false);
        $userClub3->setIsInspector(true);
        $manager->persist($userClub3);

        // User 4: Both manager and inspector
        $user4 = new User();
        $user4->setEmail('manager.inspector@cvve.fr');
        $user4->setFirstname('Pierre');
        $user4->setLastname('DURAND');
        $user4->setRoles(['ROLE_USER']);
        $user4->setPassword($this->passwordHasher->hashPassword($user4, 'managerinspector123'));
        $user4->setActive(true);
        $user4->setVerified(true);
        $manager->persist($user4);

        $userClub4 = new UserClub();
        $userClub4->setUser($user4);
        $userClub4->setClub($club);
        $userClub4->setIsManager(true);
        $userClub4->setIsInspector(true);
        $manager->persist($userClub4);

        // User 5: Member (no manager, no inspector)
        $user5 = new User();
        $user5->setEmail('member@cvve.fr');
        $user5->setFirstname('Sophie');
        $user5->setLastname('BERNARD');
        $user5->setRoles(['ROLE_USER']);
        $user5->setPassword($this->passwordHasher->hashPassword($user5, 'member123'));
        $user5->setActive(true);
        $user5->setVerified(true);
        $manager->persist($user5);

        $userClub5 = new UserClub();
        $userClub5->setUser($user5);
        $userClub5->setClub($club);
        $userClub5->setIsManager(false);
        $userClub5->setIsInspector(false);
        $manager->persist($userClub5);

        // 5. Create a user without any club
        $user6 = new User();
        $user6->setEmail('no.club@example.com');
        $user6->setFirstname('Lucas');
        $user6->setLastname('PETIT');
        $user6->setRoles(['ROLE_USER']);
        $user6->setPassword($this->passwordHasher->hashPassword($user6, 'noclub123'));
        $user6->setActive(true);
        $user6->setVerified(true);
        $manager->persist($user6);

        // Flush all changes
        $manager->flush();
    }
}
