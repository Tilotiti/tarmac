<?php

namespace App\Security\Voter;

use App\Entity\Club;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ClubVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const MANAGE = 'MANAGE';
    public const INSPECT = 'INSPECT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Only vote on Club objects with specific attributes
        return $subject instanceof Club && in_array($attribute, [self::VIEW, self::MANAGE, self::INSPECT]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // If user is not logged in, deny access
        if (!$user instanceof User) {
            return false;
        }

        $club = $subject;

        // Admins have access to everything
        if ($user->isAdmin()) {
            return true;
        }

        // Check if user has access to this club
        if (!$user->hasAccessToClub($club)) {
            return false;
        }

        // Check specific permissions
        return match ($attribute) {
            self::VIEW => true, // User has access, so they can view
            self::MANAGE => $user->isManagerOfClub($club),
            self::INSPECT => $user->isInspectorOfClub($club) || $user->isManagerOfClub($club),
            default => false,
        };
    }
}

