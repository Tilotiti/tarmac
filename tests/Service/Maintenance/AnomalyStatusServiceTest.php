<?php

namespace App\Tests\Service\Maintenance;

use App\Entity\Activity;
use App\Entity\Anomaly;
use App\Entity\Enum\ActivityType;
use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\AnomalyResolution;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Entity\User;
use App\Service\CommentNotificationService;
use App\Service\Maintenance\AnomalyStatusService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AnomalyStatusServiceTest extends TestCase
{
    /** @var Activity[] */
    private array $persisted = [];

    private function createService(
        ?CommentNotificationService $notificationService = null,
        ?EntityManagerInterface $entityManager = null
    ): AnomalyStatusService {
        $this->persisted = [];

        if ($entityManager === null) {
            // Stub, not a mock: we only capture what gets persisted
            $entityManager = $this->createStub(EntityManagerInterface::class);
            $entityManager->method('persist')->willReturnCallback(function (object $entity): void {
                if ($entity instanceof Activity) {
                    $this->persisted[] = $entity;
                }
            });
        }

        return new AnomalyStatusService(
            $entityManager,
            $notificationService ?? $this->createStub(CommentNotificationService::class)
        );
    }

    private function createAnomaly(): Anomaly
    {
        $anomaly = new Anomaly();
        $anomaly->setTitle('Fissure sur le bord d\'attaque');
        $anomaly->setDescription('Constatée à la visite prévol.');

        return $anomaly;
    }

    private function createTask(string $status, string $title = 'Réparer'): Task
    {
        $task = new Task();
        $task->setStatus($status);
        $task->setTitle($title);

        return $task;
    }

    /**
     * A subtask filed under an open task, as it is in real life.
     */
    private function createSubTask(string $status = 'open', string $title = 'Changer le pneu'): SubTask
    {
        $subTask = new SubTask();
        $subTask->setStatus($status);
        $subTask->setTitle($title);
        $this->createTask('open')->addSubTask($subTask);

        return $subTask;
    }

    public function testCreateLogsAnActivity(): void
    {
        $service = $this->createService();

        $service->handleCreate($this->createAnomaly(), new User());

        $this->assertCount(1, $this->persisted);
        $this->assertSame(ActivityType::CREATED, $this->persisted[0]->getType());
    }

    public function testCreateNotifiesManagersWhenTheEquipmentIsGrounded(): void
    {
        $anomaly = $this->createAnomaly();
        $anomaly->setImpact(AnomalyImpact::GROUNDED);

        $notificationService = $this->createMock(CommentNotificationService::class);
        $notificationService->expects($this->once())->method('sendAnomalyGroundedNotification');

        $this->createService($notificationService)->handleCreate($anomaly, new User());
    }

    public function testCreateDoesNotNotifyForANormalImpact(): void
    {
        $notificationService = $this->createMock(CommentNotificationService::class);
        $notificationService->expects($this->never())->method('sendAnomalyGroundedNotification');

        $this->createService($notificationService)->handleCreate($this->createAnomaly(), new User());
    }

    public function testChangeImpactIsIgnoredWhenTheImpactIsUnchanged(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $service = $this->createService(null, $entityManager);

        $service->handleChangeImpact($this->createAnomaly(), AnomalyImpact::TO_DETERMINE, new User());
    }

    public function testChangeImpactToGroundedLogsAndNotifies(): void
    {
        $notificationService = $this->createMock(CommentNotificationService::class);
        $notificationService->expects($this->once())->method('sendAnomalyGroundedNotification');

        $anomaly = $this->createAnomaly();

        $this->createService($notificationService)
            ->handleChangeImpact($anomaly, AnomalyImpact::GROUNDED, new User(), 'Aile déposée');

        $this->assertSame(AnomalyImpact::GROUNDED, $anomaly->getImpact());
        $this->assertCount(1, $this->persisted);
        $this->assertSame(ActivityType::IMPACT_CHANGED, $this->persisted[0]->getType());
        $this->assertSame('Aile déposée', $this->persisted[0]->getMessage());
    }

    public function testLinkSubTasksIsIdempotent(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $subTask = $this->createSubTask();

        $this->assertSame(1, $service->handleLinkSubTasks($anomaly, [$subTask], new User()));
        $this->assertSame(0, $service->handleLinkSubTasks($anomaly, [$subTask], new User()));

        $this->assertCount(1, $anomaly->getSubTasks());
        // Une entrée de chaque côté, et rien de plus au second appel
        $this->assertCount(2, $this->persisted);
    }

    public function testLinkSubTasksIsLoggedOnBothSides(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $first = $this->createSubTask('open', 'Changer le pneu');
        $second = $this->createSubTask('open', 'Contrôler la jante');

        $service->handleLinkSubTasks($anomaly, [$first, $second], new User());

        $this->assertCount(4, $this->persisted);

        $onSubTask = $this->activityForSubTask($first);
        $this->assertSame(ActivityType::SUBTASK_LINKED, $onSubTask?->getType());
        $this->assertSame($anomaly->getTitle(), $onSubTask->getMessage());
        // L'entrée miroir porte aussi la tâche, comme les autres entrées de sous-tâche
        $this->assertSame($first->getTask(), $onSubTask->getTask());
        $this->assertSame('subTaskLinkedToAnomaly', $onSubTask->getTypeLabel());

        $onAnomaly = array_values(array_filter(
            $this->persisted,
            static fn (Activity $activity) => $activity->getAnomaly() === $anomaly
        ));
        $this->assertEqualsCanonicalizing(
            ['Changer le pneu', 'Contrôler la jante'],
            array_map(static fn (Activity $activity) => $activity->getMessage(), $onAnomaly)
        );
        $this->assertSame('anomalySubTaskLinked', $onAnomaly[0]->getTypeLabel());
    }

    public function testUnlinkSubTaskDetachesAndLogsBothSides(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $subTask = $this->createSubTask();
        $anomaly->addSubTask($subTask);

        $service->handleUnlinkSubTask($anomaly, $subTask, new User());

        $this->assertCount(0, $anomaly->getSubTasks());
        $this->assertCount(0, $subTask->getAnomalies());
        $this->assertSame(ActivityType::SUBTASK_UNLINKED, $this->activityFor($anomaly)?->getType());
        $this->assertSame(ActivityType::SUBTASK_UNLINKED, $this->activityForSubTask($subTask)?->getType());
    }

    private function activityFor(Anomaly $anomaly): ?Activity
    {
        foreach ($this->persisted as $activity) {
            if ($activity->getAnomaly() === $anomaly) {
                return $activity;
            }
        }

        return null;
    }

    public function testMarkTreatedRefusesAnIneligibleAnomaly(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $service = $this->createService(null, $entityManager);
        $anomaly = $this->createAnomaly();
        $anomaly->addSubTask($this->createSubTask('open'));

        $this->assertFalse($service->handleMarkTreated($anomaly, new User()));
        $this->assertNull($anomaly->getResolution());
    }

    public function testMarkTreatedStampsTheResolution(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $anomaly->addSubTask($this->createSubTask('closed'));
        $user = new User();

        $this->assertTrue($service->handleMarkTreated($anomaly, $user, 'Vérifié au sol'));
        $this->assertSame(AnomalyResolution::TREATED, $anomaly->getResolution());
        $this->assertSame($user, $anomaly->getResolvedBy());
        $this->assertNotNull($anomaly->getResolvedAt());
        $this->assertSame(ActivityType::CLOSED, $this->persisted[0]->getType());
    }

    public function testIgnoreRefusesAnAlreadyResolvedAnomaly(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $anomaly->setResolution(AnomalyResolution::TREATED);

        $this->assertFalse($service->handleIgnore($anomaly, new User()));
        $this->assertCount(0, $this->persisted);
    }

    public function testIgnoreStoresTheReason(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();

        $this->assertTrue($service->handleIgnore($anomaly, new User(), 'Doublon'));
        $this->assertSame(AnomalyResolution::IGNORED, $anomaly->getResolution());
        $this->assertSame(ActivityType::CANCELLED, $this->persisted[0]->getType());
        $this->assertSame('Doublon', $this->persisted[0]->getMessage());
    }

    public function testReopenClearsTheResolution(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $anomaly->setResolution(AnomalyResolution::IGNORED);
        $anomaly->setResolvedBy(new User());
        $anomaly->setResolvedAt(new \DateTimeImmutable());

        $this->assertTrue($service->handleReopen($anomaly, new User(), 'Finalement pas un doublon'));
        $this->assertNull($anomaly->getResolution());
        $this->assertNull($anomaly->getResolvedBy());
        $this->assertNull($anomaly->getResolvedAt());
        $this->assertSame(ActivityType::REOPENED, $this->persisted[0]->getType());
    }

    public function testReopenRefusesAnActiveAnomaly(): void
    {
        $service = $this->createService();

        $this->assertFalse($service->handleReopen($this->createAnomaly(), new User()));
        $this->assertCount(0, $this->persisted);
    }

    private function activityForSubTask(SubTask $subTask): ?Activity
    {
        foreach ($this->persisted as $activity) {
            if ($activity->getSubTask() === $subTask) {
                return $activity;
            }
        }

        return null;
    }
}
