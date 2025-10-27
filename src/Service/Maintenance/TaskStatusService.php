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
     * Returns: 'open' | 'closed' | 'cancelled'
     */
    public function getSubTaskState(SubTask $subTask): string
    {
        // Cancelled takes precedence
        if ($subTask->isCancelled()) {
            return 'cancelled';
        }

        // Subtasks auto-close when done (no inspection at subtask level)
        return $subTask->isDone() ? 'closed' : 'open';
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
     * Check if a task can be closed (all subtasks closed, task done and inspected if needed)
     */
    public function canCloseTask(Task $task): bool
    {
        // Task must be done
        if (!$task->isDone()) {
            return false;
        }

        // If requires inspection, must be inspected
        if ($task->requiresInspection() && !$task->isInspected()) {
            return false;
        }

        // All subtasks must be closed
        foreach ($task->getSubTasks() as $subTask) {
            if ($this->getSubTaskState($subTask) !== 'closed') {
                return false;
            }
        }

        return true;
    }

    /**
     * Handle marking a subtask as done
     */
    public function handleSubTaskDone(SubTask $subTask, User $user): void
    {
        $now = new \DateTimeImmutable();
        $subTask->setDoneBy($user);
        $subTask->setDoneAt($now);

        // Subtasks auto-close when done (no inspection at subtask level)
        $subTask->setStatus('closed');

        // Log activity
        $activity = new Activity();
        $activity->setTask($subTask->getTask());
        $activity->setSubTask($subTask);
        $activity->setType(ActivityType::DONE);
        $activity->setUser($user);
        $this->entityManager->persist($activity);

        $this->entityManager->flush();
    }

    /**
     * Handle marking a task as done (for tasks without subtasks or manager override)
     */
    public function handleTaskDone(Task $task, User $user): void
    {
        $now = new \DateTimeImmutable();
        $task->setDoneBy($user);
        $task->setDoneAt($now);

        // If no inspection required, auto-close
        if (!$task->requiresInspection()) {
            $task->setStatus('closed');
            $task->setInspectedBy($user);
            $task->setInspectedAt($now);
        } else {
            // Keep status as open until inspected
            $task->setStatus('open');
        }

        // Log activity
        $activity = new Activity();
        $activity->setTask($task);
        $activity->setType(ActivityType::DONE);
        $activity->setUser($user);
        $this->entityManager->persist($activity);

        $this->entityManager->flush();
    }

    /**
     * Handle inspection approval for a task
     */
    public function handleTaskInspectApprove(Task $task, User $inspector): void
    {
        $task->setInspectedBy($inspector);
        $task->setInspectedAt(new \DateTimeImmutable());

        // Log activity - this is for TASK inspection, not subtask
        $activity = new Activity();
        $activity->setTask($task);
        $activity->setType(ActivityType::INSPECTED_APPROVED);
        $activity->setUser($inspector);
        $this->entityManager->persist($activity);

        // Check if task can now be closed
        if ($this->canCloseTask($task)) {
            $task->setStatus('closed');

            $closeActivity = new Activity();
            $closeActivity->setTask($task);
            $closeActivity->setType(ActivityType::CLOSED);
            $closeActivity->setUser($inspector);
            $this->entityManager->persist($closeActivity);
        }

        $this->entityManager->flush();
    }

    /**
     * Handle inspection rejection for a task
     */
    public function handleTaskInspectReject(Task $task, User $inspector, ?string $reason = null): void
    {
        // Revert to open state
        $task->setStatus('open');
        $task->setDoneBy(null);
        $task->setDoneAt(null);
        $task->setInspectedBy(null);
        $task->setInspectedAt(null);

        // Log activity with rejection reason - this is for TASK inspection, not subtask
        $activity = new Activity();
        $activity->setTask($task);
        $activity->setType(ActivityType::INSPECTED_REJECTED);
        $activity->setUser($inspector);
        $activity->setMessage($reason);
        $this->entityManager->persist($activity);

        $this->entityManager->flush();
    }

    /**
     * Handle closing a task
     */
    public function handleTaskClose(Task $task, User $user): void
    {
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
     * Handle manager marking task as done for another user
     */
    public function handleManagerDo(Task $task, User $manager, User $doneBy, \DateTimeImmutable $doneAt): void
    {
        $task->setDoneBy($doneBy);
        $task->setDoneAt($doneAt);

        // If no inspection required, auto-close
        if (!$task->requiresInspection()) {
            $task->setStatus('closed');
            $task->setInspectedBy($manager);
            $task->setInspectedAt(new \DateTimeImmutable());
        }

        // Log activity
        $activity = new Activity();
        $activity->setTask($task);
        $activity->setType(ActivityType::DONE);
        $activity->setUser($manager);
        $activity->setMessage(sprintf('Marked as done by %s on behalf of %s', $manager->getFullName(), $doneBy->getFullName()));
        $this->entityManager->persist($activity);

        $this->entityManager->flush();
    }

    /**
     * Handle manager marking subtask as done for another user
     */
    public function handleManagerSubTaskDo(SubTask $subTask, User $manager, User $doneBy, \DateTimeImmutable $doneAt): void
    {
        $subTask->setDoneBy($doneBy);
        $subTask->setDoneAt($doneAt);

        // Subtasks auto-close when done (no inspection at subtask level)
        $subTask->setStatus('closed');

        // Log activity
        $activity = new Activity();
        $activity->setTask($subTask->getTask());
        $activity->setSubTask($subTask);
        $activity->setType(ActivityType::DONE);
        $activity->setUser($manager);
        $activity->setMessage(sprintf('Marked as done by %s on behalf of %s', $manager->getFullName(), $doneBy->getFullName()));
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

