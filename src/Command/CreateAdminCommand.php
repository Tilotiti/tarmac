<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Create an admin user',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if admin already exists
        $existingAdmin = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'admin@tarmac.com']);
        if ($existingAdmin) {
            $io->warning('Admin user already exists! Resetting password...');
            $admin = $existingAdmin;
        } else {
            $admin = new User();
            $admin->setEmail('admin@tarmac.com');
            $admin->setFirstname('Admin');
            $admin->setLastname('Tarmac');
            $admin->setRoles(['ROLE_ADMIN']);
            $admin->setActive(true);
            $admin->setVerified(true);
        }

        // Set password to "admin"
        $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin');
        $admin->setPassword($hashedPassword);

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $io->success('Admin user created/updated successfully!');
        $io->info('Email: admin@tarmac.com');
        $io->info('Password: admin');

        return Command::SUCCESS;
    }
}

