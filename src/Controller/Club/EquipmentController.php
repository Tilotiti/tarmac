<?php

namespace App\Controller\Club;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Form\EquipmentType;
use App\Repository\Paginator;
use App\Service\ClubResolver;
use App\Entity\EquipmentOwner;
use App\Service\SubdomainService;
use App\Controller\ExtendedController;
use App\Repository\EquipmentRepository;
use App\Form\Filter\EquipmentFilterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/equipments', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('VIEW')]
class EquipmentController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly EquipmentRepository $equipmentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_equipments')]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // Handle filters - default to club equipment
        $filterForm = $this->createFilter(EquipmentFilterType::class, [
            'owner' => EquipmentOwner::CLUB->value
        ]);
        $filterForm->handleRequest($request);

        $filters = $this->getFilterData($filterForm);

        // Force club context
        $filters['club'] = $club;

        // Members can only see active equipments, managers can see all
        if (!$this->isGranted('MANAGE')) {
            $filters['active'] = true;
        }

        // Get equipments with pagination
        $equipments = Paginator::paginate(
            $this->equipmentRepository->queryByFilters($filters),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/equipment/index.html.twig', [
            'club' => $club,
            'equipments' => $equipments,
            'filterForm' => $filterForm,
        ]);
    }

    #[Route('/new', name: 'club_equipment_new')]
    #[IsGranted('MANAGE')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'Équipements', 'route' => 'club_equipments'],
        ['label' => 'Ajouter'],
    ])]
    public function new(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $equipment = new Equipment();
        $equipment->setClub($club);

        $form = $this->createForm(EquipmentType::class, $equipment, [
            'club' => $club,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $equipment->setCreatedBy($this->getUser());
            $this->entityManager->persist($equipment);
            $this->entityManager->flush();

            $this->addFlash('success', 'equipmentCreated');

            return $this->redirectToRoute('club_equipments');
        }

        return $this->render('club/equipment/new.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'club_equipment_show')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'Équipements', 'route' => 'club_equipments'],
        ['label' => '$equipment.name'],
    ])]
    public function show(Equipment $equipment): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure equipment belongs to this club
        if ($equipment->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        return $this->render('club/equipment/show.html.twig', [
            'club' => $club,
            'equipment' => $equipment,
        ]);
    }

    #[Route('/{id}/edit', name: 'club_equipment_edit')]
    #[IsGranted('MANAGE')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'Équipements', 'route' => 'club_equipments'],
        ['label' => '$equipment.name', 'route' => 'club_equipment_show', 'parameters' => ['id' => '$equipment.id']],
        ['label' => 'Modifier'],
    ])]
    public function edit(Request $request, Equipment $equipment): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure equipment belongs to this club
        if ($equipment->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(EquipmentType::class, $equipment, [
            'club' => $club,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'equipmentUpdated');

            return $this->redirectToRoute('club_equipments');
        }

        return $this->render('club/equipment/edit.html.twig', [
            'club' => $club,
            'equipment' => $equipment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/disable', name: 'club_equipment_disable', methods: ['POST'])]
    #[IsGranted('MANAGE')]
    public function disable(Equipment $equipment): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure equipment belongs to this club
        if ($equipment->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $equipment->setActive(false);
        $this->entityManager->flush();

        $this->addFlash('success', 'equipmentDisabled');

        return $this->redirectToRoute('club_equipments');
    }

    #[Route('/{id}/enable', name: 'club_equipment_enable', methods: ['POST'])]
    #[IsGranted('MANAGE')]
    public function enable(Equipment $equipment): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure equipment belongs to this club
        if ($equipment->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $equipment->setActive(true);
        $this->entityManager->flush();

        $this->addFlash('success', 'equipmentEnabled');

        return $this->redirectToRoute('club_equipments');
    }
}

