<?php

namespace App\Command;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:gliders',
    description: 'Import gliders and aircraft from a CSV file for a specific club',
)]
class ImportGlidersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('club-subdomain', InputArgument::REQUIRED, 'The subdomain of the club to import gliders for')
            ->addOption('csv-file', null, InputOption::VALUE_OPTIONAL, 'Path to the CSV file', __DIR__ . '/Assets/gliders.csv')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Run without persisting to database')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clubSubdomain = $input->getArgument('club-subdomain');
        $csvFile = $input->getOption('csv-file');
        $dryRun = $input->getOption('dry-run');

        $io->title('Importing Equipment from CSV');

        // Find the club
        $club = $this->entityManager->getRepository(Club::class)->findOneBy(['subdomain' => $clubSubdomain]);

        if (!$club) {
            $io->error(sprintf('Club with subdomain "%s" not found.', $clubSubdomain));
            return Command::FAILURE;
        }

        $io->section(sprintf('Importing equipment for club: %s (%s)', $club->getName(), $club->getSubdomain()));

        // Check if CSV file exists
        if (!file_exists($csvFile)) {
            $io->error(sprintf('CSV file not found: %s', $csvFile));
            return Command::FAILURE;
        }

        $io->text(sprintf('Reading CSV file: %s', $csvFile));

        // Read CSV file
        $handle = fopen($csvFile, 'r');
        if ($handle === false) {
            $io->error('Failed to open CSV file.');
            return Command::FAILURE;
        }

        // Skip header row
        $header = fgetcsv($handle, 0, ';');

        if ($header === false || count($header) < 5) {
            $io->error('Invalid CSV file format. Expected header: type;concours;immat;model;owner');
            fclose($handle);
            return Command::FAILURE;
        }

        $io->text('CSV format validated. Processing rows...');

        $importedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;
        $equipments = [];

        // Process each row
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < 5) {
                $io->warning(sprintf('Skipping invalid row (expected 5 columns, got %d)', count($row)));
                $skippedCount++;
                continue;
            }

            [$type, $concours, $immat, $model, $owner] = $row;

            // Skip empty rows
            if (empty(trim($type)) && empty(trim($model))) {
                $skippedCount++;
                continue;
            }

            // Format: [Concours] - [Model] ([Immat])
            $concoursPart = !empty(trim($concours)) ? trim($concours) . ' - ' : '';
            $immatPart = !empty(trim($immat)) ? ' (' . trim($immat) . ')' : '';
            $name = $concoursPart . trim($model) . $immatPart;

            // Map type to EquipmentType enum
            $equipmentType = match (strtolower(trim($type))) {
                'glider' => EquipmentType::GLIDER,
                'aircraft' => EquipmentType::AIRPLANE,
                default => null,
            };

            if ($equipmentType === null) {
                $io->warning(sprintf('Skipping row with invalid type "%s": %s', $type, $name));
                $errorCount++;
                continue;
            }

            // Map owner to EquipmentOwner enum
            $equipmentOwner = match (strtolower(trim($owner))) {
                'club' => EquipmentOwner::CLUB,
                'private' => EquipmentOwner::PRIVATE ,
                default => null,
            };

            if ($equipmentOwner === null) {
                $io->warning(sprintf('Skipping row with invalid owner "%s": %s', $owner, $name));
                $errorCount++;
                continue;
            }

            // Check if equipment with this name already exists for this club
            $existingEquipment = $this->entityManager->getRepository(Equipment::class)->findOneBy([
                'name' => $name,
                'club' => $club,
            ]);

            if ($existingEquipment) {
                $io->text(sprintf('  ⚠️  Skipped (already exists): %s', $name));
                $skippedCount++;
                continue;
            }

            // Create new equipment
            $equipment = new Equipment();
            $equipment->setName($name);
            $equipment->setType($equipmentType);
            $equipment->setOwner($equipmentOwner);
            $equipment->setClub($club);
            $equipment->setActive(true);
            $equipment->setCreatedAt(new \DateTimeImmutable());

            $equipments[] = [
                'name' => $name,
                'type' => $type,
                'owner' => $owner,
                'entity' => $equipment,
            ];

            if (!$dryRun) {
                $this->entityManager->persist($equipment);
            }

            $ownerBadge = $equipmentOwner === EquipmentOwner::CLUB ? '🏢' : '👤';
            $io->text(sprintf('  ✓ Imported: %s %s [%s]', $ownerBadge, $name, strtoupper($type)));
            $importedCount++;
        }

        fclose($handle);

        // Persist all changes
        if (!$dryRun && $importedCount > 0) {
            $this->entityManager->flush();
            $io->success('All gliders have been persisted to the database.');
        }

        // Summary
        $io->section('Import Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Total rows processed', $importedCount + $skippedCount + $errorCount],
                ['Successfully imported', $importedCount],
                ['Skipped (duplicates/empty)', $skippedCount],
                ['Errors', $errorCount],
            ]
        );

        if ($dryRun) {
            $io->note('This was a dry run. No changes were made to the database.');
        }

        if ($importedCount > 0) {
            $io->success(sprintf('Successfully imported %d equipment item(s) for club "%s".', $importedCount, $club->getName()));
        } elseif ($skippedCount > 0 && $errorCount === 0) {
            $io->info('No new equipment was imported. All entries already exist or were empty.');
        }

        return Command::SUCCESS;
    }
}

