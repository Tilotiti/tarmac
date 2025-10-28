<?php

namespace App\Controller\Club;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Form\EquipmentType;
use App\Form\PlanApplyType;
use App\Repository\PlanApplicationRepository;
use App\Repository\PlanRepository;
use App\Repository\Paginator;
use App\Repository\TaskRepository;
use App\Service\ClubResolver;
use App\Entity\Enum\EquipmentOwner;
use App\Service\Maintenance\PlanApplier;
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
        private readonly PlanRepository $planRepository,
        private readonly PlanApplicationRepository $applicationRepository,
        private readonly TaskRepository $taskRepository,
        private readonly PlanApplier $planApplier,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_equipments')]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // Handle filters - default to club equipment
        $filters = $this->createFilter(EquipmentFilterType::class, [
            'owner' => EquipmentOwner::CLUB->value
        ]);
        $filters->handleRequest($request);

        $params = $filters->getData() ?? [];

        // Force club context
        $params['club'] = $club;

        // Members can only see active equipments, managers can see all
        if (!$this->isGranted('MANAGE')) {
            $params['active'] = true;
        }

        // Get equipments with pagination
        $equipments = Paginator::paginate(
            $this->equipmentRepository->queryByFilters($params),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/equipment/index.html.twig', [
            'club' => $club,
            'equipments' => $equipments,
            'filters' => $filters->createView(),
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
        $user = $this->getUser();

        // Ensure equipment belongs to this club
        if ($equipment->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        // Get maintenance plan applications for this equipment
        $applications = $this->planApplier->getEquipmentApplications($equipment);

        // Get pending tasks for this equipment
        $qb = $this->taskRepository->queryAll();
        $qb = $this->taskRepository->filterByEquipment($qb, $equipment);
        $qb = $this->taskRepository->filterByStatus($qb, 'open');
        
        // Apply pilot visibility filter: non-pilotes cannot see glider tasks
        $isManager = $this->isGranted('MANAGE');
        $isInspector = $this->isGranted('INSPECT');
        $isPilote = $this->isGranted('PILOT');
        
        if (!$isPilote && !$isManager && !$isInspector) {
            $qb = $this->taskRepository->filterByFacilityEquipment($qb);
        }
        
        $qb = $this->taskRepository->orderByRelevantDate($qb, 'ASC');
        $pendingTasks = $qb->setMaxResults(10)->getQuery()->getResult();

        return $this->render('club/equipment/show.html.twig', [
            'club' => $club,
            'equipment' => $equipment,
            'applications' => $applications,
            'pendingTasks' => $pendingTasks,
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

    #[Route('/{id}/apply-plan', name: 'club_equipment_apply_plan', methods: ['GET', 'POST'])]
    #[IsGranted('MANAGE')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'Équipements', 'route' => 'club_equipments'],
        ['label' => '$equipment.name', 'route' => 'club_equipment_show', 'parameters' => ['id' => '$equipment.id']],
        ['label' => 'applyPlan'],
    ])]
    public function applyPlan(Equipment $equipment, Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure equipment belongs to this club
        if ($equipment->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        // Get available maintenance plans for this equipment type
        $qb = $this->planRepository->queryAll();
        $qb = $this->planRepository->filterByEquipmentType($qb, $equipment->getType());
        $availablePlans = $qb->getQuery()->getResult();

        if (empty($availablePlans)) {
            $this->addFlash('warning', 'noPlansAvailable');
            return $this->redirectToRoute('club_equipment_show', ['id' => $equipment->getId()]);
        }

        $form = $this->createForm(PlanApplyType::class, [
            'equipment' => $equipment,
        ]);

        // Add plan selection
        $form->add('plan', \Symfony\Bridge\Doctrine\Form\Type\EntityType::class, [
            'class' => \App\Entity\Plan::class,
            'choices' => $availablePlans,
            'choice_label' => 'name',
            'label' => 'plan',
            'required' => true,
            'attr' => ['class' => 'form-select'],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $plan = $data['plan'];
            $dueAt = $data['dueAt'];

            try {
                $application = $this->planApplier->applyPlan(
                    $plan,
                    $equipment,
                    $this->getUser(),
                    $dueAt
                );

                $this->addFlash('success', 'planApplied');

                return $this->redirectToRoute('club_equipment_show', ['id' => $equipment->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('club/equipment/apply_plan.html.twig', [
            'club' => $club,
            'equipment' => $equipment,
            'availablePlans' => $availablePlans,
            'form' => $form,
        ]);
    }
}

