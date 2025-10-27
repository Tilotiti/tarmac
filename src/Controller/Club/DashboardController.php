<?php

namespace App\Controller\Club;

use App\Service\ClubResolver;
use App\Entity\Enum\TaskStatus;
use App\Service\SubdomainService;
use App\Repository\TaskRepository;
use App\Entity\Enum\EquipmentOwner;
use App\Controller\ExtendedController;
use App\Repository\PlanApplicationRepository;
use Symfony\Component\HttpFoundation\Request;
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
        private readonly ClubResolver $clubResolver,
        private readonly TaskRepository $taskRepository,
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

        // === 1. TASKS AWAITING INSPECTION (for inspectors only) ===
        $awaitingInspection = [];
        $awaitingInspectionCount = 0;
        if ($isInspector) {
            $inspectionQb = $this->taskRepository->queryAll();
            $inspectionQb->join('task.equipment', 'eqInsp')
                ->andWhere('task.requiresInspection = :true')
                ->setParameter('true', true)
                ->andWhere('task.status = :done')
                ->setParameter('done', TaskStatus::DONE)
                ->andWhere('task.doneAt IS NOT NULL')
                ->andWhere('task.inspectedAt IS NULL');

            // Inspectors can see tasks on private equipment (exception to privacy rule)
            // No additional privacy filter needed for inspection tasks

            // Get count
            $countQb = clone $inspectionQb;
            $awaitingInspectionCount = (int) $countQb->select('COUNT(DISTINCT task.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Get limited results - order by dueAt with NULL values last
            $inspectionQb->addOrderBy('CASE WHEN task.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('task.dueAt', 'ASC')
                ->setMaxResults(5);
            $awaitingInspection = $inspectionQb->getQuery()->getResult();
        }

        // === 2. PENDING PLAN APPLICATIONS ===
        // Get applications with open tasks
        $applicationQb = $this->taskRepository->queryAll();
        $applicationQb->join('task.equipment', 'eqApp')
            ->andWhere('task.planApplication IS NOT NULL')
            ->andWhere('task.status != :cancelled')
            ->setParameter('cancelled', TaskStatus::CANCELLED)
            ->andWhere('task.status != :closed')
            ->setParameter('closed', TaskStatus::CLOSED);

        // Apply privacy filter for non-managers
        if (!$isManager) {
            // Show only club equipment OR private equipment owned by the user
            $applicationQb->leftJoin('eqApp.owners', 'appOwners')
                ->andWhere('eqApp.owner = :owner_club OR (eqApp.owner = :owner_private AND appOwners.id = :user)')
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
        $standaloneQb = $this->taskRepository->queryAll();
        $standaloneQb->join('task.equipment', 'eqStandalone')
            ->andWhere('task.planApplication IS NULL')
            ->andWhere('task.status != :cancelled')
            ->setParameter('cancelled', TaskStatus::CANCELLED)
            ->andWhere('task.status != :closed')
            ->setParameter('closed', TaskStatus::CLOSED);

        // Apply privacy filter for non-managers
        if (!$isManager) {
            // Show only club equipment OR private equipment owned by the user
            $standaloneQb->leftJoin('eqStandalone.owners', 'standaloneOwners')
                ->andWhere('eqStandalone.owner = :clubStandalone OR (eqStandalone.owner = :privateStandalone AND standaloneOwners.id = :userIdStandalone)')
                ->setParameter('clubStandalone', \App\Entity\Enum\EquipmentOwner::CLUB)
                ->setParameter('privateStandalone', \App\Entity\Enum\EquipmentOwner::PRIVATE)
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
            'awaitingInspection' => $awaitingInspection,
            'awaitingInspectionCount' => $awaitingInspectionCount,
        ]);
    }
}

