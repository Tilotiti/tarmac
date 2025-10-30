<?php

namespace App\Security\Voter;

use App\Entity\Enum\EquipmentType;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class TaskVoter extends Voter
{
    public const VIEW = 'TASK_VIEW';
    public const EDIT = 'TASK_EDIT';
    public const COMMENT = 'TASK_COMMENT';
    public const CREATE_SUBTASK = 'TASK_CREATE_SUBTASK';
    public const CLOSE = 'TASK_CLOSE';
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
            self::COMMENT,
            self::CREATE_SUBTASK,
            self::CLOSE,
            self::CANCEL,
        ]) && $subject instanceof Task;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /**
         * @var User $user
         */
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admins have access to everything except task closing, canceling, editing, and creating subtasks (which have business logic)
        if ($user->isAdmin() && $attribute !== self::CLOSE && $attribute !== self::CANCEL && $attribute !== self::EDIT && $attribute !== self::CREATE_SUBTASK) {
            return true;
        }

        /** @var Task $task */
        $task = $subject;
        $club = $task->getClub();

        // Check if user has access to the club
        if (!$user->hasAccessToClub($club)) {
            return false;
        }

        $membership = $club->getMembership($user);
        $isManager = $membership->isManager();
        $isInspector = $membership->isInspector();
        $isPilote = $membership->isPilote();

        return match ($attribute) {
            self::VIEW => $this->canView($task, $user, $isManager, $isInspector, $isPilote),
            self::EDIT => $this->canEdit($task, $user, $isManager),
            self::COMMENT => true, // Any club member can comment
            self::CREATE_SUBTASK => $this->canCreateSubTask($task, $isManager, $isInspector, $isPilote),
            self::CLOSE => $this->canClose($task, $isManager, $isInspector, $isPilote),
            self::CANCEL => $this->canCancel($task, $user, $isManager),
            default => false,
        };
    }

    private function canEdit(Task $task, User $user, bool $isManager): bool
    {
        // Cannot edit closed or cancelled tasks
        if ($task->isClosed() || $task->getStatus() === 'cancelled') {
            return false;
        }
        
        // Managers can edit any non-closed task
        if ($isManager) {
            return true;
        }
        
        // Members can edit their own tasks that are not closed
        if ($task->getCreatedBy() && $task->getCreatedBy()->getId() === $user->getId()) {
            return true;
        }
        
        return false;
    }

    private function canCancel(Task $task, User $user, bool $isManager): bool
    {
        // Can only cancel open tasks
        if ($task->getStatus() !== 'open') {
            return false;
        }
        
        // Cannot cancel if any subtask has been done
        if ($task->hasAnySubTaskDone()) {
            return false;
        }
        
        // Managers can cancel any open task (without done subtasks)
        if ($isManager) {
            return true;
        }
        
        // Members can cancel their own open tasks (without done subtasks)
        if ($task->getCreatedBy() && $task->getCreatedBy()->getId() === $user->getId()) {
            return true;
        }
        
        return false;
    }

    private function canCreateSubTask(Task $task, bool $isManager, bool $isInspector, bool $isPilote): bool
    {
        // Cannot create subtasks if task is done, closed, or cancelled
        if ($task->isDone() || $task->isClosed() || $task->getStatus() === 'cancelled') {
            return false;
        }
        
        $equipment = $task->getEquipment();

        // For facility equipment: any member can create subtasks
        if ($equipment->getType() === EquipmentType::FACILITY) {
            return true;
        }

        // For aircraft equipment: only pilots, managers, or inspectors can create subtasks
        if ($equipment->getType()->isAircraft()) {
            return $isPilote || $isManager || $isInspector;
        }

        // Default: only managers can create subtasks
        return $isManager;
    }

    private function canClose(Task $task, bool $isManager, bool $isInspector, bool $isPilote): bool
    {
        // Task must be open
        if ($task->getStatus() !== 'open') {
            return false;
        }
        
        // All subtasks must be closed or cancelled
        foreach ($task->getSubTasks() as $subTask) {
            if (!$subTask->isClosed() && !$subTask->isCancelled()) {
                return false;
            }
        }
        
        // Must have at least one subtask
        if ($task->getSubTasks()->count() === 0) {
            return false;
        }
        
        $equipment = $task->getEquipment();

        // For facility equipment: any member can close
        if ($equipment->getType() === EquipmentType::FACILITY) {
            return true;
        }

        // For aircraft equipment: only pilots, managers, or inspectors can close
        if ($equipment->getType()->isAircraft()) {
            return $isPilote || $isManager || $isInspector;
        }

        // Default: only managers can close
        return $isManager;
    }

    private function canView(Task $task, User $user, bool $isManager, bool $isInspector, bool $isPilote): bool
    {
        $equipment = $task->getEquipment();

        // Check pilot visibility rules for aircraft equipment
        if ($equipment->getType()->isAircraft()) {
            // Non-pilotes cannot view aircraft tasks (unless manager or inspector)
            if (!$isPilote && !$isManager && !$isInspector) {
                return false;
            }
        }

        // Public equipment - anyone can view (with pilot rules above)
        if (!$equipment->isPrivate()) {
            return true;
        }

        // Private equipment - only managers, inspectors, and owners
        if ($isManager || $isInspector) {
            return true;
        }

        // Check if user is an owner of the equipment
        return $equipment->getOwners()->contains($user);
    }
}


