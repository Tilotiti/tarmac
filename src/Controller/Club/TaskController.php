<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Enum\ActivityType;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Entity\Activity;
use App\Form\Filter\SubTaskFilterType;
use App\Form\Filter\TaskFilterType;
use App\Form\ActivityFormType;
use App\Form\TaskType;
use App\Repository\Paginator;
use App\Repository\TaskRepository;
use App\Security\Voter\TaskVoter;
use App\Service\ClubResolver;
use App\Service\Maintenance\TaskStatusService;
use App\Service\SubdomainService;
use App\Service\TaskCommentNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tasks', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('ROLE_USER')]
class TaskController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly TaskRepository $taskRepository,
        private readonly TaskStatusService $taskStatusService,
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $s3Filesystem,
        private readonly TaskCommentNotificationService $commentNotificationService,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_tasks')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'tasks'],
    ])]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // Handle filters with default status 'open'
        $filters = $this->createFilter(TaskFilterType::class, ['status' => 'open'], [
            'club' => $club,
        ]);
        $filters->handleRequest($request);

        // Build query with filters
        $qb = $this->taskRepository->queryByFilters($filters->getData() ?? []);

        // Smart ordering: by dueAt for non-done tasks, by doneAt for done tasks
        $qb = $this->taskRepository->orderByRelevantDate($qb, 'ASC');

        $tasks = Paginator::paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/task/index.html.twig', [
            'club' => $club,
            'tasks' => $tasks,
            'filters' => $filters->createView(),
        ]);
    }

    #[Route('/new', name: 'club_task_new')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'tasks', 'route' => 'club_tasks'],
        ['label' => 'newTask'],
    ])]
    public function new(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $task = new Task();
        $task->setClub($club);
        $task->setCreatedBy($this->getUser());

        // Add one empty subtask by default for new tasks
        if ($task->getSubTasks()->count() === 0) {
            $subTask = new \App\Entity\SubTask();
            $subTask->setTask($task);
            $subTask->setPosition(1);
            $task->addSubTask($subTask);
        }

        $form = $this->createForm(TaskType::class, $task, [
            'include_subtasks' => true,
            'user' => $this->getUser(),
            'club' => $club,
            'can_manage_specialisations' => $this->isGranted('MANAGE', $club),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure at least one subtask
            if ($task->getSubTasks()->count() === 0) {
                $this->addFlash('error', 'atLeastOneSubTaskRequired');
                return $this->render('club/task/new.html.twig', [
                    'club' => $club,
                    'task' => $task,
                    'form' => $form,
                ]);
            }

            // Security check: non-pilots cannot create tasks for aircraft equipment
            $isPilot = $this->isGranted('PILOT');
            if (!$isPilot && $task->getEquipment()->getType()->isAircraft()) {
                $this->addFlash('error', 'pilotRequiredForAircraftTasks');
                return $this->render('club/task/new.html.twig', [
                    'club' => $club,
                    'task' => $task,
                    'form' => $form,
                ]);
            }

            $this->entityManager->persist($task);
            $this->entityManager->flush();

            // Log task creation activity
            $activity = new Activity();
            $activity->setTask($task);
            $activity->setType(ActivityType::CREATED);
            $activity->setUser($this->getUser());
            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'taskCreated');

            return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
        }

        return $this->render('club/task/new.html.twig', [
            'club' => $club,
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'club_task_show', requirements: ['id' => '\d+'])]
    #[IsGranted(TaskVoter::VIEW, 'task')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'tasks', 'route' => 'club_tasks'],
        ['label' => '$task.title'],
    ])]
    public function show(Task $task, Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // SubTask filters (status default: open)
        $subTaskFilters = $this->createFilter(SubTaskFilterType::class, ['status' => 'open']);
        $subTaskFilters->handleRequest($request);
        $filterData = $this->getFilterData($subTaskFilters);

        $filteredSubTasks = $this->filterSubTasks($task->getSubTasks(), $filterData);

        // Comment form
        $commentForm = null;
        if ($this->isGranted(TaskVoter::COMMENT, $task)) {
            $commentForm = $this->createForm(ActivityFormType::class, null, [
                'label' => 'comment',
                'placeholder' => 'addComment',
            ]);
        }

        return $this->render('club/task/show.html.twig', [
            'club' => $club,
            'task' => $task,
            'filteredSubTasks' => $filteredSubTasks,
            'filters' => $subTaskFilters->createView(),
            'commentForm' => $commentForm,
            'taskStatusService' => $this->taskStatusService,
        ]);
    }

    /**
     * Filter subtasks by status and search.
     *
     * @param iterable<SubTask> $subTasks
     *
     * @return SubTask[]
     */
    private function filterSubTasks(iterable $subTasks, array $filterData): array
    {
        $search = isset($filterData['search']) ? mb_strtolower(trim($filterData['search'])) : null;
        $status = $filterData['status'] ?? null;

        $result = [];
        foreach ($subTasks as $subTask) {
            // Status filter: open = open, done or waitingForApproval
            if ($status === 'open') {
                $statusMatch = \in_array($subTask->getStatus(), ['open', 'done'], true)
                    || $subTask->isWaitingForApproval();
            } elseif ($status === 'closed') {
                $statusMatch = $subTask->getStatus() === 'closed';
            } elseif ($status === 'cancelled') {
                $statusMatch = $subTask->getStatus() === 'cancelled';
            } else {
                $statusMatch = true;
            }

            if (!$statusMatch) {
                continue;
            }

            // Search filter (title + description)
            if ($search !== null && $search !== '') {
                $title = mb_strtolower($subTask->getTitle() ?? '');
                $description = mb_strtolower($subTask->getDescription() ?? '');
                if (!str_contains($title, $search) && !str_contains($description, $search)) {
                    continue;
                }
            }

            $result[] = $subTask;
        }

        return $result;
    }

    #[Route('/{id}/edit', name: 'club_task_edit', requirements: ['id' => '\d+'])]
    #[IsGranted(TaskVoter::EDIT, 'task')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'tasks', 'route' => 'club_tasks'],
        ['label' => '$task.title', 'route' => 'club_task_show', 'routeParameters' => ['id' => '$task.id']],
        ['label' => 'edit'],
    ])]
    public function edit(Task $task, Request $request): Response
    {

        $club = $this->clubResolver->resolve();

        $form = $this->createForm(TaskType::class, $task, [
            'user' => $this->getUser(),
            'club' => $club,
            'is_edit' => true,
            'can_manage_specialisations' => $this->isGranted('MANAGE', $club),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Security check: non-pilots cannot edit tasks to use aircraft equipment
            $isPilot = $this->isGranted('PILOT');
            if (!$isPilot && $task->getEquipment()->getType()->isAircraft()) {
                $this->addFlash('error', 'pilotRequiredForAircraftTasks');
                return $this->render('club/task/edit.html.twig', [
                    'club' => $club,
                    'task' => $task,
                    'form' => $form,
                ]);
            }

            // If task is set to priority, inherit to all subtasks
            if ($task->isPriority()) {
                foreach ($task->getSubTasks() as $subTask) {
                    $subTask->setPriority(true);
                }
            }

            $this->entityManager->flush();

            // Log task edit activity
            $activity = new Activity();
            $activity->setTask($task);
            $activity->setType(ActivityType::EDITED);
            $activity->setUser($this->getUser());
            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'taskUpdated');

            return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
        }

        return $this->render('club/task/edit.html.twig', [
            'club' => $club,
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/close', name: 'club_task_close', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(TaskVoter::CLOSE, 'task')]
    public function close(Task $task): Response
    {
        if (!$this->taskStatusService->canCloseTask($task)) {
            $this->addFlash('error', 'allSubTasksMustBeClosed');
            return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
        }

        $this->taskStatusService->handleTaskClose($task, $this->getUser());

        $this->addFlash('success', 'taskClosed');

        // If task belongs to a plan application, redirect to the plan application show page
        if ($task->getPlanApplication() !== null) {
            return $this->redirectToRoute('club_plan_application_show', ['id' => $task->getPlanApplication()->getId()]);
        }

        return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
    }

    #[Route('/{id}/cancel', name: 'club_task_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(TaskVoter::CANCEL, 'task')]
    public function cancel(Task $task, Request $request): Response
    {

        $form = $this->createForm(ActivityFormType::class, null, [
            'required' => false,
            'label' => 'cancellationReason',
            'placeholder' => 'optionalReason',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->taskStatusService->handleCancelTask($task, $this->getUser(), $data['message'] ?? null);

            $this->addFlash('success', 'taskCancelled');

            return $this->redirectToRoute('club_tasks');
        }

        $this->addFlash('error', 'invalidRequest');
        return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
    }

    #[Route('/{id}/comment', name: 'club_task_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(TaskVoter::COMMENT, 'task')]
    public function addComment(Task $task, Request $request): Response
    {

        $form = $this->createForm(ActivityFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $activity = new Activity();
            $activity->setTask($task);
            $activity->setType(ActivityType::COMMENT);
            $activity->setUser($this->getUser());
            $activity->setMessage($data['message']);

            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            // Send email notifications to users with activity on the task
            $this->commentNotificationService->sendTaskCommentNotifications($task, $activity, $this->getUser());

            $this->addFlash('success', 'commentAdded');
        }

        return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
    }

    #[Route('/{id}/toggle-priority', name: 'club_task_toggle_priority', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('MANAGE')]
    public function togglePriority(Task $task): Response
    {
        $club = $this->clubResolver->resolve();
        
        // Check user has access to this task's club
        if (!$this->getUser()->hasAccessToClub($club) || $task->getClub() !== $club) {
            $this->addFlash('error', 'accessDenied');
            return $this->redirectToRoute('club_tasks');
        }

        // Toggle priority
        $newPriority = !$task->isPriority();
        $task->setPriority($newPriority);
        
        $this->entityManager->flush();

        $this->addFlash('success', $newPriority ? 'taskMarkedAsPriority' : 'taskUnmarkedAsPriority');

        return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
    }

    #[Route('/{id}/delete-documentation', name: 'club_task_delete_documentation', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(TaskVoter::EDIT, 'task')]
    public function deleteDocumentation(Task $task, Request $request): Response
    {
        $token = $request->query->get('_token');

        if (!$token || !$this->isCsrfTokenValid('del_task_doc' . $task->getId(), $token)) {
            $this->addFlash('danger', 'invalidToken');
            return $this->redirectToRoute('club_task_edit', ['id' => $task->getId()]);
        }

        if (!$task->getDocumentation()) {
            $this->addFlash('warning', 'noDocumentationToDelete');
            return $this->redirectToRoute('club_task_edit', ['id' => $task->getId()]);
        }

        $documentationUrl = $task->getDocumentation();
        $parsedUrl = parse_url($documentationUrl);
        $filePath = $parsedUrl['path'] ?? null;

        if ($filePath) {
            $filePath = ltrim($filePath, '/');
            try {
                if ($this->s3Filesystem->fileExists($filePath)) {
                    $this->s3Filesystem->delete($filePath);
                }
            } catch (\Throwable) {
                // Ignore storage deletion errors, we'll still remove reference.
            }
        }

        $task->setDocumentation(null);
        $activity = new Activity();
        $activity->setTask($task);
        $activity->setType(ActivityType::EDITED);
        $activity->setUser($this->getUser());
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $this->addFlash('success', 'taskDocumentationDeleted');

        return $this->redirectToRoute('club_task_edit', ['id' => $task->getId()]);
    }
}

