<?php

namespace App\Tests\Service\Maintenance;

use App\Entity\Activity;
use App\Entity\Anomaly;
use App\Entity\Enum\ActivityType;
use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\AnomalyResolution;
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

    public function testLinkTaskIsIdempotent(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $task = $this->createTask('open');

        $service->handleLinkTask($anomaly, $task, new User());
        $service->handleLinkTask($anomaly, $task, new User());

        $this->assertCount(1, $anomaly->getTasks());
        // Une entrée de chaque côté, et rien de plus au second appel
        $this->assertCount(2, $this->persisted);
    }

    public function testLinkTaskIsLoggedOnBothSides(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $task = $this->createTask('open', 'Remplacer la rotule');

        $service->handleLinkTask($anomaly, $task, new User());

        $onAnomaly = $this->activityFor($anomaly);
        $onTask = $this->activityForTask($task);

        $this->assertNotNull($onAnomaly, 'le journal de l\'anomalie doit être alimenté');
        $this->assertNotNull($onTask, 'le journal de la tâche doit être alimenté');

        $this->assertSame(ActivityType::TASK_LINKED, $onAnomaly->getType());
        $this->assertSame(ActivityType::TASK_LINKED, $onTask->getType());

        // Chaque côté nomme l'autre
        $this->assertSame('Remplacer la rotule', $onAnomaly->getMessage());
        $this->assertSame($anomaly->getTitle(), $onTask->getMessage());
    }

    public function testUnlinkTaskDetachesBothSidesAndLogsBothSides(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $task = $this->createTask('open');
        $anomaly->addTask($task);

        $service->handleUnlinkTask($anomaly, $task, new User());

        $this->assertCount(0, $anomaly->getTasks());
        $this->assertCount(0, $task->getAnomalies());

        $this->assertSame(ActivityType::TASK_UNLINKED, $this->activityFor($anomaly)?->getType());
        $this->assertSame(ActivityType::TASK_UNLINKED, $this->activityForTask($task)?->getType());
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

    private function activityForTask(Task $task): ?Activity
    {
        foreach ($this->persisted as $activity) {
            if ($activity->getTask() === $task) {
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
        $anomaly->addTask($this->createTask('open'));

        $this->assertFalse($service->handleMarkTreated($anomaly, new User()));
        $this->assertNull($anomaly->getResolution());
    }

    public function testMarkTreatedStampsTheResolution(): void
    {
        $service = $this->createService();
        $anomaly = $this->createAnomaly();
        $anomaly->addTask($this->createTask('closed'));
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
}
