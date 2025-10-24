<?php

namespace App\Controller\Public;

use App\Entity\User;
use App\Service\SubdomainService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/', host: 'www.%domain%')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly SubdomainService $subdomainService
    ) {
    }

    #[Route('/clubs', name: 'public_clubs')]
    public function clubs(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to access this page.');
        }

        // Only show active clubs on the dashboard
        $clubs = array_filter($user->getClubs(), fn($club) => $club->isActive());

        // Auto-redirect to single club if user has only one membership and is not an admin
        if (count($clubs) === 1 && !$user->isAdmin()) {
            $club = reset($clubs);
            $clubUrl = $this->subdomainService->generateClubUrl($club->getSubdomain(), '/');
            return new RedirectResponse($clubUrl);
        }

        return $this->render('public/clubs.html.twig', [
            'clubs' => $clubs,
            'subdomainService' => $this->subdomainService,
        ]);
    }
}

