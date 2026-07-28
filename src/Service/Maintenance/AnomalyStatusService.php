<?php

namespace App\Service\Maintenance;

use App\Entity\Activity;
use App\Entity\Anomaly;
use App\Entity\Enum\ActivityType;
use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\AnomalyResolution;
use App\Entity\Task;
use App\Entity\User;
use App\Service\CommentNotificationService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Owns every anomaly transition: mutates the entity, records the matching
 * activity, and flushes — the same contract as TaskStatusService.
 */
class AnomalyStatusService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommentNotificationService $notificationService
    ) {
    }

    public function handleCreate(Anomaly $anomaly, User $user): void
    {
        $this->log($anomaly, ActivityType::CREATED, $user);
        $this->entityManager->flush();

        if ($anomaly->isGrounding()) {
            $this->notificationService->sendAnomalyGroundedNotification($anomaly, $user);
        }
    }

    public function handleEdit(Anomaly $anomaly, User $user): void
    {
        $this->log($anomaly, ActivityType::EDITED, $user);
        $this->entityManager->flush();
    }

    public function handleChangeImpact(Anomaly $anomaly, AnomalyImpact $impact, User $user, ?string $message = null): void
    {
        if ($anomaly->getImpact() === $impact) {
            return;
        }

        $anomaly->setImpact($impact);

        $this->log($anomaly, ActivityType::IMPACT_CHANGED, $user, $message);
        $this->entityManager->flush();

        if ($anomaly->isGrounding()) {
            $this->notificationService->sendAnomalyGroundedNotification($anomaly, $user);
        }
    }

    public function handleLinkTask(Anomaly $anomaly, Task $task, User $user): void
    {
        if ($anomaly->getTasks()->contains($task)) {
            return;
        }

        $anomaly->addTask($task);

        // Tracé des deux côtés : le rattachement intéresse autant celui qui suit
        // l'anomalie que celui qui travaille sur la tâche.
        $this->log($anomaly, ActivityType::TASK_LINKED, $user, $task->getTitle());
        $this->logOnTask($task, ActivityType::TASK_LINKED, $user, $anomaly->getTitle());
        $this->entityManager->flush();
    }

    public function handleUnlinkTask(Anomaly $anomaly, Task $task, User $user): void
    {
        if (!$anomaly->getTasks()->contains($task)) {
            return;
        }

        $anomaly->removeTask($task);

        $this->log($anomaly, ActivityType::TASK_UNLINKED, $user, $task->getTitle());
        $this->logOnTask($task, ActivityType::TASK_UNLINKED, $user, $anomaly->getTitle());
        $this->entityManager->flush();
    }

    /**
     * @return bool false when the anomaly was not eligible, leaving it untouched
     */
    public function handleMarkTreated(Anomaly $anomaly, User $user, ?string $message = null): bool
    {
        if (!$anomaly->canBeMarkedAsTreated()) {
            return false;
        }

        $this->resolve($anomaly, AnomalyResolution::TREATED, $user);

        $this->log($anomaly, ActivityType::CLOSED, $user, $message);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return bool false when the anomaly was already resolved
     */
    public function handleIgnore(Anomaly $anomaly, User $user, ?string $reason = null): bool
    {
        if ($anomaly->isResolved()) {
            return false;
        }

        $this->resolve($anomaly, AnomalyResolution::IGNORED, $user);

        $this->log($anomaly, ActivityType::CANCELLED, $user, $reason);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return bool false when the anomaly was not resolved in the first place
     */
    public function handleReopen(Anomaly $anomaly, User $user, ?string $reason = null): bool
    {
        if (!$anomaly->isResolved()) {
            return false;
        }

        $anomaly->setResolution(null);
        $anomaly->setResolvedBy(null);
        $anomaly->setResolvedAt(null);

        $this->log($anomaly, ActivityType::REOPENED, $user, $reason);
        $this->entityManager->flush();

        return true;
    }

    private function resolve(Anomaly $anomaly, AnomalyResolution $resolution, User $user): void
    {
        $anomaly->setResolution($resolution);
        $anomaly->setResolvedBy($user);
        $anomaly->setResolvedAt(new \DateTimeImmutable());
    }

    private function log(Anomaly $anomaly, ActivityType $type, User $user, ?string $message = null): Activity
    {
        $activity = new Activity();
        $activity->setAnomaly($anomaly);
        $activity->setType($type);
        $activity->setUser($user);
        $activity->setMessage($message);

        $this->entityManager->persist($activity);

        return $activity;
    }

    /**
     * Entrée miroir dans le journal de la tâche.
     */
    private function logOnTask(Task $task, ActivityType $type, User $user, ?string $message = null): Activity
    {
        $activity = new Activity();
        $activity->setTask($task);
        $activity->setType($type);
        $activity->setUser($user);
        $activity->setMessage($message);

        $this->entityManager->persist($activity);

        return $activity;
    }
}
