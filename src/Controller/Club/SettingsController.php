<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Form\ClubAerodromeType;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reglages', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('MANAGE')]
class SettingsController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('/aerodrome', name: 'club_settings_aerodrome', methods: ['GET', 'POST'])]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'aerodrome'],
    ])]
    public function aerodrome(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $form = $this->createForm(ClubAerodromeType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $club->syncCoordinatesFromAerodrome();
            $this->entityManager->flush();
            $this->addFlash('success', 'aerodromeSaved');

            return $this->redirectToRoute('club_dashboard');
        }

        return $this->render('club/settings/aerodrome.html.twig', ['club' => $club, 'form' => $form]);
    }
}
