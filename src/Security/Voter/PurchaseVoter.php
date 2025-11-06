<?php

namespace App\Security\Voter;

use App\Entity\Enum\PurchaseStatus;
use App\Entity\Purchase;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PurchaseVoter extends Voter
{
    public const VIEW = 'PURCHASE_VIEW';
    public const EDIT = 'PURCHASE_EDIT';
    public const CANCEL = 'PURCHASE_CANCEL';
    public const APPROVE = 'PURCHASE_APPROVE';
    public const MARK_PURCHASED = 'PURCHASE_MARK_PURCHASED';
    public const MARK_DELIVERED = 'PURCHASE_MARK_DELIVERED';
    public const MARK_REIMBURSED = 'PURCHASE_MARK_REIMBURSED';
    public const REVERT_STATUS = 'PURCHASE_REVERT_STATUS';
    public const COMMENT = 'PURCHASE_COMMENT';

    public function __construct(
        private readonly Security $security
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            self::VIEW,
            self::EDIT,
            self::CANCEL,
            self::APPROVE,
            self::MARK_PURCHASED,
            self::MARK_DELIVERED,
            self::MARK_REIMBURSED,
            self::REVERT_STATUS,
            self::COMMENT,
        ]) && $subject instanceof Purchase;
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

        // Admins have access to everything
        if ($user->isAdmin()) {
            return true;
        }

        /** @var Purchase $purchase */
        $purchase = $subject;
        $club = $purchase->getClub();

        // Check if user has access to the club
        if (!$user->hasAccessToClub($club)) {
            return false;
        }

        $membership = $club->getMembership($user);
        $isManager = $membership->isManager();

        return match ($attribute) {
            self::VIEW => true, // Any club member can view
            self::EDIT => $this->canEdit($purchase, $user, $isManager),
            self::CANCEL => $this->canCancel($purchase, $user, $isManager),
            self::APPROVE => $this->canApprove($purchase, $isManager),
            self::MARK_PURCHASED => $this->canMarkPurchased($purchase),
            self::MARK_DELIVERED => $this->canMarkDelivered($purchase),
            self::MARK_REIMBURSED => $this->canMarkReimbursed($purchase, $isManager),
            self::REVERT_STATUS => $this->canRevertStatus($purchase, $user, $isManager),
            self::COMMENT => true, // Any club member can comment
            default => false,
        };
    }

    private function canEdit(Purchase $purchase, User $user, bool $isManager): bool
    {
        // Cannot edit if cancelled or reimbursed
        if (in_array($purchase->getStatus(), [PurchaseStatus::CANCELLED, PurchaseStatus::REIMBURSED])) {
            return false;
        }

        // Managers can always edit (unless cancelled/reimbursed)
        if ($isManager) {
            return true;
        }

        // Members can edit if they created, purchased, or marked as delivered
        $userId = $user->getId();
        
        if ($purchase->getCreatedBy() && $purchase->getCreatedBy()->getId() === $userId) {
            return true;
        }
        
        if ($purchase->getPurchasedBy() && $purchase->getPurchasedBy()->getId() === $userId) {
            return true;
        }
        
        if ($purchase->getDeliveredBy() && $purchase->getDeliveredBy()->getId() === $userId) {
            return true;
        }

        return false;
    }

    private function canCancel(Purchase $purchase, User $user, bool $isManager): bool
    {
        if ($purchase->isCompleted()) {
            return false;
        }

        if ($isManager) {
            return true;
        }

        return $purchase->getCreatedBy() && $purchase->getCreatedBy()->getId() === $user->getId();
    }

    private function canApprove(Purchase $purchase, bool $isManager): bool
    {
        if (!$isManager) {
            return false;
        }

        return $purchase->isPendingApproval();
    }

    private function canMarkPurchased(Purchase $purchase): bool
    {
        return $purchase->canMarkPurchased();
    }

    private function canMarkDelivered(Purchase $purchase): bool
    {
        return $purchase->canMarkDelivered();
    }

    private function canMarkReimbursed(Purchase $purchase, bool $isManager): bool
    {
        if (!$isManager) {
            return false;
        }

        return $purchase->canMarkReimbursed();
    }

    private function canRevertStatus(Purchase $purchase, User $user, bool $isManager): bool
    {
        return $purchase->canRevertStatusBy($user, $isManager);
    }
}

