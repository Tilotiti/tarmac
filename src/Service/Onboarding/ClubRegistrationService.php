<?php

namespace App\Service\Onboarding;

use App\Entity\Club;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ClubRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly SubdomainService $subdomainService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Crée le club, en fait le demandeur gestionnaire, prévient les admins et
     * envoie l'e-mail de bienvenue. Le rapport de doublon sert à l'e-mail admin.
     */
    public function register(User $user, Club $club, DuplicateReport $report): Club
    {
        $club->setCreatedBy($user);
        $club->setActive(true);
        $club->syncCoordinatesFromAerodrome();

        $membership = (new Membership())->setUser($user)->setClub($club)->setIsManager(true);
        $club->addMembership($membership);
        $user->addMembership($membership);

        $this->entityManager->persist($club);
        $this->entityManager->flush();

        $clubUrl = $this->subdomainService->generateClubUrl((string) $club->getSubdomain());

        $this->notifyAdmins($club, $report, $clubUrl);
        $this->welcomePresident($user, $club, $clubUrl);

        return $club;
    }

    private function notifyAdmins(Club $club, DuplicateReport $report, string $clubUrl): void
    {
        $adminUrl = $this->urlGenerator->generate('admin_club_show', ['id' => $club->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($this->userRepository->findAdmins() as $admin) {
            $this->mailer->send((new TemplatedEmail())
                ->from(new Address('contact@tarmac.center', 'Tarmac'))
                ->to((string) $admin->getEmail())
                ->subject(sprintf('Nouveau club : %s', $club->getName()))
                ->htmlTemplate('email/clubCreated.html.twig')
                ->context([
                    'club' => $club,
                    'report' => $report,
                    'clubUrl' => $clubUrl,
                    'adminUrl' => $adminUrl,
                ]));
        }
    }

    private function welcomePresident(User $user, Club $club, string $clubUrl): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->from(new Address('contact@tarmac.center', 'Tarmac'))
            ->to((string) $user->getEmail())
            ->subject(sprintf('%s est sur Tarmac', $club->getName()))
            ->htmlTemplate('email/welcomePresident.html.twig')
            ->context([
                'user' => $user,
                'club' => $club,
                'clubUrl' => $clubUrl,
                'guideUrl' => $this->urlGenerator->generate('public_guide', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }
}
