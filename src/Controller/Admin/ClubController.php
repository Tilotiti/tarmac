<?php

namespace App\Controller\Admin;

use App\Controller\ExtendedController;
use App\Entity\Club;
use App\Entity\Membership;
use App\Form\ClubType;
use App\Form\Filter\ClubFilterType;
use App\Repository\ClubRepository;
use App\Repository\Paginator;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/clubs', host: 'www.%domain%')]
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

    #[Route('', name: 'admin_club_index')]
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

        return $this->render('admin/club/index.html.twig', [
            'clubs' => $clubs,
            'domain' => $this->subdomainService->getDomain(),
            'filterForm' => $filterForm,
        ]);
    }

    #[Route('/new', name: 'admin_club_new')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'admin_dashboard'],
        ['label' => 'Clubs', 'route' => 'admin_club_index'],
        ['label' => 'Ajouter'],
    ])]
    public function new(Request $request): Response
    {
        $club = new Club();
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($club);
            $this->entityManager->flush();

            $this->addFlash('success', 'clubCreated');

            return $this->redirectToRoute('admin_club_index');
        }

        return $this->render('admin/club/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_club_show')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'admin_dashboard'],
        ['label' => 'Clubs', 'route' => 'admin_club_index'],
        ['label' => '$club.name'],
    ])]
    public function show(Club $club): Response
    {
        return $this->render('admin/club/show.html.twig', [
            'club' => $club,
            'domain' => $this->subdomainService->getDomain(),
            'subdomain_service' => $this->subdomainService,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_club_edit')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'admin_dashboard'],
        ['label' => 'Clubs', 'route' => 'admin_club_index'],
        ['label' => '$club.name', 'route' => 'admin_club_show', 'parameters' => ['id' => '$club.id']],
        ['label' => 'Modifier'],
    ])]
    public function edit(Request $request, Club $club): Response
    {
        $form = $this->createForm(ClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'clubUpdated');

            return $this->redirectToRoute('admin_club_index');
        }

        return $this->render('admin/club/edit.html.twig', [
            'form' => $form,
            'club' => $club,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_club_delete', methods: ['POST', 'GET'])]
    public function delete(Club $club): Response
    {
        $club->setActive(false);
        $this->entityManager->flush();

        $this->addFlash('success', 'clubDisabled');

        return $this->redirectToRoute('admin_club_index');
    }

    #[Route('/{id}/activate', name: 'admin_club_activate', methods: ['POST', 'GET'])]
    public function activate(Club $club): Response
    {
        $club->setActive(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'clubEnabled');

        return $this->redirectToRoute('admin_club_show', ['id' => $club->getId()]);
    }

}

