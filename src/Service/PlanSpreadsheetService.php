<?php

namespace App\Service;

use App\Entity\Plan;
use App\Entity\PlanSubTask;
use App\Entity\PlanTask;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\Translation\TranslatorInterface;

class PlanSpreadsheetService
{
    /**
     * Header labels expected in the XLSX template.
     */
    public const HEADERS = [
        'taskPosition',
        'taskTitle',
        'taskDescription',
        'subtaskPosition',
        'subtaskTitle',
        'subtaskDescription',
        'difficulty',
        'requiresInspection',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Build a Spreadsheet ready to be streamed as XLSX.
     */
    public function generateSpreadsheet(Plan $plan): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Plan');

        $this->writeHeaders($sheet);

        $rowIndex = 2;
        foreach ($plan->getTaskTemplates() as $task) {
            $subTasks = $task->getSubTaskTemplates();
            if ($subTasks->count() === 0) {
                $rowIndex = $this->writeRow(
                    $sheet,
                    $rowIndex,
                    $task->getPosition(),
                    $task->getTitle(),
                    $task->getDescription(),
                    null,
                    null,
                    null,
                    null
                );
                continue;
            }

            foreach ($subTasks as $subTask) {
                $rowIndex = $this->writeRow(
                    $sheet,
                    $rowIndex,
                    $task->getPosition(),
                    $task->getTitle(),
                    $task->getDescription(),
                    $subTask->getPosition(),
                    $subTask->getTitle(),
                    $subTask->getDescription(),
                    $subTask->getDifficulty(),
                    $subTask->requiresInspection()
                );
            }
        }

        // Auto-size columns for readability.
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Freeze header row.
        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    /**
     * Import plan data from an XLSX file.
     */
    public function importFromFile(Plan $plan, UploadedFile $file): PlanSpreadsheetImportResult
    {
        $result = new PlanSpreadsheetImportResult();

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
        } catch (\Throwable $exception) {
            $result->addError('invalidFile', ['message' => $exception->getMessage()]);
            return $result;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            $result->addError('emptyFile');
            return $result;
        }

        $headers = array_shift($rows);
        $columnMap = $this->mapColumns($headers);

        if ($missing = $this->missingHeaders($columnMap)) {
            $labels = array_map(fn(string $header) => $this->getHeaderLabel($header), $missing);
            $result->addError('missingHeaders', ['headers' => $labels]);
            return $result;
        }

        $tasks = [];
        $taskOrder = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // account for header row
            if ($this->isRowEmpty($row)) {
                continue;
            }

            $taskTitle = $this->extractString($row, $columnMap['taskTitle']);
            if ($taskTitle === '') {
                $result->addRowError($rowNumber, 'missingTaskTitle');
                continue;
            }

            $taskDescription = $this->extractString($row, $columnMap['taskDescription']);
            $taskPositionRaw = $this->extractString($row, $columnMap['taskPosition']);
            $taskPosition = is_numeric($taskPositionRaw) ? (int) $taskPositionRaw : null;

            $taskKey = $taskPosition ?? $taskTitle;
            if (!isset($tasks[$taskKey])) {
                $task = new PlanTask();
                $task->setTitle($taskTitle);
                $task->setDescription($taskDescription);
                $task->setPosition($taskPosition ?? count($tasks));

                $tasks[$taskKey] = $task;
                $taskOrder[] = $taskKey;
            } else {
                $task = $tasks[$taskKey];
                // Update description if current row provides it.
                if ($taskDescription !== '') {
                    $task->setDescription($taskDescription);
                }
            }

            $subtaskTitle = $this->extractString($row, $columnMap['subtaskTitle']);
            $subtaskDescription = $this->extractString($row, $columnMap['subtaskDescription']);
            $difficultyRaw = $this->extractString($row, $columnMap['difficulty']);
            $requiresInspectionRaw = $this->extractString($row, $columnMap['requiresInspection']);
            $subtaskPositionRaw = $this->extractString($row, $columnMap['subtaskPosition']);

            if ($subtaskTitle === '') {
                $result->addRowWarning($rowNumber, 'missingSubtaskTitle');
                continue;
            }

            $difficulty = $this->parseDifficulty($difficultyRaw);
            if ($difficulty === null) {
                $result->addRowWarning($rowNumber, 'invalidDifficulty', ['provided' => $difficultyRaw]);
                $difficulty = 2;
            }

            $requiresInspection = $this->parseBoolean($requiresInspectionRaw);
            $subtask = new PlanSubTask();
            $subtask->setTitle($subtaskTitle);
            $subtask->setDescription($subtaskDescription !== '' ? $subtaskDescription : null);
            $subtask->setDifficulty($difficulty);
            $subtask->setRequiresInspection($requiresInspection);

            if (is_numeric($subtaskPositionRaw)) {
                $subtask->setPosition((int) $subtaskPositionRaw);
            }

            $task->addSubTaskTemplate($subtask);

            $result->incrementSubtaskCount();
        }

        if (empty($tasks)) {
            $result->addError('noTasksFound');
            return $result;
        }

        if ($result->hasErrors()) {
            return $result;
        }

        // Replace existing tasks with the imported ones.
        foreach ($plan->getTaskTemplates()->toArray() as $existingTask) {
            $plan->removeTaskTemplate($existingTask);
        }

        usort($taskOrder, static function ($a, $b) use ($tasks): int {
            $positionA = $tasks[$a]->getPosition();
            $positionB = $tasks[$b]->getPosition();

            return $positionA <=> $positionB;
        });

