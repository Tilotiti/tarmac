<?php

namespace App\Security\Voter;

use App\Entity\Anomaly;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class AnomalyVoter extends Voter
{
    public const VIEW = 'ANOMALY_VIEW';
    public const EDIT = 'ANOMALY_EDIT';
    public const COMMENT = 'ANOMALY_COMMENT';
    public const LINK_TASK = 'ANOMALY_LINK_TASK';
    public const CHANGE_IMPACT = 'ANOMALY_CHANGE_IMPACT';
    public const TREAT = 'ANOMALY_TREAT';
    public const IGNORE = 'ANOMALY_IGNORE';
    public const REOPEN = 'ANOMALY_REOPEN';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::COMMENT,
            self::LINK_TASK,
            self::CHANGE_IMPACT,
            self::TREAT,
            self::IGNORE,
            self::REOPEN,
        ], true) && $subject instanceof Anomaly;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        /** @var Anomaly $anomaly */
        $anomaly = $subject;
        $club = $anomaly->getClub();

        // Admins can read anything; the write attributes carry business logic and
        // still go through the regular membership rules below.
        if ($user->isAdmin() && in_array($attribute, [self::VIEW, self::COMMENT], true)) {
            return true;
        }

        $membership = $user->getMembershipForClub($club);

        if ($membership === null) {
            return false;
        }

        $isManager = $membership->isManager();
        $isInspector = $membership->isInspector();

        return match ($attribute) {
            self::VIEW => $this->canView($anomaly, $user, $isManager, $isInspector),
            // Any member who can see the anomaly can discuss it
            self::COMMENT => $this->canView($anomaly, $user, $isManager, $isInspector),
            self::EDIT => $this->canEdit($anomaly, $user, $isManager),
            self::LINK_TASK => $isManager && !$anomaly->isResolved(),
            self::CHANGE_IMPACT => $isManager && !$anomaly->isResolved(),
            self::TREAT => $isManager && $anomaly->canBeMarkedAsTreated(),
            self::IGNORE => $isManager && !$anomaly->isResolved(),
            self::REOPEN => $isManager && $anomaly->isResolved(),
            default => false,
        };
    }

    /**
     * Same visibility rules as tasks: public equipment is visible to every member,
     * private equipment only to managers, inspectors and its owners.
     */
    private function canView(Anomaly $anomaly, User $user, bool $isManager, bool $isInspector): bool
    {
        $equipment = $anomaly->getEquipment();

        if (!$equipment->isPrivate()) {
            return true;
        }

        if ($isManager || $isInspector) {
            return true;
        }

        return $equipment->getOwners()->contains($user);
    }

    /**
     * The reporter (or the member who recorded it) and any manager may fix the
     * anomaly, as long as it has not been treated or ignored.
     */
    private function canEdit(Anomaly $anomaly, User $user, bool $isManager): bool
    {
        if ($anomaly->isResolved()) {
            return false;
        }

        if ($isManager) {
            return true;
        }

        foreach ([$anomaly->getReportedBy(), $anomaly->getCreatedBy()] as $author) {
            if ($author && $author->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }
}
