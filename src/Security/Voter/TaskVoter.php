<?php

namespace App\Security\Voter;

use App\Entity\Task;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TaskVoter extends Voter
{
    public const VIEW = 'TASK_VIEW';
    public const EDIT = 'TASK_EDIT';
    public const DELETE = 'TASK_DELETE';
    public const COMMENT = 'TASK_COMMENT';
    public const CREATE_SUBTASK = 'TASK_CREATE_SUBTASK';
    public const DO = 'TASK_DO';
    public const CLOSE = 'TASK_CLOSE';
    public const INSPECT = 'TASK_INSPECT';
    public const CANCEL = 'TASK_CANCEL';

    public function __construct(
        private readonly Security $security
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::COMMENT,
            self::CREATE_SUBTASK,
            self::DO ,
            self::CLOSE,
            self::INSPECT,
            self::CANCEL,
        ]) && $subject instanceof Task;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admins have access to everything
        if ($user->isAdmin()) {
            return true;
        }

        /** @var Task $task */
        $task = $subject;
        $club = $task->getClub();

        // Check if user has access to the club
        if (!$user->hasAccessToClub($club)) {
            return false;
        }

        $isManager = $user->isManagerOfClub($club);
        $isInspector = $user->isInspectorOfClub($club);

        return match ($attribute) {
            self::VIEW => $this->canView($task, $user, $isManager, $isInspector),
            self::EDIT => $isManager,
            self::DELETE => $isManager,
            self::COMMENT => true, // Any club member can comment
            self::CREATE_SUBTASK => true, // Any club member can create subtasks
            self::DO => $this->canDo($task, $user),
            self::CLOSE => $isManager,
            self::INSPECT => $this->canInspect($task, $isInspector),
            self::CANCEL => $isManager,
            default => false,
        };
    }

    private function canView(Task $task, User $user, bool $isManager, bool $isInspector): bool
    {
        $equipment = $task->getEquipment();

        // Public equipment - anyone can view
        if (!$equipment->isPrivate()) {
            return true;
        }

        // Private equipment - only managers, inspectors (if task requires inspection), and owners
        if ($isManager) {
            return true;
        }

        if ($task->requiresInspection() && $isInspector) {
            return true;
        }

        // Check if user is an owner of the equipment
        return $equipment->getOwners()->contains($user);
    }

    private function canDo(Task $task, User $user): bool
    {
        // Cannot do if cancelled or closed
        if ($task->isCancelled() || $task->isClosed()) {
            return false;
        }

        // Any club member can mark open tasks as done
        return true;
    }

    private function canInspect(Task $task, bool $isInspector): bool
    {
        // Must be an inspector
        if (!$isInspector) {
            return false;
        }

        // Task must require inspection
        if (!$task->requiresInspection()) {
            return false;
        }

        // Task must be done and not yet inspected
        return $task->isDone() && !$task->isInspected();
    }
}

