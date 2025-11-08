<?php

namespace App\Tests\Service;

use App\Entity\Plan;
use App\Entity\PlanSubTask;
use App\Entity\PlanTask;
use App\Service\PlanSpreadsheetService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class PlanSpreadsheetServiceTest extends TestCase
{
    public function testExportAndImportReplacesExistingTasks(): void
    {
        $translator = $this->createTranslator();
        $service = new PlanSpreadsheetService($translator);

        $sourcePlan = $this->createSamplePlan();
        $spreadsheet = $service->generateSpreadsheet($sourcePlan);
        $filePath = $this->writeSpreadsheetToTempFile($spreadsheet);

        $uploadedFile = new UploadedFile(
            $filePath,
            'plan.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            0,
            true
        );

        $targetPlan = new Plan();
        $targetPlan->setName('Target plan');
        $legacyTask = new PlanTask();
        $legacyTask->setTitle('Legacy task');
        $legacyTask->setPosition(0);
        $targetPlan->addTaskTemplate($legacyTask);

        $result = $service->importFromFile($targetPlan, $uploadedFile);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->getTaskCount());
        $this->assertSame(2, $result->getSubtaskCount());
        $this->assertCount(1, $targetPlan->getTaskTemplates());

        /** @var PlanTask $task */
        $task = $targetPlan->getTaskTemplates()->first();
        $this->assertSame('Inspection annuelle', $task->getTitle());
        $this->assertSame('Contrôles obligatoires', $task->getDescription());
        $this->assertSame(0, $task->getPosition());

        $this->assertCount(2, $task->getSubTaskTemplates());

        /** @var PlanSubTask $firstSubTask */
        $firstSubTask = $task->getSubTaskTemplates()->first();
        $this->assertSame('Vérifier la structure', $firstSubTask->getTitle());
        $this->assertSame('Inspection visuelle complète', $firstSubTask->getDescription());
        $this->assertSame(3, $firstSubTask->getDifficulty());
        $this->assertTrue($firstSubTask->requiresInspection());
        $this->assertSame(0, $firstSubTask->getPosition());

        /** @var PlanSubTask $secondSubTask */
        $secondSubTask = $task->getSubTaskTemplates()->last();
        $this->assertSame('Tester les commandes', $secondSubTask->getTitle());
        $this->assertSame('', (string) $secondSubTask->getDescription());
        $this->assertSame(2, $secondSubTask->getDifficulty());
        $this->assertFalse($secondSubTask->requiresInspection());
        $this->assertSame(1, $secondSubTask->getPosition());

        @unlink($filePath);
    }

    public function testImportProvidesWarningsAndDefaults(): void
    {
        $translator = $this->createTranslator();
        $service = new PlanSpreadsheetService($translator);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headerLabels = array_map(
            static fn(string $header) => $translator->trans('planSpreadsheet.header.' . $header),
            PlanSpreadsheetService::HEADERS
        );
        $sheet->fromArray($headerLabels, null, 'A1');
        $sheet->fromArray(
            [0, 'Contrôle moteur', 'Vérifications générales', 0, 'Vidange', 'Huile 15W50', '5', 'yes'],
            null,
            'A2'
        );
        $sheet->fromArray(
            [0, 'Contrôle moteur', '', 1, '', '', '2', 'no'],
            null,
            'A3'
        );

        $filePath = $this->writeSpreadsheetToTempFile($spreadsheet);
        $uploadedFile = new UploadedFile(
            $filePath,
            'plan.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            0,
            true
        );

        $targetPlan = new Plan();
        $targetPlan->setName('Engine plan');
        $existingTask = new PlanTask();
        $existingTask->setTitle('Ancienne tâche');
        $existingTask->setPosition(0);
        $targetPlan->addTaskTemplate($existingTask);

        $result = $service->importFromFile($targetPlan, $uploadedFile);

        $this->assertFalse($result->hasErrors());
        $this->assertSame(1, $result->getTaskCount());
        $this->assertSame(1, $result->getSubtaskCount());
        $this->assertCount(1, $targetPlan->getTaskTemplates());

        /** @var PlanTask $task */
        $task = $targetPlan->getTaskTemplates()->first();
        $this->assertSame('Contrôle moteur', $task->getTitle());

        /** @var PlanSubTask $subTask */
        $subTask = $task->getSubTaskTemplates()->first();
        $this->assertSame('Vidange', $subTask->getTitle());
        $this->assertSame('Huile 15W50', $subTask->getDescription());
        $this->assertSame(2, $subTask->getDifficulty(), 'Invalid difficulty should fallback to default value');
        $this->assertTrue($subTask->requiresInspection());

        $warnings = array_filter(
            $result->getRowMessages(),
            static fn(array $message): bool => $message['severity'] === 'warning'
        );
        $this->assertCount(2, $warnings, 'Expected two warnings (invalid difficulty + missing subtask title).');

        @unlink($filePath);
    }

    private function createSamplePlan(): Plan
    {
        $plan = new Plan();
        $plan->setName('Sample plan');

        $task = new PlanTask();
        $task->setTitle('Inspection annuelle');
        $task->setDescription('Contrôles obligatoires');
        $task->setPosition(0);

        $subTaskA = new PlanSubTask();
        $subTaskA->setTitle('Vérifier la structure');
        $subTaskA->setDescription('Inspection visuelle complète');
        $subTaskA->setDifficulty(3);
        $subTaskA->setRequiresInspection(true);
        $subTaskA->setPosition(0);
        $task->addSubTaskTemplate($subTaskA);

        $subTaskB = new PlanSubTask();
        $subTaskB->setTitle('Tester les commandes');
        $subTaskB->setDescription('');
        $subTaskB->setDifficulty(2);
        $subTaskB->setRequiresInspection(false);
        $subTaskB->setPosition(1);
        $task->addSubTaskTemplate($subTaskB);

        $plan->addTaskTemplate($task);

        return $plan;
    }

    private function writeSpreadsheetToTempFile(Spreadsheet $spreadsheet): string
    {
        $filePath = tempnam(sys_get_temp_dir(), 'plan_test_');
        if ($filePath === false) {
            self::fail('Unable to create temporary file for spreadsheet.');
        }

        $fileWithExtension = $filePath . '.xlsx';
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($fileWithExtension);

        @unlink($filePath);

        return $fileWithExtension;
    }

    private function createTranslator(): Translator
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'planSpreadsheet.header.taskPosition' => 'Position tâche',
            'planSpreadsheet.header.taskTitle' => 'Titre de la tâche',
            'planSpreadsheet.header.taskDescription' => 'Description de la tâche',
            'planSpreadsheet.header.subtaskPosition' => 'Position sous-tâche',
            'planSpreadsheet.header.subtaskTitle' => 'Titre de la sous-tâche',
            'planSpreadsheet.header.subtaskDescription' => 'Description de la sous-tâche',
            'planSpreadsheet.header.difficulty' => 'Difficulté',
            'planSpreadsheet.header.requiresInspection' => 'Inspection requise',
        ], 'fr');

        return $translator;
    }
}


