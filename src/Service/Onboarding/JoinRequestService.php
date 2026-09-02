<?php

namespace App\Service\Onboarding;

use App\Entity\Club;
use App\Entity\JoinRequest;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\JoinRequestRepository;
use App\Repository\UserRepository;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class JoinRequestService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JoinRequestRepository $joinRequestRepository,
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly SubdomainService $subdomainService,
    ) {
    }

    /**
     * Enregistre la demande (une seule ouverte par utilisateur et club) et prévient
     * les gestionnaires ; sans gestionnaire, les admins reçoivent le message.
     */
    public function submit(User $user, Club $club, ?string $message): JoinRequest
    {
        $existing = $this->joinRequestRepository->findOpenByUserAndClub($user, $club);
        if ($existing !== null) {
            return $existing;
        }

        $joinRequest = (new JoinRequest())->setUser($user)->setClub($club)->setMessage($message !== null && trim($message) !== '' ? trim($message) : null);
        $this->entityManager->persist($joinRequest);
        $this->entityManager->flush();

        $managers = $club->getMemberships()
            ->filter(static fn (Membership $membership): bool => $membership->isManager())
            ->map(static fn (Membership $membership): User => $membership->getUser())
            ->toArray();
        $recipients = $managers !== [] ? $managers : $this->userRepository->findAdmins();

        // Les gestionnaires acceptent (avec les rôles) ou ignorent depuis la page Invitations
        $requestsUrl = $this->subdomainService->generateClubUrl((string) $club->getSubdomain(), '/invitations');

        foreach ($recipients as $recipient) {
            $this->mailer->send((new TemplatedEmail())
                ->from(new Address('contact@tarmac.center', 'Tarmac'))
                ->to((string) $recipient->getEmail())
                ->subject(sprintf('%s souhaite rejoindre %s', $user->getFullName(), $club->getName()))
                ->htmlTemplate('email/joinRequest.html.twig')
                ->context(['joinRequest' => $joinRequest, 'requestsUrl' => $requestsUrl]));
        }

        return $joinRequest;
    }

    /**
     * Le demandeur est connu et son e-mail vérifié : on crée l'adhésion directement,
     * avec les rôles choisis par le gestionnaire, puis on le prévient.
     */
    public function accept(JoinRequest $joinRequest, User $handledBy, bool $isManager, bool $isInspector, bool $isPilote): Membership
    {
        $club = $joinRequest->getClub();
        $user = $joinRequest->getUser();

        $membership = $club->getMembership($user);
        if ($membership === null) {
            $membership = (new Membership())->setUser($user)->setClub($club);
            $club->addMembership($membership);
            $user->addMembership($membership);
            $this->entityManager->persist($membership);
        }
        $membership->setIsManager($isManager)->setIsInspector($isInspector)->setIsPilote($isPilote);

        $joinRequest->markHandled($handledBy);
        $this->entityManager->flush();

        $this->mailer->send((new TemplatedEmail())
            ->from(new Address('contact@tarmac.center', 'Tarmac'))
            ->to((string) $user->getEmail())
            ->subject(sprintf('Bienvenue à %s', $club->getName()))
            ->htmlTemplate('email/joinRequestAccepted.html.twig')
            ->context([
                'user' => $user,
                'club' => $club,
                'membership' => $membership,
                'clubUrl' => $this->subdomainService->generateClubUrl((string) $club->getSubdomain()),
            ]));

        return $membership;
    }
}
