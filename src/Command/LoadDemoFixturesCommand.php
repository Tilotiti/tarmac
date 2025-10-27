<?php

namespace App\Command;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Membership;
use App\Entity\Plan;
use App\Entity\PlanSubTask;
use App\Entity\PlanTask;
use App\Entity\User;
use App\Service\Maintenance\PlanApplier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:fixtures:demo',
    description: 'Load demo fixtures for testing (creates demo club with users, equipment, maintenance plan, and plan applications)',
)]
class LoadDemoFixturesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private PlanApplier $planApplier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Loading Demo Fixtures');

        // Step 1: Delete existing demo club if it exists
        $io->section('Cleaning up existing demo data');
        $existingClub = $this->entityManager->getRepository(Club::class)->findOneBy(['subdomain' => 'demo']);

        if ($existingClub) {
            $io->text('Found existing demo club, deleting...');
            $this->entityManager->remove($existingClub);
            $this->entityManager->flush();
            $io->success('Existing demo club deleted');
        } else {
            $io->text('No existing demo club found');
        }

        // Step 2: Create demo club
        $io->section('Creating demo club');
        $club = new Club();
        $club->setName('Club de Démonstration');
        $club->setSubdomain('demo');
        $club->setActive(true);
        $this->entityManager->persist($club);
        $io->success('Demo club created');

        // Step 3: Create or reuse demo users
        $io->section('Creating demo users');

        $users = [
            'manager' => $this->createOrGetUser(
                'contact+manager@tarmac.club',
                'Alice',
                'Manager',
                'demo123'
            ),
            'inspector' => $this->createOrGetUser(
                'contact+inspector@tarmac.club',
                'Bob',
                'Inspector',
                'demo123'
            ),
            'user' => $this->createOrGetUser(
                'contact+user@tarmac.club',
                'Charlie',
                'Pilot',
                'demo123'
            ),
            'admin' => $this->createOrGetUser(
                'contact+admin@tarmac.club',
                'Diana',
                'Admin',
                'demo123'
            ),
        ];

        $io->success(sprintf('Created/reused %d demo users', count($users)));

        // Step 4: Create memberships
        $io->section('Creating memberships');

        $membership1 = new Membership();
        $membership1->setUser($users['manager']);
        $membership1->setClub($club);
        $membership1->setIsManager(true);
        $membership1->setIsInspector(false);
        $this->entityManager->persist($membership1);

        $membership2 = new Membership();
        $membership2->setUser($users['inspector']);
        $membership2->setClub($club);
        $membership2->setIsManager(false);
        $membership2->setIsInspector(true);
        $this->entityManager->persist($membership2);

        $membership3 = new Membership();
        $membership3->setUser($users['user']);
        $membership3->setClub($club);
        $membership3->setIsManager(false);
        $membership3->setIsInspector(false);
        $this->entityManager->persist($membership3);

        $membership4 = new Membership();
        $membership4->setUser($users['admin']);
        $membership4->setClub($club);
        $membership4->setIsManager(true);
        $membership4->setIsInspector(true);
        $this->entityManager->persist($membership4);

        $io->success('Created 4 memberships');

        // Step 5: Create equipment
        $io->section('Creating equipment');

        // Glider 1 - Club
        $glider1 = new Equipment();
        $glider1->setName('ASK-21 F-XXXA');
        $glider1->setType(EquipmentType::GLIDER);
        $glider1->setOwner(EquipmentOwner::CLUB);
        $glider1->setCreatedBy($users['manager']);
        $glider1->setClub($club);
        $glider1->setActive(true);
        $this->entityManager->persist($glider1);

        // Glider 2 - Club
        $glider2 = new Equipment();
        $glider2->setName('Discus 2b F-XXXB');
        $glider2->setType(EquipmentType::GLIDER);
        $glider2->setOwner(EquipmentOwner::CLUB);
        $glider2->setCreatedBy($users['manager']);
        $glider2->setClub($club);
        $glider2->setActive(true);
        $this->entityManager->persist($glider2);

        // Glider 3 - Private (owned by User 3)
        $glider3 = new Equipment();
        $glider3->setName('LS4 F-XXXC');
        $glider3->setType(EquipmentType::GLIDER);
        $glider3->setOwner(EquipmentOwner::PRIVATE);
        $glider3->setCreatedBy($users['user']);
        $glider3->setClub($club);
        $glider3->setActive(true);
        $glider3->addOwner($users['user']); // Add User 3 as owner
        $this->entityManager->persist($glider3);

        // Infrastructure 1
        $infrastructure1 = new Equipment();
        $infrastructure1->setName('Bureau');
        $infrastructure1->setType(EquipmentType::FACILITY);
        $infrastructure1->setOwner(EquipmentOwner::CLUB);
        $infrastructure1->setCreatedBy($users['manager']);
        $infrastructure1->setClub($club);
        $infrastructure1->setActive(true);
        $this->entityManager->persist($infrastructure1);

        // Infrastructure 2
        $infrastructure2 = new Equipment();
        $infrastructure2->setName('Cuisine');
        $infrastructure2->setType(EquipmentType::FACILITY);
        $infrastructure2->setOwner(EquipmentOwner::CLUB);
        $infrastructure2->setCreatedBy($users['manager']);
        $infrastructure2->setClub($club);
        $infrastructure2->setActive(true);
        $this->entityManager->persist($infrastructure2);

        $io->success('Created 5 equipment items (3 gliders, 2 infrastructures)');

        // Step 6: Create maintenance plan for gliders
        $io->section('Creating annual maintenance plan');

        $plan = new Plan();
        $plan->setName('Visite Annuelle Planeur');
        $plan->setDescription('Plan de maintenance annuelle complet pour les planeurs, incluant inspections structurelles, contrôles système et révisions réglementaires');
        $plan->setEquipmentType(EquipmentType::GLIDER);
        $plan->setClub($club);
        $plan->setCreatedBy($users['manager']);
        $this->entityManager->persist($plan);

        // Task 1: Inspection Structurelle
        $task1 = new PlanTask();
        $task1->setPlan($plan);
        $task1->setTitle('Inspection structure et entoilage');
        $task1->setDescription('Contrôle visuel complet de la structure, recherche de fissures, corrosion, état de l\'entoilage');
        $task1->setDifficulty(4);
        $task1->setRequiresInspection(true);
        $task1->setPosition(1);
        $this->entityManager->persist($task1);

        // Task 1 - SubTasks
        $subTask1_1 = new PlanSubTask();
        $subTask1_1->setTaskTemplate($task1);
        $subTask1_1->setTitle('Inspection ailes et empennages');
        $subTask1_1->setPosition(1);
        $this->entityManager->persist($subTask1_1);

        $subTask1_2 = new PlanSubTask();
        $subTask1_2->setTaskTemplate($task1);
        $subTask1_2->setTitle('Contrôle du fuselage');
        $subTask1_2->setPosition(2);
        $this->entityManager->persist($subTask1_2);

        $subTask1_3 = new PlanSubTask();
        $subTask1_3->setTaskTemplate($task1);
        $subTask1_3->setTitle('Vérification des fixations');
        $subTask1_3->setPosition(3);
        $this->entityManager->persist($subTask1_3);

        // Task 2: Commandes de Vol
        $task2 = new PlanTask();
        $task2->setPlan($plan);
        $task2->setTitle('Contrôle des commandes de vol');
        $task2->setDescription('Vérification du fonctionnement, des jeux, et de l\'état des câbles et renvois');
        $task2->setDifficulty(3);
        $task2->setRequiresInspection(true);
        $task2->setPosition(2);
        $this->entityManager->persist($task2);

        // Task 2 - SubTasks
        $subTask2_1 = new PlanSubTask();
        $subTask2_1->setTaskTemplate($task2);
        $subTask2_1->setTitle('Test débattements ailerons');
        $subTask2_1->setPosition(1);
        $this->entityManager->persist($subTask2_1);

        $subTask2_2 = new PlanSubTask();
        $subTask2_2->setTaskTemplate($task2);
        $subTask2_2->setTitle('Test gouverne de profondeur');
        $subTask2_2->setPosition(2);
        $this->entityManager->persist($subTask2_2);

        $subTask2_3 = new PlanSubTask();
        $subTask2_3->setTaskTemplate($task2);
        $subTask2_3->setTitle('Test gouverne de direction');
        $subTask2_3->setPosition(3);
        $this->entityManager->persist($subTask2_3);

        $subTask2_4 = new PlanSubTask();
        $subTask2_4->setTaskTemplate($task2);
        $subTask2_4->setTitle('Contrôle câbles et poulies');
        $subTask2_4->setPosition(4);
        $this->entityManager->persist($subTask2_4);

        // Task 3: Train d'Atterrissage
        $task3 = new PlanTask();
        $task3->setPlan($plan);
        $task3->setTitle('Révision train d\'atterrissage');
        $task3->setDescription('Contrôle du train, roue, frein, mécanisme de verrouillage');
        $task3->setDifficulty(3);
        $task3->setRequiresInspection(true);
        $task3->setPosition(3);
        $this->entityManager->persist($task3);

        // Task 3 - SubTasks
        $subTask3_1 = new PlanSubTask();
        $subTask3_1->setTaskTemplate($task3);
        $subTask3_1->setTitle('Démontage et inspection roue');
        $subTask3_1->setPosition(1);
        $this->entityManager->persist($subTask3_1);

        $subTask3_2 = new PlanSubTask();
        $subTask3_2->setTaskTemplate($task3);
        $subTask3_2->setTitle('Contrôle système de freinage');
        $subTask3_2->setPosition(2);
        $this->entityManager->persist($subTask3_2);

        // Task 4: Graissage
        $task4 = new PlanTask();
        $task4->setPlan($plan);
        $task4->setTitle('Graissage général');
        $task4->setDescription('Lubrification de tous les points de graissage selon manuel d\'entretien');
        $task4->setDifficulty(2);
        $task4->setRequiresInspection(false);
        $task4->setPosition(4);
        $this->entityManager->persist($task4);

        // Task 5: Verrière et Cockpit
        $task5 = new PlanTask();
        $task5->setPlan($plan);
        $task5->setTitle('Nettoyage verrière et cockpit');
        $task5->setDescription('Nettoyage complet, polissage verrière, vérification étanchéité');
        $task5->setDifficulty(1);
        $task5->setRequiresInspection(false);
        $task5->setPosition(5);
        $this->entityManager->persist($task5);

        // Task 5 - SubTasks
        $subTask5_1 = new PlanSubTask();
        $subTask5_1->setTaskTemplate($task5);
        $subTask5_1->setTitle('Polissage verrière');
        $subTask5_1->setPosition(1);
        $this->entityManager->persist($subTask5_1);

        $subTask5_2 = new PlanSubTask();
        $subTask5_2->setTaskTemplate($task5);
        $subTask5_2->setTitle('Nettoyage instruments');
        $subTask5_2->setPosition(2);
        $this->entityManager->persist($subTask5_2);

        // Task 6: Systèmes Embarqués
        $task6 = new PlanTask();
        $task6->setPlan($plan);
        $task6->setTitle('Contrôle instruments et radio');
        $task6->setDescription('Vérification fonctionnement variomètre, altimètre, anémomètre, compas, radio');
        $task6->setDifficulty(3);
        $task6->setRequiresInspection(true);
        $task6->setPosition(6);
        $this->entityManager->persist($task6);

        // Task 7: Parachute
        $task7 = new PlanTask();
        $task7->setPlan($plan);
        $task7->setTitle('Contrôle parachute de secours');
        $task7->setDescription('Vérification date de validité, état général, sangles');
        $task7->setDifficulty(5);
        $task7->setRequiresInspection(true);
        $task7->setPosition(7);
        $this->entityManager->persist($task7);

        // Task 8: Pesée
        $task8 = new PlanTask();
        $task8->setPlan($plan);
        $task8->setTitle('Pesée et centrage');
        $task8->setDescription('Pesée complète de l\'appareil, calcul du centrage, mise à jour fiche de pesée');
        $task8->setDifficulty(4);
        $task8->setRequiresInspection(true);
        $task8->setPosition(8);
        $this->entityManager->persist($task8);

        // Task 8 - SubTasks
        $subTask8_1 = new PlanSubTask();
        $subTask8_1->setTaskTemplate($task8);
        $subTask8_1->setTitle('Préparation planeur');
        $subTask8_1->setPosition(1);
        $this->entityManager->persist($subTask8_1);

        $subTask8_2 = new PlanSubTask();
        $subTask8_2->setTaskTemplate($task8);
        $subTask8_2->setTitle('Pesée 3 points');
        $subTask8_2->setPosition(2);
        $this->entityManager->persist($subTask8_2);

        $subTask8_3 = new PlanSubTask();
        $subTask8_3->setTaskTemplate($task8);
        $subTask8_3->setTitle('Calculs et documentation');
        $subTask8_3->setPosition(3);
        $this->entityManager->persist($subTask8_3);

        $io->success('Created maintenance plan with 8 tasks and 14 subtasks');

        // Flush and clear to ensure all entities are persisted and detached
        $this->entityManager->flush();

        // Get the plan ID before clearing
        $planId = $plan->getId();

        // Clear the entity manager to detach all entities
        $this->entityManager->clear();

        // Reload the plan with all its associations
        $plan = $this->entityManager->getRepository(Plan::class)->find($planId);
        $glider1 = $this->entityManager->getRepository(Equipment::class)->find($glider1->getId());
        $glider3 = $this->entityManager->getRepository(Equipment::class)->find($glider3->getId());

        // Reload users
        $manager = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'contact+manager@tarmac.club']);
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => 'contact+user@tarmac.club']);

        // Step 7: Apply maintenance plan to a public glider (glider1)
        $io->section('Applying maintenance plan to public glider');

        $publicApplication = $this->planApplier->applyPlan(
            $plan,
            $glider1,
            $manager,
            new \DateTimeImmutable('-2 days')
        );

        $publicTaskCount = $publicApplication->getTasks()->count();
        $publicSubTaskCount = 0;
        foreach ($publicApplication->getTasks() as $task) {
            $publicSubTaskCount += $task->getSubTasks()->count();
        }

        $io->success(sprintf(
            'Applied plan to %s (%d tasks, %d subtasks, due in 3 months)',
            $glider1->getName(),
            $publicTaskCount,
            $publicSubTaskCount
        ));

        // Step 8: Apply maintenance plan to a private glider (glider3)
        $io->section('Applying maintenance plan to private glider');

        $privateApplication = $this->planApplier->applyPlan(
            $plan,
            $glider3,
            $user,
            new \DateTimeImmutable('+1 month')
        );

        $privateTaskCount = $privateApplication->getTasks()->count();
        $privateSubTaskCount = 0;
        foreach ($privateApplication->getTasks() as $task) {
            $privateSubTaskCount += $task->getSubTasks()->count();
        }

        $io->success(sprintf(
            'Applied plan to %s (%d tasks, %d subtasks, due in 1 month)',
            $glider3->getName(),
            $privateTaskCount,
            $privateSubTaskCount
        ));

        $io->success('All demo fixtures loaded successfully!');

        // Summary
        $io->table(
            ['Entity', 'Count'],
            [
                ['Club', '1'],
                ['Users', '4'],
                ['Memberships', '4'],
                ['Equipment', '5 (3 gliders, 2 infrastructures)'],
                ['Plans', '1'],
                ['Plan Tasks (templates)', '8'],
                ['Plan SubTasks (templates)', '14'],
                ['Plan Applications', '2 (1 public glider, 1 private glider)'],
                ['Tasks (from applications)', ($publicTaskCount + $privateTaskCount)],
                ['SubTasks (from applications)', ($publicSubTaskCount + $privateSubTaskCount)],
            ]
        );

        $io->note([
            'Demo club subdomain: demo',
            'Demo club URL: https://demo.tarmac.wip',
            '',
            'Demo users:',
            '  - contact+manager@tarmac.club (Manager) - password: demo123',
            '  - contact+inspector@tarmac.club (Inspector) - password: demo123',
            '  - contact+user@tarmac.club (Regular user) - password: demo123',
            '  - contact+admin@tarmac.club (Manager + Inspector) - password: demo123',
        ]);

        return Command::SUCCESS;
    }

    private function createOrGetUser(string $email, string $firstname, string $lastname, string $plainPassword): User
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if ($user) {
            // User exists, update password and ensure it's verified and active
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            $user->setVerified(true);
            $user->setActive(true);
            return $user;
        }

        // Create new user
        $user = new User();
        $user->setEmail($email);
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setVerified(true);
        $user->setActive(true);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);

        return $user;
    }
}

