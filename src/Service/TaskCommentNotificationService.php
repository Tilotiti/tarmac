<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ContributionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TaskCommentNotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ActivityRepository $activityRepository,
        private readonly ContributionRepository $contributionRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Send email notifications to all users with activity on a task when a comment is added
     */
    public function sendTaskCommentNotifications(Task $task, Activity $comment, User $commentAuthor): void
    {
        $recipients = $this->getUsersWithActivityOnTask($task, $commentAuthor);

        if (empty($recipients)) {
            return;
        }

        $club = $task->getClub();
        $taskUrl = $this->urlGenerator->generate('club_task_show', [
            'id' => $task->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // Send email to each recipient
        foreach ($recipients as $recipient) {
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address('contact@tarmac.center', 'Tarmac'))
                    ->to($recipient->getEmail())
                    ->subject(sprintf(
                        '[%s] Nouveau commentaire sur la tâche : %s',
                        $club->getName(),
                        $task->getTitle()
                    ))
                    ->htmlTemplate('email/task_comment.html.twig')
                    ->context([
                        'club' => $club,
                        'task' => $task,
                        'subTask' => null,
                        'comment' => $comment,
                        'commentAuthor' => $commentAuthor,
                        'taskUrl' => $taskUrl,
                    ])
                ;

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                // Log error but continue with other recipients
                error_log(sprintf('Failed to send task comment notification to %s: %s', $recipient->getEmail(), $e->getMessage()));
            }
        }
    }

    /**
     * Send email notifications to all users with activity on a subtask when a comment is added
     */
    public function sendSubTaskCommentNotifications(SubTask $subTask, Activity $comment, User $commentAuthor): void
    {
        $recipients = $this->getUsersWithActivityOnSubTask($subTask, $commentAuthor);

        if (empty($recipients)) {
            return;
        }

        $task = $subTask->getTask();
        $club = $task->getClub();
        $taskUrl = $this->urlGenerator->generate('club_subtask_show', [
            'taskId' => $task->getId(),
            'id' => $subTask->getId(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        // Send email to each recipient
        foreach ($recipients as $recipient) {
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address('contact@tarmac.center', 'Tarmac'))
                    ->to($recipient->getEmail())
                    ->subject(sprintf(
                        '[%s] Nouveau commentaire sur la sous-tâche : %s',
                        $club->getName(),
                        $subTask->getTitle()
                    ))
                    ->htmlTemplate('email/task_comment.html.twig')
                    ->context([
                        'club' => $club,
                        'task' => $task,
                        'subTask' => $subTask,
                        'comment' => $comment,
                        'commentAuthor' => $commentAuthor,
                        'taskUrl' => $taskUrl,
                    ])
                ;

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                // Log error but continue with other recipients
                error_log(sprintf('Failed to send subtask comment notification to %s: %s', $recipient->getEmail(), $e->getMessage()));
            }
        }
    }

    /**
     * Get all users who have activity on a task (excluding the comment author)
     *
     * @return User[]
     */
    private function getUsersWithActivityOnTask(Task $task, User $excludeUser): array
    {
        $users = [];

        // Add task creator
        if ($task->getCreatedBy() && $task->getCreatedBy()->getId() !== $excludeUser->getId()) {
            $users[$task->getCreatedBy()->getId()] = $task->getCreatedBy();
        }

        // Add task canceller if exists
        if ($task->getCancelledBy() && $task->getCancelledBy()->getId() !== $excludeUser->getId()) {
            $users[$task->getCancelledBy()->getId()] = $task->getCancelledBy();
        }

        // Add all users who have activities on the task
        foreach ($task->getActivities() as $activity) {
            if ($activity->getUser() && $activity->getUser()->getId() !== $excludeUser->getId()) {
                $users[$activity->getUser()->getId()] = $activity->getUser();
            }
        }

        // Add all users who have activities on subtasks
        foreach ($task->getSubTasks() as $subTask) {
            foreach ($subTask->getActivities() as $activity) {
                if ($activity->getUser() && $activity->getUser()->getId() !== $excludeUser->getId()) {
                    $users[$activity->getUser()->getId()] = $activity->getUser();
                }
            }

            // Add users from contributions
            foreach ($subTask->getContributions() as $contribution) {
                $memberUser = $contribution->getMembership()->getUser();
                if ($memberUser && $memberUser->getId() !== $excludeUser->getId()) {
                    $users[$memberUser->getId()] = $memberUser;
                }
            }

            // Add subtask creators/doers/inspectors
            if ($subTask->getCreatedBy() && $subTask->getCreatedBy()->getId() !== $excludeUser->getId()) {
                $users[$subTask->getCreatedBy()->getId()] = $subTask->getCreatedBy();
            }
            if ($subTask->getDoneBy() && $subTask->getDoneBy()->getId() !== $excludeUser->getId()) {
                $users[$subTask->getDoneBy()->getId()] = $subTask->getDoneBy();
            }
            if ($subTask->getCompletedBy() && $subTask->getCompletedBy()->getId() !== $excludeUser->getId()) {
                $users[$subTask->getCompletedBy()->getId()] = $subTask->getCompletedBy();
            }
            if ($subTask->getInspectedBy() && $subTask->getInspectedBy()->getId() !== $excludeUser->getId()) {
                $users[$subTask->getInspectedBy()->getId()] = $subTask->getInspectedBy();
            }
            if ($subTask->getCancelledBy() && $subTask->getCancelledBy()->getId() !== $excludeUser->getId()) {
                $users[$subTask->getCancelledBy()->getId()] = $subTask->getCancelledBy();
            }
        }

        return array_values($users);
    }

    /**
     * Get all users who have activity on a subtask (excluding the comment author)
     *
     * @return User[]
     */
    private function getUsersWithActivityOnSubTask(SubTask $subTask, User $excludeUser): array
    {
        $users = [];

        // Add subtask creator
        if ($subTask->getCreatedBy() && $subTask->getCreatedBy()->getId() !== $excludeUser->getId()) {
            $users[$subTask->getCreatedBy()->getId()] = $subTask->getCreatedBy();
        }

        // Add subtask doers/inspectors
        if ($subTask->getDoneBy() && $subTask->getDoneBy()->getId() !== $excludeUser->getId()) {
            $users[$subTask->getDoneBy()->getId()] = $subTask->getDoneBy();
        }
        if ($subTask->getCompletedBy() && $subTask->getCompletedBy()->getId() !== $excludeUser->getId()) {
            $users[$subTask->getCompletedBy()->getId()] = $subTask->getCompletedBy();
        }
        if ($subTask->getInspectedBy() && $subTask->getInspectedBy()->getId() !== $excludeUser->getId()) {
            $users[$subTask->getInspectedBy()->getId()] = $subTask->getInspectedBy();
        }
        if ($subTask->getCancelledBy() && $subTask->getCancelledBy()->getId() !== $excludeUser->getId()) {
            $users[$subTask->getCancelledBy()->getId()] = $subTask->getCancelledBy();
        }

        // Add all users who have activities on the subtask
        foreach ($subTask->getActivities() as $activity) {
            if ($activity->getUser() && $activity->getUser()->getId() !== $excludeUser->getId()) {
                $users[$activity->getUser()->getId()] = $activity->getUser();
            }
        }

        // Add users from contributions
        foreach ($subTask->getContributions() as $contribution) {
            $memberUser = $contribution->getMembership()->getUser();
            if ($memberUser && $memberUser->getId() !== $excludeUser->getId()) {
                $users[$memberUser->getId()] = $memberUser;
            }
        }

        // Also include users with activity on the parent task
        $task = $subTask->getTask();
        if ($task->getCreatedBy() && $task->getCreatedBy()->getId() !== $excludeUser->getId()) {
            $users[$task->getCreatedBy()->getId()] = $task->getCreatedBy();
        }
        if ($task->getCancelledBy() && $task->getCancelledBy()->getId() !== $excludeUser->getId()) {
            $users[$task->getCancelledBy()->getId()] = $task->getCancelledBy();
        }

        // Add all users who have activities on the parent task
        foreach ($task->getActivities() as $activity) {
            if ($activity->getUser() && $activity->getUser()->getId() !== $excludeUser->getId()) {
                $users[$activity->getUser()->getId()] = $activity->getUser();
            }
        }

        return array_values($users);
    }
}

