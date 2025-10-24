<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Club;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('ROLE_USER')]
#[IsGranted('VIEW')]
class DashboardController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_dashboard')]
    public function index(): Response
    {
        $club = $this->clubResolver->resolve();

        return $this->render('club/dashboard.html.twig', [
            'club' => $club,
        ]);
    }
}

