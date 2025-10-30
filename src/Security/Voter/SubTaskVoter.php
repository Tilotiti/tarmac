<?php

namespace App\Security\Voter;

use App\Entity\Enum\EquipmentType;
use App\Entity\SubTask;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SubTaskVoter extends Voter
{
    public const VIEW = 'SUBTASK_VIEW';
    public const EDIT = 'SUBTASK_EDIT';
    public const COMMENT = 'SUBTASK_COMMENT';
    public const DO = 'SUBTASK_DO';
    public const INSPECT = 'SUBTASK_INSPECT';
    public const CANCEL = 'SUBTASK_CANCEL';

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
            self::DO,
            self::INSPECT,
            self::CANCEL,
        ]) && $subject instanceof SubTask;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Admins have access to everything except inspection and edit (which have business logic)
        if ($user->isAdmin() && $attribute !== self::INSPECT && $attribute !== self::EDIT) {
            return true;
        }

        /** @var SubTask $subTask */
        $subTask = $subject;
        $task = $subTask->getTask();
        $club = $task->getClub();

        // Check if user has access to the club
        if (!$user->hasAccessToClub($club)) {
            return false;
        }

        $membership = $user->getMembershipForClub($club);
        $isManager = $user->isManagerOfClub($club);
        $isInspector = $user->isInspectorOfClub($club);
        $isPilote = $membership ? $membership->isPilote() : false;

        return match ($attribute) {
            self::VIEW => $this->canView($subTask, $user, $isManager, $isInspector, $isPilote),
            self::EDIT => $this->canEdit($subTask, $user, $isManager),
            self::COMMENT => true, // Any club member can comment
            self::DO => $this->canDo($subTask, $user, $isManager, $isInspector, $isPilote),
            self::INSPECT => $this->canInspect($subTask, $isInspector),
            self::CANCEL => $this->canCancel($subTask, $user, $isManager),
            default => false,
        };
    }

    private function canEdit(SubTask $subTask, User $user, bool $isManager): bool
    {
        // Can only edit open subtasks
        if ($subTask->getStatus() !== 'open') {
            return false;
        }
        
        // Managers can edit any open subtask
        if ($isManager) {
            return true;
        }
        
        // Members can edit their own open subtasks
        if ($subTask->getCreatedBy() && $subTask->getCreatedBy()->getId() === $user->getId()) {
            return true;
        }
        
        return false;
    }

    private function canCancel(SubTask $subTask, User $user, bool $isManager): bool
    {
        // Can only cancel open subtasks
        if ($subTask->getStatus() !== 'open') {
            return false;
        }
        
        // Managers can cancel any open subtask
        if ($isManager) {
            return true;
        }
        
        // Members can cancel their own open subtasks
        if ($subTask->getCreatedBy() && $subTask->getCreatedBy()->getId() === $user->getId()) {
            return true;
        }
        
        return false;
    }

    private function canView(SubTask $subTask, User $user, bool $isManager, bool $isInspector, bool $isPilote): bool
    {
        $task = $subTask->getTask();
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

    private function canDo(SubTask $subTask, User $user, bool $isManager, bool $isInspector, bool $isPilote): bool
    {
        // Cannot do if cancelled or closed or done
        if ($subTask->isCancelled() || $subTask->isClosed() || $subTask->isDone()) {
            return false;
        }

        $task = $subTask->getTask();
        $equipment = $task->getEquipment();

        // Check pilot rules for aircraft equipment
        if ($equipment->getType()->isAircraft()) {
            // Non-pilotes cannot work on aircraft tasks (unless manager or inspector)
            if (!$isPilote && !$isManager && !$isInspector) {
                return false;
            }
        }

        // Any club member can mark open subtasks as done (with pilot rules above)
        return true;
    }

    private function canInspect(SubTask $subTask, bool $isInspector): bool
    {
        // Must be an inspector
        if (!$isInspector) {
            return false;
        }

        // SubTask must require inspection
        if (!$subTask->requiresInspection()) {
            return false;
        }

        // SubTask must be done and not yet inspected
        return $subTask->isDone() && !$subTask->isInspected() && $subTask->getStatus() === 'done';
    }
}


