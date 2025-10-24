<?php

namespace App\Security\Voter;

use App\Entity\Club;
use App\Entity\User;
use App\Service\ClubResolver;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ClubVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const MANAGE = 'MANAGE';
    public const INSPECT = 'INSPECT';

    public function __construct(
        private readonly ClubResolver $clubResolver
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Vote on these attributes regardless of subject (will resolve club internally)
        return in_array($attribute, [self::VIEW, self::MANAGE, self::INSPECT]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // If user is not logged in, deny access
        if (!$user instanceof User) {
            return false;
        }

        // Admins have access to everything
        if ($user->isAdmin()) {
            return true;
        }

        // Resolve club from subdomain
        $club = $this->clubResolver->resolve();
        if (!$club) {
            return false;
        }

        // Get user's membership for this club
        $membership = $club->getMembership($user);
        if (!$membership) {
            return false;
        }

        // Check specific permissions based on membership
        return match ($attribute) {
            self::VIEW => true, // User has membership, so they can view
            self::MANAGE => $membership->isManager(),
            self::INSPECT => $membership->isInspector(),
            default => false,
        };
    }
}

