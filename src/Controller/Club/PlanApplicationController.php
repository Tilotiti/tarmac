<?php

namespace App\Controller\Club;

use App\Entity\PlanApplication;
use App\Form\ActivityFormType;
use App\Repository\Paginator;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use App\Repository\TaskRepository;
use App\Controller\ExtendedController;
use App\Service\Maintenance\TaskStatusService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PlanApplicationRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Form\Filter\PlanApplicationFilterType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/plans/applications', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('ROLE_USER')]
class PlanApplicationController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly PlanApplicationRepository $applicationRepository,
        private readonly TaskRepository $taskRepository,
        private readonly TaskStatusService $taskStatusService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_plan_applications')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'plans', 'route' => 'club_plans'],
        ['label' => 'applications'],
    ])]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // Handle filters
        $filterForm = $this->createFilter(PlanApplicationFilterType::class, null, [
            'club' => $club,
        ]);
        $filterForm->handleRequest($request);

        // Build query with filters
        $qb = $this->applicationRepository->queryAll();
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $filters = $filterForm->getData();
            if (!empty($filters['plan'])) {
                $qb = $this->applicationRepository->filterByPlan($qb, $filters['plan']);
            }
            if (!empty($filters['equipment'])) {
                $qb = $this->applicationRepository->filterByEquipment($qb, $filters['equipment']);
            }
            if (!empty($filters['equipmentType'])) {
                $qb = $this->applicationRepository->filterByEquipmentType($qb, $filters['equipmentType']);
            }
            if (isset($filters['cancelled']) && $filters['cancelled'] !== '') {
                $qb = $this->applicationRepository->filterByCancelled($qb, $filters['cancelled'] === '1');
            }
            if (!empty($filters['dueDate']) && $filters['dueDate'] !== 'all') {
                $qb = $this->applicationRepository->filterByDueDate($qb, $filters['dueDate']);
            }
        }

        $qb = $this->applicationRepository->orderByDueDate($qb, 'ASC');

        $applications = Paginator::paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/plan/application/index.html.twig', [
            'club' => $club,
            'applications' => $applications,
            'filterForm' => $filterForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'club_plan_application_show', requirements: ['id' => '\d+'])]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'plans', 'route' => 'club_plans'],
        ['label' => 'applications', 'route' => 'club_plan_applications'],
        ['label' => '$application.plan.name'],
    ])]
    public function show(PlanApplication $application): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure application belongs to this club
        if ($application->getEquipment()->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        // Get tasks for this application
        $qb = $this->taskRepository->queryAll();
        $qb = $this->taskRepository->filterByPlanApplication($qb, $application);
        $qb = $this->taskRepository->orderByRelevantDate($qb, 'ASC');
        
        $tasks = $qb->getQuery()->getResult();

        return $this->render('club/plan/application/show.html.twig', [
            'club' => $club,
            'application' => $application,
            'tasks' => $tasks,
        ]);
    }

    #[Route('/{id}/cancel', name: 'club_plan_application_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('MANAGE')]
    public function cancel(PlanApplication $application, Request $request): Response
    {
        $form = $this->createForm(ActivityFormType::class, null, [
            'required' => false,
            'label' => 'cancellationReason',
            'placeholder' => 'optionalReason',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->taskStatusService->handleCancelApplication($application, $this->getUser(), $data['message'] ?? null);
            $this->addFlash('success', 'planApplicationCancelled');
        }

        return $this->redirectToRoute('club_plan_applications');
    }
}
