<?php

namespace App\Tests\Entity;

use App\Entity\Anomaly;
use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\AnomalyResolution;
use App\Entity\Enum\AnomalyStatus;
use App\Entity\SubTask;
use App\Entity\Task;
use PHPUnit\Framework\TestCase;

/**
 * The anomaly status is derived, never stored: these tests pin down the
 * derivation rules and the guard that opens the "mark as treated" action.
 */
class AnomalyTest extends TestCase
{
    private function createSubTask(string $status, string $taskStatus = 'open'): SubTask
    {
        $task = new Task();
        $task->setStatus($taskStatus);
        $subTask = new SubTask();
        $subTask->setStatus($status);
        $task->addSubTask($subTask);

        return $subTask;
    }

    public function testStatusIsReportedWithoutAnySubTask(): void
    {
        $anomaly = new Anomaly();

        $this->assertSame(AnomalyStatus::REPORTED, $anomaly->getStatus());
    }

    public function testStatusIsReportedWhenEveryLinkedSubTaskIsCancelled(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('cancelled'));
        $anomaly->addSubTask($this->createSubTask('cancelled'));

        $this->assertSame(AnomalyStatus::REPORTED, $anomaly->getStatus());
    }

    public function testStatusIsAcknowledgedWithAtLeastOneActiveSubTask(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('cancelled'));
        $anomaly->addSubTask($this->createSubTask('open'));

        $this->assertSame(AnomalyStatus::ACKNOWLEDGED, $anomaly->getStatus());
    }

    public function testStatusStaysAcknowledgedWhenAllSubTasksAreClosedButNotConfirmed(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('closed'));

        // Closing the subtasks only unlocks the button — a manager still confirms
        $this->assertSame(AnomalyStatus::ACKNOWLEDGED, $anomaly->getStatus());
        $this->assertTrue($anomaly->canBeMarkedAsTreated());
    }

    public function testTreatedResolutionWins(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('open'));
        $anomaly->setResolution(AnomalyResolution::TREATED);

        $this->assertSame(AnomalyStatus::TREATED, $anomaly->getStatus());
    }

    public function testIgnoredResolutionWinsOverEverything(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('open'));
        $anomaly->setResolution(AnomalyResolution::IGNORED);

        $this->assertSame(AnomalyStatus::IGNORED, $anomaly->getStatus());
    }

    public function testCannotBeTreatedWithoutAnySubTask(): void
    {
        $anomaly = new Anomaly();

        $this->assertFalse($anomaly->canBeMarkedAsTreated());
    }

    public function testCannotBeTreatedWhenEverySubTaskIsCancelled(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('cancelled'));

        $this->assertFalse($anomaly->canBeMarkedAsTreated());
    }

    public function testCannotBeTreatedWhileOneSubTaskIsStillOpen(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('closed'));
        $anomaly->addSubTask($this->createSubTask('open'));

        $this->assertFalse($anomaly->canBeMarkedAsTreated());
    }

    public function testCancelledSubTasksDoNotBlockTreatment(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('closed'));
        $anomaly->addSubTask($this->createSubTask('cancelled'));

        $this->assertTrue($anomaly->canBeMarkedAsTreated());
    }

    public function testCannotBeTreatedTwice(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('closed'));
        $anomaly->setResolution(AnomalyResolution::TREATED);

        $this->assertFalse($anomaly->canBeMarkedAsTreated());
    }

    public function testCannotBeTreatedWhileOneSubTaskAwaitsInspection(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('closed'));
        $anomaly->addSubTask($this->createSubTask('done'));

        $this->assertSame(AnomalyStatus::ACKNOWLEDGED, $anomaly->getStatus());
        $this->assertFalse($anomaly->canBeMarkedAsTreated());
    }

    public function testSubTasksOfACancelledTaskDoNotCount(): void
    {
        // handleCancelTask n'annule que les sous-tâches ouvertes : une sous-tâche close
        // d'une tâche annulée garde son statut, mais ne doit plus compter.
        $anomaly = new Anomaly();
        $anomaly->addSubTask($this->createSubTask('closed', 'cancelled'));

        $this->assertSame(AnomalyStatus::REPORTED, $anomaly->getStatus());
        $this->assertFalse($anomaly->canBeMarkedAsTreated());
    }

    public function testLinkIsMaintainedOnBothSides(): void
    {
        $anomaly = new Anomaly();
        $subTask = $this->createSubTask('open');
        $anomaly->addSubTask($subTask);

        $this->assertTrue($subTask->getAnomalies()->contains($anomaly));

        $anomaly->removeSubTask($subTask);

        $this->assertFalse($subTask->getAnomalies()->contains($anomaly));
    }

    public function testIsGroundingOnlyWhileUnresolved(): void
    {
        $anomaly = new Anomaly();
        $anomaly->setImpact(AnomalyImpact::GROUNDED);

        $this->assertTrue($anomaly->isGrounding());

        $anomaly->setResolution(AnomalyResolution::TREATED);

        $this->assertFalse($anomaly->isGrounding());
    }

    public function testPhotosAreAppendedWithoutDuplicatesAndRemovable(): void
    {
        $anomaly = new Anomaly();

        $anomaly->addPhoto('https://static.example/anomalies/a.jpg');
        $anomaly->addPhoto('https://static.example/anomalies/b.jpg');
        $anomaly->addPhoto('https://static.example/anomalies/a.jpg');

        $this->assertSame([
            'https://static.example/anomalies/a.jpg',
            'https://static.example/anomalies/b.jpg',
        ], $anomaly->getPhotos());

        $anomaly->removePhoto('https://static.example/anomalies/a.jpg');

        // Re-indexed so the JSON column stays a list, not an object
        $this->assertSame(['https://static.example/anomalies/b.jpg'], $anomaly->getPhotos());
    }

    public function testTaskListsTheAnomaliesOfItsSubTasksOnce(): void
    {
        $task = new Task();
        $first = new SubTask();
        $second = new SubTask();
        $task->addSubTask($first);
        $task->addSubTask($second);
        $anomaly = new Anomaly();
        $anomaly->addSubTask($first);
        $anomaly->addSubTask($second);

        $this->assertCount(1, $task->getAnomalies());
        $this->assertSame($anomaly, $task->getAnomalies()->first());
    }
}