        $taskPosition = 0;
        foreach ($taskOrder as $taskKey) {
            /** @var PlanTask $task */
            $task = $tasks[$taskKey];
            $task->setPosition($taskPosition++);

            $subtaskPosition = 0;
            foreach ($task->getSubTaskTemplates() as $subtask) {
                $subtask->setPosition($subtaskPosition++);
            }

            $plan->addTaskTemplate($task);
            $result->incrementTaskCount();
        }

        return $result;
    }

    private function writeHeaders(Worksheet $sheet): void
    {
        $labels = [];
        foreach (self::HEADERS as $header) {
            $labels[] = $this->getHeaderLabel($header);
        }

        $sheet->fromArray($labels, null, 'A1');
        $headerRange = sprintf('A1:%s1', $sheet->getHighestColumn());
        $style = $sheet->getStyle($headerRange);
        $style->getFont()->setBold(true);
        $style->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('EEEEEE');
    }

    private function writeRow(
        Worksheet $sheet,
        int $rowIndex,
        ?int $taskPosition,
        ?string $taskTitle,
        ?string $taskDescription,
        ?int $subtaskPosition,
        ?string $subtaskTitle,
        ?string $subtaskDescription,
        ?int $difficulty,
        ?bool $requiresInspection = null
    ): int {
        $sheet->setCellValue("A{$rowIndex}", $taskPosition !== null ? $taskPosition : '');
        $sheet->setCellValue("B{$rowIndex}", $taskTitle ?? '');
        $sheet->setCellValue("C{$rowIndex}", $taskDescription ?? '');
        $sheet->setCellValue("D{$rowIndex}", $subtaskPosition !== null ? $subtaskPosition : '');
        $sheet->setCellValue("E{$rowIndex}", $subtaskTitle ?? '');
        $sheet->setCellValue("F{$rowIndex}", $subtaskDescription ?? '');
        $sheet->setCellValue("G{$rowIndex}", $difficulty ?? '');
        $sheet->setCellValue("H{$rowIndex}", $requiresInspection === null ? '' : ($requiresInspection ? '1' : '0'));

        return $rowIndex + 1;
    }

    private function mapColumns(array $headers): array
    {
        $map = [];
        $lookup = $this->buildHeaderLookup();

        foreach ($headers as $index => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            if (isset($lookup[$normalized])) {
                $canonical = $lookup[$normalized];
                $map[$canonical] = $index;
            }
        }

        return $map;
    }

    private function missingHeaders(array $columnMap): array
    {
        $missing = [];
        foreach (self::HEADERS as $header) {
            if (!array_key_exists($header, $columnMap)) {
                $missing[] = $header;
            }
        }

        return $missing;
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function extractString(array $row, ?int $index): string
    {
        if ($index === null || !array_key_exists($index, $row)) {
            return '';
        }

        return trim((string) $row[$index]);
    }

    private function parseDifficulty(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return 2;
        }

        if (is_numeric($value)) {
            $difficulty = (int) $value;
            return ($difficulty >= 1 && $difficulty <= 3) ? $difficulty : null;
        }

        $normalized = mb_strtolower($value);
        return match ($normalized) {
            'debutant', 'débutant', 'beginner' => 1,
            'experimente', 'expérimenté', 'intermediate' => 2,
            'expert', 'advanced' => 3,
            default => null,
        };
    }

    private function parseBoolean(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = mb_strtolower(trim($value));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'oui', 'vrai', 'y', 'x'], true);
    }

    private function getHeaderLabel(string $header): string
    {
        return $this->translator->trans('planSpreadsheet.header.' . $header);
    }

    private function buildHeaderLookup(): array
    {
        $lookup = [];
        foreach (self::HEADERS as $header) {
            $lookup[$this->normalizeHeader($header)] = $header;
            $translated = $this->normalizeHeader($this->getHeaderLabel($header));
            $lookup[$translated] = $header;
        }

        return $lookup;
    }

    private function normalizeHeader(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}

class PlanSpreadsheetImportResult
{
    private array $errors = [];
    private array $rowWarnings = [];
    private int $tasks = 0;
    private int $subtasks = 0;

    public function addError(string $code, array $context = []): void
    {
        $this->errors[] = ['code' => $code, 'context' => $context];
    }

    public function addRowError(int $row, string $code, array $context = []): void
    {
        $this->rowWarnings[] = [
            'row' => $row,
            'code' => $code,
            'context' => $context,
            'severity' => 'error',
        ];
    }

    public function addRowWarning(int $row, string $code, array $context = []): void
    {
        $this->rowWarnings[] = [
            'row' => $row,
            'code' => $code,
            'context' => $context,
            'severity' => 'warning',
        ];
    }

    public function incrementTaskCount(): void
    {
        $this->tasks++;
    }

    public function incrementSubtaskCount(): void
    {
        $this->subtasks++;
    }

    public function hasErrors(): bool
    {
        if (!empty($this->errors)) {
            return true;
        }

        foreach ($this->rowWarnings as $warning) {
            if ($warning['severity'] === 'error') {
                return true;
            }
        }

        return false;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getRowMessages(): array
    {
        return $this->rowWarnings;
    }

    public function getTaskCount(): int
    {
        return $this->tasks;
    }

    public function getSubtaskCount(): int
    {
        return $this->subtasks;
    }
}


