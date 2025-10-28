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
use App\Entity\SubTask;
use App\Entity\Task;
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
    description: 'Load demo fixtures for testing (creates demo club with users, equipment, facility tasks, maintenance plan, and plan applications)',
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
            'nonpilot' => $this->createOrGetUser(
                'contact+nonpilot@tarmac.club',
                'Eve',
                'Member',
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
        $membership1->setIsPilote(true);
        $this->entityManager->persist($membership1);

        $membership2 = new Membership();
        $membership2->setUser($users['inspector']);
        $membership2->setClub($club);
        $membership2->setIsManager(false);
        $membership2->setIsInspector(true);
        $membership2->setIsPilote(true);
        $this->entityManager->persist($membership2);

        $membership3 = new Membership();
        $membership3->setUser($users['user']);
        $membership3->setClub($club);
        $membership3->setIsManager(false);
        $membership3->setIsInspector(false);
        $membership3->setIsPilote(true);
        $this->entityManager->persist($membership3);

        $membership4 = new Membership();
        $membership4->setUser($users['nonpilot']);
        $membership4->setClub($club);
        $membership4->setIsManager(false);
        $membership4->setIsInspector(false);
        $membership4->setIsPilote(false);
        $this->entityManager->persist($membership4);

        $membership5 = new Membership();
        $membership5->setUser($users['admin']);
        $membership5->setClub($club);
        $membership5->setIsManager(true);
        $membership5->setIsInspector(true);
        $membership5->setIsPilote(true);
        $this->entityManager->persist($membership5);

        $io->success('Created 5 memberships');

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

        // Step 6: Create simple tasks for facilities
        $io->section('Creating tasks for facilities');

        // Task for Office (Bureau)
        $officeTask = new Task();
        $officeTask->setClub($club);
        $officeTask->setEquipment($infrastructure1);
        $officeTask->setTitle('Entretien annuel bureau');
        $officeTask->setDescription('Nettoyage et organisation du bureau, vérification des équipements administratifs');
        $officeTask->setDueAt(new \DateTimeImmutable('+2 weeks'));
        $officeTask->setCreatedBy($users['manager']);
        $this->entityManager->persist($officeTask);

        $officeSubTask1 = new SubTask();
        $officeSubTask1->setTask($officeTask);
        $officeSubTask1->setTitle('Nettoyer et ranger le bureau');
        $officeSubTask1->setDescription('Rangement des documents, nettoyage des surfaces, organisation du mobilier');
        $officeSubTask1->setDifficulty(1);
        $officeSubTask1->setRequiresInspection(false);
        $officeSubTask1->setPosition(1);
        $this->entityManager->persist($officeSubTask1);

        $officeSubTask2 = new SubTask();
        $officeSubTask2->setTask($officeTask);
        $officeSubTask2->setTitle('Vérifier les équipements informatiques');
        $officeSubTask2->setDescription('Test imprimante, ordinateur, connexion internet, mise à jour logiciels');
        $officeSubTask2->setDifficulty(2);
        $officeSubTask2->setRequiresInspection(false);
        $officeSubTask2->setPosition(2);
        $this->entityManager->persist($officeSubTask2);

        $officeSubTask3 = new SubTask();
        $officeSubTask3->setTask($officeTask);
        $officeSubTask3->setTitle('Renouveler les fournitures de bureau');
        $officeSubTask3->setDescription('Inventaire et commande des fournitures (papier, stylos, classeurs, etc.)');
        $officeSubTask3->setDifficulty(1);
        $officeSubTask3->setRequiresInspection(false);
        $officeSubTask3->setPosition(3);
        $this->entityManager->persist($officeSubTask3);

        // Task for Kitchen (Cuisine)
        $kitchenTask = new Task();
        $kitchenTask->setClub($club);
        $kitchenTask->setEquipment($infrastructure2);
        $kitchenTask->setTitle('Contrôle hygiène cuisine');
        $kitchenTask->setDescription('Vérification hygiène et sécurité alimentaire, entretien des équipements de cuisine');
        $kitchenTask->setDueAt(new \DateTimeImmutable('+1 week'));
        $kitchenTask->setCreatedBy($users['manager']);
        $this->entityManager->persist($kitchenTask);

        $kitchenSubTask1 = new SubTask();
        $kitchenSubTask1->setTask($kitchenTask);
        $kitchenSubTask1->setTitle('Nettoyage approfondi de la cuisine');
        $kitchenSubTask1->setDescription('Dégraissage surfaces, nettoyage réfrigérateur, désinfection plans de travail');
        $kitchenSubTask1->setDifficulty(2);
        $kitchenSubTask1->setRequiresInspection(false);
        $kitchenSubTask1->setPosition(1);
        $this->entityManager->persist($kitchenSubTask1);

        $kitchenSubTask2 = new SubTask();
        $kitchenSubTask2->setTask($kitchenTask);
        $kitchenSubTask2->setTitle('Vérifier les dates de péremption');
        $kitchenSubTask2->setDescription('Contrôle dates produits alimentaires, élimination produits périmés');
        $kitchenSubTask2->setDifficulty(1);
        $kitchenSubTask2->setRequiresInspection(false);
        $kitchenSubTask2->setPosition(2);
        $this->entityManager->persist($kitchenSubTask2);

        $kitchenSubTask3 = new SubTask();
        $kitchenSubTask3->setTask($kitchenTask);
        $kitchenSubTask3->setTitle('Contrôler les équipements de cuisine');
        $kitchenSubTask3->setDescription('Test four, micro-ondes, cafetière, vérification état vaisselle');
        $kitchenSubTask3->setDifficulty(2);
        $kitchenSubTask3->setRequiresInspection(false);
        $kitchenSubTask3->setPosition(3);
        $this->entityManager->persist($kitchenSubTask3);

        $io->success('Created 2 tasks for facilities (6 subtasks total)');

        // Step 7: Create maintenance plan for gliders
        $io->section('Creating annual maintenance plan');

        $plan = new Plan();
        $plan->setName('Visite Annuelle 100h - Planeur');
        $plan->setDescription('Plan de maintenance réglementaire 100h incluant inspections structurelles, contrôles de navigabilité et révisions système conformes EASA CS-22');
        $plan->setEquipmentType(EquipmentType::GLIDER);
        $plan->setClub($club);
        $plan->setCreatedBy($users['manager']);
        $this->entityManager->persist($plan);

        // Task 1: Inspection Structurelle (Safety-Critical)
        $task1 = new PlanTask();
        $task1->setPlan($plan);
        $task1->setTitle('Inspection structure primaire');
        $task1->setDescription('Contrôle non-destructif de la cellule, recherche de fissures, délaminage, corrosion. Inspection visuelle et tactile conforme ATA 51.');
        $task1->setPosition(1);
        $this->entityManager->persist($task1);

        // Task 1 - SubTasks
        $subTask1_1 = new PlanSubTask();
        $subTask1_1->setTaskTemplate($task1);
        $subTask1_1->setTitle('Inspection longeron principal et nervures');
        $subTask1_1->setDescription('Contrôle visuel des longerons bois/composite, recherche de fissures, vérification tensions entoilage');
        $subTask1_1->setDifficulty(5);
        $subTask1_1->setRequiresInspection(true); // Safety-critical
        $subTask1_1->setPosition(1);
        $this->entityManager->persist($subTask1_1);

        $subTask1_2 = new PlanSubTask();
        $subTask1_2->setTaskTemplate($task1);
        $subTask1_2->setTitle('Contrôle fuselage et cloisons');
        $subTask1_2->setDescription('Inspection fuselage (recherche corrosion, fissures), contrôle cloisons pare-feu et points d\'ancrage');
        $subTask1_2->setDifficulty(4);
        $subTask1_2->setRequiresInspection(true); // Safety-critical
        $subTask1_2->setPosition(2);
        $this->entityManager->persist($subTask1_2);

        $subTask1_3 = new PlanSubTask();
        $subTask1_3->setTaskTemplate($task1);
        $subTask1_3->setTitle('Inspection empennages (HTP/VTP)');
        $subTask1_3->setDescription('Contrôle plan fixe horizontal/vertical, gouvernes, charnières, jeux, fissures');
        $subTask1_3->setDifficulty(4);
        $subTask1_3->setRequiresInspection(true); // Safety-critical
        $subTask1_3->setPosition(3);
        $this->entityManager->persist($subTask1_3);

        $subTask1_4 = new PlanSubTask();
        $subTask1_4->setTaskTemplate($task1);
        $subTask1_4->setTitle('Vérification fixations et ferrures');
        $subTask1_4->setDescription('Contrôle serrage boulonnerie AN/NAS, état ferrures, freinage fils métalliques');
        $subTask1_4->setDifficulty(3);
        $subTask1_4->setRequiresInspection(true); // Safety-critical
        $subTask1_4->setPosition(4);
        $this->entityManager->persist($subTask1_4);

        // Task 2: Commandes de Vol (Safety-Critical)
        $task2 = new PlanTask();
        $task2->setPlan($plan);
        $task2->setTitle('Commandes de vol - Chaîne cinématique');
        $task2->setDescription('Inspection système complet de commandes : câbles, guignols, renvois, compensateurs. Contrôle débattements, jeux, tensions. Conformité ATA 27.');
        $task2->setPosition(2);
        $this->entityManager->persist($task2);

        // Task 2 - SubTasks
        $subTask2_1 = new PlanSubTask();
        $subTask2_1->setTaskTemplate($task2);
        $subTask2_1->setTitle('Contrôle câbles inox (gainage, torons, cosses)');
        $subTask2_1->setDescription('Inspection visuelle des câbles, recherche brins cassés, vérification cosses serties, lubrification');
        $subTask2_1->setDifficulty(3);
        $subTask2_1->setRequiresInspection(true); // Safety-critical
        $subTask2_1->setPosition(1);
        $this->entityManager->persist($subTask2_1);

        $subTask2_2 = new PlanSubTask();
        $subTask2_2->setTaskTemplate($task2);
        $subTask2_2->setTitle('Test débattements et butées (aileron/profondeur/direction)');
        $subTask2_2->setDescription('Mesure débattements réglementaires, contrôle butées mécaniques, vérification symétrie');
        $subTask2_2->setDifficulty(3);
        $subTask2_2->setRequiresInspection(true); // Safety-critical
        $subTask2_2->setPosition(2);
        $this->entityManager->persist($subTask2_2);

        $subTask2_3 = new PlanSubTask();
        $subTask2_3->setTaskTemplate($task2);
        $subTask2_3->setTitle('Inspection renvois et chapes (jeu, usure, goupillage)');
        $subTask2_3->setDescription('Contrôle jeux paliers, état chapes rotules, vérification goupilles fendues, graissage roulements');
        $subTask2_3->setDifficulty(4);
        $subTask2_3->setRequiresInspection(true); // Safety-critical
        $subTask2_3->setPosition(3);
        $this->entityManager->persist($subTask2_3);

        $subTask2_4 = new PlanSubTask();
        $subTask2_4->setTaskTemplate($task2);
        $subTask2_4->setTitle('Compensateur de profondeur - réglage et sécurisation');
        $subTask2_4->setDescription('Vérification course compensateur, test trim, contrôle blocage vis, état palonnier');
        $subTask2_4->setDifficulty(2);
        $subTask2_4->setRequiresInspection(false); // Réglage non critique
        $subTask2_4->setPosition(4);
        $this->entityManager->persist($subTask2_4);

        // Task 3: Train d'Atterrissage (Safety-Critical)
        $task3 = new PlanTask();
        $task3->setPlan($plan);
        $task3->setTitle('Train d\'atterrissage et freinage');
        $task3->setDescription('Révision complète train principal : roue, pneu, frein, mécanisme verrouillage, ressort amortisseur, roulements');
        $task3->setPosition(3);
        $this->entityManager->persist($task3);

        // Task 3 - SubTasks
        $subTask3_1 = new PlanSubTask();
        $subTask3_1->setTaskTemplate($task3);
        $subTask3_1->setTitle('Démontage roue et contrôle roulements');
        $subTask3_1->setDescription('Dépose roue, nettoyage roulements, contrôle usure pistes, regraissage, remontage avec couples de serrage');
        $subTask3_1->setDifficulty(3);
        $subTask3_1->setRequiresInspection(true); // Safety-critical
        $subTask3_1->setPosition(1);
        $this->entityManager->persist($subTask3_1);

        $subTask3_2 = new PlanSubTask();
        $subTask3_2->setTaskTemplate($task3);
        $subTask3_2->setTitle('Inspection pneu (usure, pression, âge)');
        $subTask3_2->setDescription('Vérification profondeur sculptures, recherche craquelures, contrôle valve, gonflage pression réglementaire');
        $subTask3_2->setDifficulty(2);
        $subTask3_2->setRequiresInspection(false); // Routine check
        $subTask3_2->setPosition(2);
        $this->entityManager->persist($subTask3_2);

        $subTask3_3 = new PlanSubTask();
        $subTask3_3->setTaskTemplate($task3);
        $subTask3_3->setTitle('Révision freins (plaquettes, maître-cylindre, purge)');
        $subTask3_3->setDescription('Contrôle usure plaquettes, état disque, niveau LHM, purge circuit hydraulique, test efficacité');
        $subTask3_3->setDifficulty(4);
        $subTask3_3->setRequiresInspection(true); // Safety-critical
        $subTask3_3->setPosition(3);
        $this->entityManager->persist($subTask3_3);

        $subTask3_4 = new PlanSubTask();
        $subTask3_4->setTaskTemplate($task3);
        $subTask3_4->setTitle('Test mécanisme verrouillage et sécurité');
        $subTask3_4->setDescription('Vérification engagement crochet, test ressort rappel, contrôle indicateur position, essai sol');
        $subTask3_4->setDifficulty(3);
        $subTask3_4->setRequiresInspection(true); // Safety-critical
        $subTask3_4->setPosition(4);
        $this->entityManager->persist($subTask3_4);

        // Task 4: Accouplages Aile-Fuselage (Safety-Critical)
        $task4 = new PlanTask();
        $task4->setPlan($plan);
        $task4->setTitle('Accouplages et ferrures aile-fuselage');
        $task4->setDescription('Inspection manilles, axes, crochets automatiques, contrôle jeux, vérification conformité accouplement');
        $task4->setPosition(4);
        $this->entityManager->persist($task4);

        // Task 4 - SubTasks
        $subTask4_1 = new PlanSubTask();
        $subTask4_1->setTaskTemplate($task4);
        $subTask4_1->setTitle('Inspection axe principal et manilles');
        $subTask4_1->setDescription('Démontage axes, contrôle usure, mesure jeux, vérification couples de serrage');
        $subTask4_1->setDifficulty(4);
        $subTask4_1->setRequiresInspection(true); // Safety-critical
        $subTask4_1->setPosition(1);
        $this->entityManager->persist($subTask4_1);

        $subTask4_2 = new PlanSubTask();
        $subTask4_2->setTaskTemplate($task4);
        $subTask4_2->setTitle('Contrôle crochets automatiques et sécurité');
        $subTask4_2->setDescription('Test fonctionnement crochets, vérification ressorts, contrôle indicateurs verrouillage');
        $subTask4_2->setDifficulty(3);
        $subTask4_2->setRequiresInspection(true); // Safety-critical
        $subTask4_2->setPosition(2);
        $this->entityManager->persist($subTask4_2);

        $subTask4_3 = new PlanSubTask();
        $subTask4_3->setTaskTemplate($task4);
        $subTask4_3->setTitle('Graissage roulements et articulations');
        $subTask4_3->setDescription('Application graisse aéronautique sur points de pivotement, roulements paliers');
        $subTask4_3->setDifficulty(2);
        $subTask4_3->setRequiresInspection(false); // Maintenance routine
        $subTask4_3->setPosition(3);
        $this->entityManager->persist($subTask4_3);

        // Task 5: Verrière et Sécurité Cockpit
        $task5 = new PlanTask();
        $task5->setPlan($plan);
        $task5->setTitle('Verrière et équipements cockpit');
        $task5->setDescription('Inspection verrière, mécanisme ouverture, harnais, largage verrière secours, instruments vol');
        $task5->setPosition(5);
        $this->entityManager->persist($task5);

        // Task 5 - SubTasks
        $subTask5_1 = new PlanSubTask();
        $subTask5_1->setTaskTemplate($task5);
        $subTask5_1->setTitle('Contrôle verrière (fissures, rayures, étanchéité)');
        $subTask5_1->setDescription('Inspection plexiglass, recherche craquelures, test charnières, vérification joints');
        $subTask5_1->setDifficulty(2);
        $subTask5_1->setRequiresInspection(false); // Visual check
        $subTask5_1->setPosition(1);
        $this->entityManager->persist($subTask5_1);

        $subTask5_2 = new PlanSubTask();
        $subTask5_2->setTaskTemplate($task5);
        $subTask5_2->setTitle('Test largage verrière urgence');
        $subTask5_2->setDescription('Vérification fonctionnement système largage secours, contrôle goupilles, test manipulation');
        $subTask5_2->setDifficulty(3);
        $subTask5_2->setRequiresInspection(true); // Safety-critical
        $subTask5_2->setPosition(2);
        $this->entityManager->persist($subTask5_2);

        $subTask5_3 = new PlanSubTask();
        $subTask5_3->setTaskTemplate($task5);
        $subTask5_3->setTitle('Inspection harnais 5 points et boucle inertielle');
        $subTask5_3->setDescription('Contrôle sangles (usure, coupures), test boucle, vérification ancrage structure');
        $subTask5_3->setDifficulty(2);
        $subTask5_3->setRequiresInspection(true); // Safety-critical
        $subTask5_3->setPosition(3);
        $this->entityManager->persist($subTask5_3);

        $subTask5_4 = new PlanSubTask();
        $subTask5_4->setTaskTemplate($task5);
        $subTask5_4->setTitle('Nettoyage instruments et tableau de bord');
        $subTask5_4->setDescription('Dépoussiérage instruments, nettoyage cadrans, vérification éclairage');
        $subTask5_4->setDifficulty(1);
        $subTask5_4->setRequiresInspection(false); // Cosmetic
        $subTask5_4->setPosition(4);
        $this->entityManager->persist($subTask5_4);

        // Task 6: Instruments de Bord et Avionique
        $task6 = new PlanTask();
        $task6->setPlan($plan);
        $task6->setTitle('Instruments de vol et avionique');
        $task6->setDescription('Contrôle précision instruments anémobarométriques, test radio VHF, vérification transpondeur Mode S, compas magnétique');
        $task6->setPosition(6);
        $this->entityManager->persist($task6);

        // Task 6 - SubTasks
        $subTask6_1 = new PlanSubTask();
        $subTask6_1->setTaskTemplate($task6);
        $subTask6_1->setTitle('Étalonnage altimètre et vérification prises statique/totale');
        $subTask6_1->setDescription('Test étanchéité circuit pitot-statique, contrôle précision altimètre QNH 1013 hPa');
        $subTask6_1->setDifficulty(4);
        $subTask6_1->setRequiresInspection(true); // Required for airworthiness
        $subTask6_1->setPosition(1);
        $this->entityManager->persist($subTask6_1);

        $subTask6_2 = new PlanSubTask();
        $subTask6_2->setTaskTemplate($task6);
        $subTask6_2->setTitle('Test variomètre et badin');
        $subTask6_2->setDescription('Contrôle fonctionnement variomètre (montée/descente), test badin vitesse indiquée');
        $subTask6_2->setDifficulty(2);
        $subTask6_2->setRequiresInspection(false); // Functional test
        $subTask6_2->setPosition(2);
        $this->entityManager->persist($subTask6_2);

        $subTask6_3 = new PlanSubTask();
        $subTask6_3->setTaskTemplate($task6);
        $subTask6_3->setTitle('Contrôle radio VHF 8.33 kHz et transpondeur');
        $subTask6_3->setDescription('Test émission/réception sur fréquence test, vérification autonomie batterie, contrôle code transpondeur');
        $subTask6_3->setDifficulty(2);
        $subTask6_3->setRequiresInspection(false); // Functional test
        $subTask6_3->setPosition(3);
        $this->entityManager->persist($subTask6_3);

        // Task 7: Parachute Secours (Safety-Critical)
        $task7 = new PlanTask();
        $task7->setPlan($plan);
        $task7->setTitle('Parachute de secours - Inspection réglementaire');
        $task7->setDescription('Contrôle réglementaire annuel parachute secours : validité, état général, sangles, extracteur, poignée largage');
        $task7->setPosition(7);
        $this->entityManager->persist($task7);

        // Task 7 - SubTasks
        $subTask7_1 = new PlanSubTask();
        $subTask7_1->setTaskTemplate($task7);
        $subTask7_1->setTitle('Vérification date de validité et étiquettes');
        $subTask7_1->setDescription('Contrôle date dernière visite atelier, validité 12 mois, lecture étiquettes constructeur et organisme agréé');
        $subTask7_1->setDifficulty(1);
        $subTask7_1->setRequiresInspection(false); // Document check
        $subTask7_1->setPosition(1);
        $this->entityManager->persist($subTask7_1);

        $subTask7_2 = new PlanSubTask();
        $subTask7_2->setTaskTemplate($task7);
        $subTask7_2->setTitle('Inspection visuelle extracteur et suspentes');
        $subTask7_2->setDescription('Contrôle état extracteur (déchirures, usure), inspection suspentes (coupures, noeuds), vérification élévateurs');
        $subTask7_2->setDifficulty(3);
        $subTask7_2->setRequiresInspection(true); // Safety-critical
        $subTask7_2->setPosition(2);
        $this->entityManager->persist($subTask7_2);

        $subTask7_3 = new PlanSubTask();
        $subTask7_3->setTaskTemplate($task7);
        $subTask7_3->setTitle('Test poignée largage et sangle ventrale');
        $subTask7_3->setDescription('Vérification course poignée, résistance déclenchement, contrôle sangle ventrale (usure, couture)');
        $subTask7_3->setDifficulty(2);
        $subTask7_3->setRequiresInspection(true); // Safety-critical
        $subTask7_3->setPosition(3);
        $this->entityManager->persist($subTask7_3);

        // Task 8: Pesée et Centrage (Regulatory)
        $task8 = new PlanTask();
        $task8->setPlan($plan);
        $task8->setTitle('Pesée réglementaire et centrage');
        $task8->setDescription('Pesée complète 3 points conforme CS-22, calcul centre de gravité, mise à jour fiche de pesée, plaquage résultats');
        $task8->setPosition(8);
        $this->entityManager->persist($task8);

        // Task 8 - SubTasks
        $subTask8_1 = new PlanSubTask();
        $subTask8_1->setTaskTemplate($task8);
        $subTask8_1->setTitle('Préparation appareil (vidange lest, niveau carburant)');
        $subTask8_1->setDescription('Positionnement planeur, calage horizontal, vérification lest eau vidangé, carburant mesuré');
        $subTask8_1->setDifficulty(2);
        $subTask8_1->setRequiresInspection(false); // Preparation
        $subTask8_1->setPosition(1);
        $this->entityManager->persist($subTask8_1);

        $subTask8_2 = new PlanSubTask();
        $subTask8_2->setTaskTemplate($task8);
        $subTask8_2->setTitle('Pesée 3 points (peson calibré)');
        $subTask8_2->setDescription('Relevé poids roue + béquilles, utilisation peson étalonné valide, triple mesure avec moyenne');
        $subTask8_2->setDifficulty(3);
        $subTask8_2->setRequiresInspection(true); // Regulatory requirement
        $subTask8_2->setPosition(2);
        $this->entityManager->persist($subTask8_2);

        $subTask8_3 = new PlanSubTask();
        $subTask8_3->setTaskTemplate($task8);
        $subTask8_3->setTitle('Calcul CG et vérification enveloppe');
        $subTask8_3->setDescription('Calcul centre gravité, positionnement dans enveloppe constructeur, vérification limites avant/arrière');
        $subTask8_3->setDifficulty(4);
        $subTask8_3->setRequiresInspection(true); // Regulatory requirement
        $subTask8_3->setPosition(3);
        $this->entityManager->persist($subTask8_3);

        $subTask8_4 = new PlanSubTask();
        $subTask8_4->setTaskTemplate($task8);
        $subTask8_4->setTitle('Mise à jour fiche de pesée et plaquage cockpit');
        $subTask8_4->setDescription('Rédaction fiche pesée, signature organismes habilités, apposition plaque masse/centrage cockpit');
        $subTask8_4->setDifficulty(2);
        $subTask8_4->setRequiresInspection(true); // Regulatory requirement
        $subTask8_4->setPosition(4);
        $this->entityManager->persist($subTask8_4);

        $io->success('Created maintenance plan with 8 tasks and 29 subtasks');

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

        // Step 8: Apply maintenance plan to a public glider (glider1)
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

        // Step 9: Apply maintenance plan to a private glider (glider3)
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
                ['Users', '5'],
                ['Memberships', '5'],
                ['Equipment', '5 (3 gliders, 2 facilities)'],
                ['Facility Tasks', '2 (office + kitchen)'],
                ['Facility SubTasks', '6'],
                ['Plans', '1'],
                ['Plan Tasks (templates)', '8'],
                ['Plan SubTasks (templates)', '29'],
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
            '  - contact+manager@tarmac.club (Manager, Pilot) - password: demo123',
            '  - contact+inspector@tarmac.club (Inspector, Pilot) - password: demo123',
            '  - contact+user@tarmac.club (Pilot) - password: demo123',
            '  - contact+nonpilot@tarmac.club (Non-Pilot Member) - password: demo123',
            '  - contact+admin@tarmac.club (Manager, Inspector, Pilot) - password: demo123',
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

