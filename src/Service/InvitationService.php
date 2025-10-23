<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\InvitationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class InvitationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly InvitationRepository $invitationRepository,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Create a new invitation for a club
     */
    public function createInvitation(Club $club, array $data): Invitation
    {
        $invitation = new Invitation();
        $invitation->setClub($club);
        $invitation->setEmail($data['email']);
        $invitation->setFirstname($data['firstname'] ?? null);
        $invitation->setLastname($data['lastname'] ?? null);
        $invitation->setIsManager($data['isManager'] ?? false);
        $invitation->setIsInspector($data['isInspector'] ?? false);

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $invitation->setToken($token);

        // Check if email belongs to an existing user
        $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            $invitation->setAcceptedBy($existingUser);
        }

        $this->entityManager->persist($invitation);
        $this->entityManager->flush();

        return $invitation;
    }

    /**
     * Send invitation email
     */
    public function sendInvitation(Invitation $invitation): void
    {
        $invitationUrl = $this->urlGenerator->generate('public_invitation_accept', [
            'token' => $invitation->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address('noreply@tarmac.com', 'Tarmac'))
            ->to($invitation->getEmail())
            ->subject('Invitation à rejoindre ' . $invitation->getClub()->getName())
            ->htmlTemplate('email/invitation.html.twig')
            ->context([
                'invitation' => $invitation,
                'invitationUrl' => $invitationUrl,
            ])
        ;

        $this->mailer->send($email);
    }

    /**
     * Resend an invitation with a new token and expiry date
     */
    public function resendInvitation(Invitation $invitation): void
    {
        // Generate new token and extend expiry
        $token = bin2hex(random_bytes(32));
        $invitation->setToken($token);
        $invitation->setExpiresAt(new \DateTimeImmutable('+1 week'));

        // Re-check if email belongs to an existing user (in case they registered since)
        $existingUser = $this->userRepository->findOneBy(['email' => $invitation->getEmail()]);
        if ($existingUser) {
            $invitation->setAcceptedBy($existingUser);
        }

        $this->entityManager->flush();

        // Send the email
        $this->sendInvitation($invitation);
    }

    /**
     * Accept an invitation and create membership
     */
    public function acceptInvitation(User $user, Invitation $invitation): Membership
    {
        // Validate invitation is not expired
        if ($invitation->isExpired()) {
            throw new \RuntimeException('Cette invitation a expiré.');
        }

        // Validate invitation is for this user if locked
        if ($invitation->isLocked() && $invitation->getAcceptedBy() !== $user) {
            throw new \RuntimeException('Cette invitation n\'est pas pour vous.');
        }

        // Check if membership already exists
        $existingMembership = $this->entityManager->getRepository(Membership::class)
            ->findByUserAndClub($user, $invitation->getClub());

        if ($existingMembership) {
            // Delete invitation and return existing membership
            $this->entityManager->remove($invitation);
            $this->entityManager->flush();
            return $existingMembership;
        }

        // Create new membership
        $membership = new Membership();
        $membership->setUser($user);
        $membership->setClub($invitation->getClub());
        $membership->setIsManager($invitation->isManager());
        $membership->setIsInspector($invitation->isInspector());

        $this->entityManager->persist($membership);

        // Delete the invitation
        $this->entityManager->remove($invitation);

        $this->entityManager->flush();

        return $membership;
    }

    /**
     * Cancel/delete an invitation
     */
    public function cancelInvitation(Invitation $invitation): void
    {
        $this->entityManager->remove($invitation);
        $this->entityManager->flush();
    }

    /**
     * Auto-accept all pending invitations for a user by email
     */
    public function autoAcceptInvitationsForUser(User $user): array
    {
        $invitations = $this->invitationRepository->findPendingByEmail($user->getEmail());
        $acceptedClubs = [];

        foreach ($invitations as $invitation) {
            try {
                $this->acceptInvitation($user, $invitation);
                $acceptedClubs[] = $invitation->getClub();
            } catch (\Exception $e) {
                // Log error but continue with other invitations
                // Could use a logger here
            }
        }

        return $acceptedClubs;
    }
}

