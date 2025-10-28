<?php

namespace App\Controller\Club;

use App\Service\ClubResolver;
use App\Entity\Enum\TaskStatus;
use App\Service\SubdomainService;
use App\Repository\TaskRepository;
use App\Repository\SubTaskRepository;
use App\Entity\Enum\EquipmentOwner;
use App\Controller\ExtendedController;
use App\Repository\PlanApplicationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www).*'])]
#[IsGranted('ROLE_USER')]
#[IsGranted('VIEW')]
class DashboardController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly TaskRepository $taskRepository,
        private readonly SubTaskRepository $subTaskRepository,
        private readonly PlanApplicationRepository $applicationRepository,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_dashboard')]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        $isManager = $this->isGranted('MANAGE');
        $isInspector = $this->isGranted('INSPECT');
        $isPilote = $this->isGranted('PILOT');

        // === 1. SUBTASKS AWAITING INSPECTION (for inspectors only) ===
        $awaitingInspectionSubTasks = [];
        $awaitingInspectionCount = 0;
        if ($isInspector) {
            $inspectionQb = $this->subTaskRepository->createQueryBuilder('subtask')
                ->join('subtask.task', 'task')
                ->join('task.equipment', 'equipment')
                ->where('task.club = :club')
                ->setParameter('club', $club)
                ->andWhere('subtask.requiresInspection = :true')
                ->setParameter('true', true)
                ->andWhere('subtask.status = :done')
                ->setParameter('done', 'done')
                ->andWhere('subtask.doneBy IS NOT NULL')
                ->andWhere('subtask.inspectedBy IS NULL');

            // Inspectors can see subtasks on private equipment (exception to privacy rule)
            // No additional privacy filter needed for inspection subtasks

            // Get count
            $countQb = clone $inspectionQb;
            $awaitingInspectionCount = (int) $countQb->select('COUNT(DISTINCT subtask.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Get limited results - order by task dueAt with NULL values last
            $inspectionQb->addOrderBy('CASE WHEN task.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('task.dueAt', 'ASC')
                ->setMaxResults(5);
            $awaitingInspectionSubTasks = $inspectionQb->getQuery()->getResult();
        }

        // === 2. PENDING PLAN APPLICATIONS ===
        // Get applications with tasks that still need work (open status, including those waiting for inspection)
        $applicationQb = $this->taskRepository->queryAll();
        $applicationQb->join('task.equipment', 'equipment_application')
            ->andWhere('task.planApplication IS NOT NULL')
            ->andWhere('task.status = :openStatus')
            ->setParameter('openStatus', TaskStatus::OPEN);

        // Apply pilot visibility filter: non-pilotes can only see facility equipment
        if (!$isPilote && !$isManager && !$isInspector) {
            $applicationQb = $this->taskRepository->filterByFacilityEquipment($applicationQb);
        }

        // Apply privacy filter for non-managers
        if (!$isManager) {
            // Show only club equipment OR private equipment owned by the user
            $applicationQb->leftJoin('equipment_application.owners', 'owners')
                ->andWhere('equipment_application.owner = :owner_club OR (equipment_application.owner = :owner_private AND owners.id = :user)')
                ->setParameter('owner_club', EquipmentOwner::CLUB)
                ->setParameter('owner_private', EquipmentOwner::PRIVATE)
                ->setParameter('user', $user->getId());
        }

        // Group by application to count unique applications
        $applicationTasksForCount = (clone $applicationQb)
            ->select('DISTINCT IDENTITY(task.planApplication)')
            ->getQuery()
            ->getResult();
        $pendingApplicationsCount = count($applicationTasksForCount);

        // Get tasks for display (limit by application) - order by dueAt with NULL values last
        $applicationTasks = (clone $applicationQb)
            ->addOrderBy('CASE WHEN task.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('task.dueAt', 'ASC')
            ->setMaxResults(50) // Get more to ensure we have tasks from at least 5 applications
            ->getQuery()
            ->getResult();

        // Group by application and limit to 5 applications
        $pendingApplications = [];
        foreach ($applicationTasks as $task) {
            $application = $task->getPlanApplication();
            if ($application && count($pendingApplications) < 5) {
                if (!isset($pendingApplications[$application->getId()])) {
                    $pendingApplications[$application->getId()] = [
                        'application' => $application,
                        'tasks' => [],
                    ];
                }
                $pendingApplications[$application->getId()]['tasks'][] = $task;
            }
        }

        // === 3. STANDALONE OPEN TASKS ===
        // Only show tasks that are open or done (exclude closed/cancelled)
        $standaloneQb = $this->taskRepository->queryAll();
        $standaloneQb->join('task.equipment', 'equipment_standalone')
            ->andWhere('task.planApplication IS NULL')
            ->andWhere('task.status IN (:standaloneStatuses)')
            ->setParameter('standaloneStatuses', ['open', 'done']);

        // Apply pilot visibility filter: non-pilotes can only see facility equipment
        if (!$isPilote && !$isManager && !$isInspector) {
            $standaloneQb = $this->taskRepository->filterByFacilityEquipment($standaloneQb);
        }

        // Apply privacy filter for non-managers
        if (!$isManager) {
            // Show only club equipment OR private equipment owned by the user
            $standaloneQb->leftJoin('equipment_standalone.owners', 'owners')
                ->andWhere('equipment_standalone.owner = :clubStandalone OR (equipment_standalone.owner = :privateStandalone AND owners.id = :userIdStandalone)')
                ->setParameter('clubStandalone', EquipmentOwner::CLUB)
                ->setParameter('privateStandalone', EquipmentOwner::PRIVATE)
                ->setParameter('userIdStandalone', $user->getId());
        }

        // Get count
        $standaloneCountQb = clone $standaloneQb;
        $standaloneTasksCount = (int) $standaloneCountQb->select('COUNT(DISTINCT task.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get limited results - order by dueAt with NULL values last
        $standaloneQb->addOrderBy('CASE WHEN task.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('task.dueAt', 'ASC')
            ->setMaxResults(5);
        $standaloneTasks = $standaloneQb->getQuery()->getResult();

        return $this->render('club/dashboard.html.twig', [
            'club' => $club,
            'pendingApplications' => $pendingApplications,
            'pendingApplicationsCount' => $pendingApplicationsCount,
            'standaloneTasks' => $standaloneTasks,
            'standaloneTasksCount' => $standaloneTasksCount,
            'awaitingInspectionSubTasks' => $awaitingInspectionSubTasks,
            'awaitingInspectionCount' => $awaitingInspectionCount,
        ]);
    }
}

