<?php

namespace App\Service\Maintenance;

use App\Entity\Enum\ActivityType;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Entity\Activity;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TaskStatusService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get the current state of a subtask based on its flags
     * Returns: 'open' | 'done' | 'closed' | 'cancelled'
     */
    public function getSubTaskState(SubTask $subTask): string
    {
        // Use the actual status from the entity
        return $subTask->getStatus();
    }

    /**
     * Get task progress (percentage of closed subtasks)
     */
    public function getTaskProgress(Task $task): float
    {
        $subTasks = $task->getSubTasks();
        $total = count($subTasks);

        if ($total === 0) {
            return 0.0;
        }

        $closed = 0;
        foreach ($subTasks as $subTask) {
            if ($this->getSubTaskState($subTask) === 'closed') {
                $closed++;
            }
        }

        return round(($closed / $total) * 100, 1);
    }

    /**
     * Check if a task can be closed (all subtasks must be closed)
     */
    public function canCloseTask(Task $task): bool
    {
        // All subtasks must be closed
        foreach ($task->getSubTasks() as $subTask) {
            if (!$subTask->isClosed() && !$subTask->isCancelled()) {
                return false;
            }
        }

        return $task->getSubTasks()->count() > 0;
    }

    /**
     * Check if a subtask can be closed
     */
    public function canCloseSubTask(SubTask $subTask): bool
    {
        // Must be done
        if (!$subTask->isDone()) {
            return false;
        }

        // If requires inspection, must be inspected
        if ($subTask->requiresInspection() && !$subTask->isInspected()) {
            return false;
        }

        return true;
    }

    /**
     * Handle marking a subtask as done
     */
    public function handleSubTaskDone(SubTask $subTask, User $doneByUser, bool $isInspector = false, ?User $completedBy = null): void
    {
        $now = new \DateTimeImmutable();
        $subTask->setDoneBy($doneByUser);
        $subTask->setDoneAt($now);

        // Set who completed/submitted the form (if different from doneBy)
        if ($completedBy !== null) {
            $subTask->setCompletedBy($completedBy);
        }

        // If no inspection required, auto-close
        if (!$subTask->requiresInspection()) {
            $subTask->setStatus('closed');
        }
        // If inspection required but user is qualified (inspector), auto-approve and close
        elseif ($isInspector) {
            $subTask->setStatus('closed');
            $subTask->setInspectedBy($doneByUser);
            $subTask->setInspectedAt($now);
        }
        // Otherwise, set to 'done' status and wait for inspection
        else {
            $subTask->setStatus('done');
        }

        // Log activity with info about who completed it
        $activity = new Activity();
        $activity->setTask($subTask->getTask());
        $activity->setSubTask($subTask);
        $activity->setType(ActivityType::DONE);
        $activity->setUser($doneByUser);

        // Add message if completed by someone different
        if ($completedBy !== null && $completedBy->getId() !== $doneByUser->getId()) {
            $activity->setMessage(sprintf(
                'Complété par %s',
                $completedBy->getFullName() ?: $completedBy->getEmail()
            ));
        }

        $this->entityManager->persist($activity);

        // If auto-approved by qualified member, log inspection approval too
        if ($subTask->requiresInspection() && $isInspector && $subTask->isInspected()) {
            $inspectionActivity = new Activity();
            $inspectionActivity->setTask($subTask->getTask());
            $inspectionActivity->setSubTask($subTask);
            $inspectionActivity->setType(ActivityType::INSPECTED_APPROVED);
            $inspectionActivity->setUser($doneByUser);
            $inspectionActivity->setMessage('Auto-validé (membre qualifié)');
            $this->entityManager->persist($inspectionActivity);
        }

        $this->entityManager->flush();
    }

    /**
     * Handle inspection approval for a subtask
     */
    public function handleSubTaskInspectApprove(SubTask $subTask, User $inspector): void
    {
        $subTask->setInspectedBy($inspector);
        $subTask->setInspectedAt(new \DateTimeImmutable());
        $subTask->setStatus('closed');

        // Log activity
        $activity = new Activity();
        $activity->setTask($subTask->getTask());
        $activity->setSubTask($subTask);
        $activity->setType(ActivityType::INSPECTED_APPROVED);
        $activity->setUser($inspector);
        $this->entityManager->persist($activity);

        $this->entityManager->flush();
    }

    /**
     * Handle inspection rejection for a subtask
     */
    public function handleSubTaskInspectReject(SubTask $subTask, User $inspector, ?string $reason = null): void
    {
        // Revert to open state
        $subTask->setStatus('open');
        $subTask->setDoneBy(null);
        $subTask->setDoneAt(null);
        $subTask->setInspectedBy(null);
        $subTask->setInspectedAt(null);

        // Log activity with rejection reason
        $activity = new Activity();
        $activity->setTask($subTask->getTask());
        $activity->setSubTask($subTask);
        $activity->setType(ActivityType::INSPECTED_REJECTED);
        $activity->setUser($inspector);
        $activity->setMessage($reason);
        $this->entityManager->persist($activity);

        $this->entityManager->flush();
    }


    /**
     * Handle closing a task (only if all subtasks are closed)
     */
    public function handleTaskClose(Task $task, User $user): void
    {
        if (!$this->canCloseTask($task)) {
            throw new \RuntimeException('Cannot close task: not all subtasks are closed');
        }

        $task->setStatus('closed');

        // Log activity
        $activity = new Activity();
        $activity->setTask($task);
        $activity->setType(ActivityType::CLOSED);
        $activity->setUser($user);
        $this->entityManager->persist($activity);

        $this->entityManager->flush();
    }

    /**
     * Handle cancelling a task (cascades to open subtasks)
     */
    public function handleCancelTask(Task $task, User $user, ?string $reason = null): void
    {
        $now = new \DateTimeImmutable();

        // Cancel the task
        $task->setStatus('cancelled');
        $task->setCancelledBy($user);
        $task->setCancelledAt($now);

        // Log activity
        $activity = new Activity();
        $activity->setTask($task);
        $activity->setType(ActivityType::CANCELLED);
        $activity->setUser($user);
        $activity->setMessage($reason);
        $this->entityManager->persist($activity);

        // Cancel all open subtasks
        foreach ($task->getSubTasks() as $subTask) {
            if ($subTask->isOpen()) {
                $subTask->setStatus('cancelled');
                $subTask->setCancelledBy($user);
                $subTask->setCancelledAt($now);

                // Log subtask cancellation
                $subActivity = new Activity();
                $subActivity->setTask($task);
                $subActivity->setSubTask($subTask);
                $subActivity->setType(ActivityType::CANCELLED);
                $subActivity->setUser($user);
                $subActivity->setMessage('Cancelled due to task cancellation');
                $this->entityManager->persist($subActivity);
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Handle cancelling a subtask
     */
    public function handleCancelSubTask(SubTask $subTask, User $user, ?string $reason = null): void
    {
        $subTask->setStatus('cancelled');
        $subTask->setCancelledBy($user);
        $subTask->setCancelledAt(new \DateTimeImmutable());

        // Log activity
        $activity = new Activity();
        $activity->setTask($subTask->getTask());
        $activity->setSubTask($subTask);
        $activity->setType(ActivityType::CANCELLED);
        $activity->setUser($user);
        $activity->setMessage($reason);
        $this->entityManager->persist($activity);

        $this->entityManager->flush();
    }

    /**
     * Handle cancelling a maintenance plan application (cascades to all tasks/subtasks)
     */
    public function handleCancelApplication(\App\Entity\PlanApplication $application, User $user, ?string $reason = null): void
    {
        $now = new \DateTimeImmutable();

        // Cancel the application
        $application->setCancelledBy($user);
        $application->setCancelledAt($now);

        // Cancel all tasks and their subtasks
        foreach ($application->getTasks() as $task) {
            if ($task->isOpen()) {
                $this->handleCancelTask($task, $user, $reason ?? 'Maintenance plan application cancelled');
            }
        }

        $this->entityManager->flush();
    }
}

