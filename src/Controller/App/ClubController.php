<?php

namespace App\Controller\App;

use App\Controller\ExtendedController;
use App\Entity\Club;
use App\Entity\Membership;
use App\Form\ClubType;
use App\Form\Filter\ClubFilterType;
use App\Repository\ClubRepository;
use App\Repository\Paginator;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clubs', host: 'www.%domain%')]
#[IsGranted('ROLE_ADMIN')]
class ClubController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubRepository $clubRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'app_club_index')]
    public function index(Request $request): Response
    {
        $filterForm = $this->createForm(ClubFilterType::class);
        $filterForm->handleRequest($request);

        $filters = [];
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $filters = $filterForm->getData();
            // Remove empty values
            $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        }

        $clubs = Paginator::paginate(
            $this->clubRepository->queryByFilters($filters),
            $request->query->getInt('page', 1),
            12
        );

        return $this->render('app/club/index.html.twig', [
            'clubs' => $clubs,
            'domain' => $this->subdomainService->getDomain(),
            'filterForm' => $filterForm,
        ]);
    }

    #[Route('/new', name: 'app_club_new')]
    public function new(Request $request): Response
    {
        $club = new Club();
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($club);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le club a été créé avec succès.');

            return $this->redirectToRoute('app_club_index');
        }

        return $this->render('app/club/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_club_show')]
    public function show(Club $club): Response
    {
        return $this->render('app/club/show.html.twig', [
            'club' => $club,
            'domain' => $this->subdomainService->getDomain(),
            'subdomain_service' => $this->subdomainService,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_club_edit')]
    public function edit(Request $request, Club $club): Response
    {
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Le club a été mis à jour.');

            return $this->redirectToRoute('app_club_index');
        }

        return $this->render('app/club/edit.html.twig', [
            'form' => $form,
            'club' => $club,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_club_delete', methods: ['POST', 'GET'])]
    public function delete(Club $club): Response
    {
        $club->setActive(false);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le club a été désactivé.');

        return $this->redirectToRoute('app_club_index');
    }

    #[Route('/{id}/activate', name: 'app_club_activate', methods: ['POST', 'GET'])]
    public function activate(Club $club): Response
    {
        $club->setActive(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le club a été réactivé.');

        return $this->redirectToRoute('app_club_show', ['id' => $club->getId()]);
    }

}

