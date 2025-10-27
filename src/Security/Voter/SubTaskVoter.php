<?php

namespace App\Security\Voter;

use App\Entity\SubTask;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class SubTaskVoter extends Voter
{
    public const VIEW = 'SUBTASK_VIEW';
    public const EDIT = 'SUBTASK_EDIT';
    public const DELETE = 'SUBTASK_DELETE';
    public const COMMENT = 'SUBTASK_COMMENT';
    public const DO = 'SUBTASK_DO';
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
            self::DELETE,
            self::COMMENT,
            self::DO ,
            self::CANCEL,
        ]) && $subject instanceof SubTask;
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

        /** @var SubTask $subTask */
        $subTask = $subject;
        $task = $subTask->getTask();
        $club = $task->getClub();

        // Check if user has access to the club
        if (!$user->hasAccessToClub($club)) {
            return false;
        }

        $isManager = $user->isManagerOfClub($club);
        $isInspector = $user->isInspectorOfClub($club);

        return match ($attribute) {
            self::VIEW => $this->canView($subTask, $user, $isManager, $isInspector),
            self::EDIT => $isManager,
            self::DELETE => $isManager,
            self::COMMENT => true, // Any club member can comment
            self::DO => $this->canDo($subTask, $user),
            self::CANCEL => $isManager,
            default => false,
        };
    }

    private function canView(SubTask $subTask, User $user, bool $isManager, bool $isInspector): bool
    {
        $task = $subTask->getTask();
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

    private function canDo(SubTask $subTask, User $user): bool
    {
        // Cannot do if cancelled or closed
        if ($subTask->isCancelled() || $subTask->isClosed()) {
            return false;
        }

        // Any club member can mark open subtasks as done
        return true;
    }
}

