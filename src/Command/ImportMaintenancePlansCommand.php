<?php

namespace App\Command;

use App\Entity\Club;
use App\Entity\Enum\EquipmentType;
use App\Entity\Plan;
use App\Entity\PlanTask;
use App\Entity\PlanSubTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:maintenance-plans',
    description: 'Import maintenance plans with tasks and subtasks from a CSV file',
)]
class ImportMaintenancePlansCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('csv-file', InputArgument::REQUIRED, 'Path to the CSV file to import')
            ->addOption('club-subdomain', 'c', InputOption::VALUE_REQUIRED, 'Club subdomain to import plans for')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without persisting data to database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $csvFile = $input->getArgument('csv-file');
        $clubSubdomain = $input->getOption('club-subdomain');
        $dryRun = $input->getOption('dry-run');

        // Check if file exists
        if (!file_exists($csvFile)) {
            $io->error(sprintf('File not found: %s', $csvFile));
            return Command::FAILURE;
        }

        // Get the club if subdomain is provided
        $club = null;
        if ($clubSubdomain) {
            $club = $this->entityManager->getRepository(Club::class)->findOneBy(['subdomain' => $clubSubdomain]);
            if (!$club) {
                $io->error(sprintf('Club not found with subdomain: %s', $clubSubdomain));
                return Command::FAILURE;
            }
            $io->info(sprintf('Importing plans for club: %s', $club->getName()));
        } else {
            $io->warning('No club specified. Plans will be created without club assignment. Use --club-subdomain option to assign to a club.');
        }

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No data will be persisted');
        }

        // Parse CSV
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            $io->error('Failed to open CSV file');
            return Command::FAILURE;
        }

        // Read header
        $header = fgetcsv($handle);
        if ($header === false) {
            $io->error('Failed to read CSV header');
            fclose($handle);
            return Command::FAILURE;
        }

        // Validate header
        $expectedColumns = [
            'plan_name',
            'plan_description',
            'plan_equipment_type',
            'task_title',
            'task_description',
            'task_position',
            'subtask_title',
            'subtask_description',
            'subtask_difficulty',
            'subtask_requires_inspection',
            'subtask_position',
        ];

        if ($header !== $expectedColumns) {
            $io->error('Invalid CSV header. Expected columns: ' . implode(', ', $expectedColumns));
            fclose($handle);
            return Command::FAILURE;
        }

        // Store plans, tasks, and subtasks
        $plans = [];
        $tasks = [];
        $rowNumber = 1;
        $errorCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            try {
                $data = array_combine($header, $row);

                // Validate equipment type
                $equipmentTypeValue = strtolower(trim($data['plan_equipment_type']));
                $equipmentType = match ($equipmentTypeValue) {
                    'glider' => EquipmentType::GLIDER,
                    'airplane' => EquipmentType::AIRPLANE,
                    'facility' => EquipmentType::FACILITY,
                    default => throw new \InvalidArgumentException(sprintf('Invalid equipment type: %s (must be glider, airplane, or facility)', $equipmentTypeValue)),
                };

                // Validate difficulty
                $difficulty = (int) $data['subtask_difficulty'];
                if ($difficulty < 1 || $difficulty > 5) {
                    throw new \InvalidArgumentException(sprintf('Invalid difficulty: %d (must be between 1 and 5)', $difficulty));
                }

                // Validate requires_inspection
                $requiresInspection = filter_var($data['subtask_requires_inspection'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($requiresInspection === null) {
                    throw new \InvalidArgumentException(sprintf('Invalid requires_inspection value: %s (must be 0, 1, true, or false)', $data['subtask_requires_inspection']));
                }

                // Get or create plan
                $planKey = $data['plan_name'] . '|' . $equipmentTypeValue;
                if (!isset($plans[$planKey])) {
                    $plan = new Plan();
                    $plan->setName($data['plan_name']);
                    $plan->setDescription($data['plan_description'] ?: null);
                    $plan->setEquipmentType($equipmentType);
                    
                    if ($club) {
                        $plan->setClub($club);
                    }

                    $plans[$planKey] = $plan;
                    $io->text(sprintf('Creating plan: %s (%s)', $data['plan_name'], $equipmentTypeValue));
                }
                $plan = $plans[$planKey];

                // Get or create task
                $taskKey = $planKey . '|' . $data['task_title'] . '|' . $data['task_position'];
                if (!isset($tasks[$taskKey])) {
                    $task = new PlanTask();
                    $task->setTitle($data['task_title']);
                    $task->setDescription($data['task_description'] ?: null);
                    $task->setPosition((int) $data['task_position']);
                    $task->setPlan($plan);
                    $plan->addTaskTemplate($task);

                    $tasks[$taskKey] = $task;
                    $io->text(sprintf('  └─ Creating task: %s (position %d)', $data['task_title'], (int) $data['task_position']));
                }
                $task = $tasks[$taskKey];

                // Create subtask
                $subtask = new PlanSubTask();
                $subtask->setTitle($data['subtask_title']);
                $subtask->setDescription($data['subtask_description'] ?: null);
                $subtask->setDifficulty($difficulty);
                $subtask->setRequiresInspection($requiresInspection);
                $subtask->setPosition((int) $data['subtask_position']);
                $subtask->setTaskTemplate($task);
                $task->addSubTaskTemplate($subtask);

                $inspectionBadge = $requiresInspection ? ' [INSPECTION]' : '';
                $io->text(sprintf('     └─ Creating subtask: %s (difficulty %d, position %d)%s', 
                    $data['subtask_title'], 
                    $difficulty, 
                    (int) $data['subtask_position'],
                    $inspectionBadge
                ));

            } catch (\Exception $e) {
                $io->error(sprintf('Row %d: %s', $rowNumber, $e->getMessage()));
                $errorCount++;
            }
        }

        fclose($handle);

        if ($errorCount > 0) {
            $io->error(sprintf('Failed to parse %d rows', $errorCount));
            return Command::FAILURE;
        }

        // Persist to database
        if (!$dryRun) {
            $io->section('Persisting to database...');
            
            foreach ($plans as $plan) {
                $this->entityManager->persist($plan);
            }

            $this->entityManager->flush();
            $io->success(sprintf('Successfully imported %d plans with their tasks and subtasks', count($plans)));
        } else {
            $io->info(sprintf('DRY RUN: Would have imported %d plans', count($plans)));
        }

        // Display summary
        $io->section('Import Summary');
        $totalTasks = 0;
        $totalSubTasks = 0;
        
        foreach ($plans as $plan) {
            $taskCount = $plan->getTaskTemplates()->count();
            $totalTasks += $taskCount;
            
            foreach ($plan->getTaskTemplates() as $task) {
                $totalSubTasks += $task->getSubTaskTemplates()->count();
            }
        }

        $io->table(
            ['Metric', 'Count'],
            [
                ['Plans', count($plans)],
                ['Tasks', $totalTasks],
                ['Subtasks', $totalSubTasks],
            ]
        );

        return Command::SUCCESS;
    }
}

