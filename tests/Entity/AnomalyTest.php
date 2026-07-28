<?php

namespace App\Tests\Entity;

use App\Entity\Anomaly;
use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\AnomalyResolution;
use App\Entity\Enum\AnomalyStatus;
use App\Entity\Task;
use PHPUnit\Framework\TestCase;

/**
 * The anomaly status is derived, never stored: these tests pin down the
 * derivation rules and the guard that opens the "mark as treated" action.
 */
class AnomalyTest extends TestCase
{
    private function createTask(string $status): Task
    {
        $task = new Task();
        $task->setStatus($status);

        return $task;
    }

    public function testStatusIsReportedWithoutAnyTask(): void
    {
        $anomaly = new Anomaly();

        $this->assertSame(AnomalyStatus::REPORTED, $anomaly->getStatus());
    }

    public function testStatusIsReportedWhenEveryLinkedTaskIsCancelled(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addTask($this->createTask('cancelled'));
        $anomaly->addTask($this->createTask('cancelled'));

        $this->assertSame(AnomalyStatus::REPORTED, $anomaly->getStatus());
    }

    public function testStatusIsAcknowledgedWithAtLeastOneActiveTask(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addTask($this->createTask('cancelled'));
        $anomaly->addTask($this->createTask('open'));

        $this->assertSame(AnomalyStatus::ACKNOWLEDGED, $anomaly->getStatus());
    }

    public function testStatusStaysAcknowledgedWhenAllTasksAreClosedButNotConfirmed(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addTask($this->createTask('closed'));

        // Closing the tasks only unlocks the button — a manager still confirms
        $this->assertSame(AnomalyStatus::ACKNOWLEDGED, $anomaly->getStatus());
        $this->assertTrue($anomaly->canBeMarkedAsTreated());
    }

    public function testTreatedResolutionWins(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addTask($this->createTask('open'));
        $anomaly->setResolution(AnomalyResolution::TREATED);

        $this->assertSame(AnomalyStatus::TREATED, $anomaly->getStatus());
    }

    public function testIgnoredResolutionWinsOverEverything(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addTask($this->createTask('open'));
        $anomaly->setResolution(AnomalyResolution::IGNORED);

        $this->assertSame(AnomalyStatus::IGNORED, $anomaly->getStatus());
    }

    public function testCannotBeTreatedWithoutAnyTask(): void
    {
        $anomaly = new Anomaly();

        $this->assertFalse($anomaly->canBeMarkedAsTreated());
    }

    public function testCannotBeTreatedWhenEveryTaskIsCancelled(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addTask($this->createTask('cancelled'));

        $this->assertFalse($anomaly->canBeMarkedAsTreated());
    }

    public function testCannotBeTreatedWhileOneTaskIsStillOpen(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addTask($this->createTask('closed'));
        $anomaly->addTask($this->createTask('open'));

        $this->assertFalse($anomaly->canBeMarkedAsTreated());
    }

    public function testCancelledTasksDoNotBlockTreatment(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addTask($this->createTask('closed'));
        $anomaly->addTask($this->createTask('cancelled'));

        $this->assertTrue($anomaly->canBeMarkedAsTreated());
    }

    public function testCannotBeTreatedTwice(): void
    {
        $anomaly = new Anomaly();
        $anomaly->addTask($this->createTask('closed'));
        $anomaly->setResolution(AnomalyResolution::TREATED);

        $this->assertFalse($anomaly->canBeMarkedAsTreated());
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
}
