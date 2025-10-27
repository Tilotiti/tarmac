<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Enum\ActivityType;
use App\Entity\Task;
use App\Entity\Activity;
use App\Form\Filter\TaskFilterType;
use App\Form\ActivityFormType;
use App\Form\TaskType;
use App\Repository\Paginator;
use App\Repository\TaskRepository;
use App\Security\Voter\TaskVoter;
use App\Service\ClubResolver;
use App\Service\Maintenance\TaskStatusService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
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
        $filters = $this->createFilter(TaskFilterType::class, ['status' => 'open']);
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

        $form = $this->createForm(TaskType::class, $task, [
            'include_subtasks' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
            'commentForm' => $commentForm,
            'taskStatusService' => $this->taskStatusService,
        ]);
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

        $form = $this->createForm(TaskType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

    #[Route('/{id}/do', name: 'club_task_do', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(TaskVoter::DO , 'task')]
    public function do(Task $task): Response
    {

        $this->taskStatusService->handleTaskDone($task, $this->getUser());

        $this->addFlash('success', 'taskMarkedAsDone');

        return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
    }

    #[Route('/{id}/close', name: 'club_task_close', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(TaskVoter::CLOSE, 'task')]
    public function close(Task $task): Response
    {
        if (!$this->taskStatusService->canCloseTask($task)) {
            $this->addFlash('error', 'taskCannotBeClosed');
            return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
        }

        $this->taskStatusService->handleTaskClose($task, $this->getUser());

        $this->addFlash('success', 'taskClosed');

        return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
    }

    #[Route('/{id}/inspect/approve', name: 'club_task_inspect_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(TaskVoter::INSPECT, 'task')]
    public function inspectApprove(Task $task): Response
    {

        $this->taskStatusService->handleTaskInspectApprove($task, $this->getUser());

        $this->addFlash('success', 'inspectionApproved');

        return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
    }

    #[Route('/{id}/inspect/reject', name: 'club_task_inspect_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(TaskVoter::INSPECT, 'task')]
    public function inspectReject(Task $task, Request $request): Response
    {

        $form = $this->createForm(ActivityFormType::class, null, [
            'required' => true,
            'label' => 'rejectionReason',
            'placeholder' => 'explainReason',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->taskStatusService->handleTaskInspectReject($task, $this->getUser(), $data['message'] ?? null);

            $this->addFlash('success', 'inspectionRejected');

            return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
        }

        $this->addFlash('error', 'rejectionReasonRequired');
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

            $this->addFlash('success', 'commentAdded');
        }

        return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
    }
}

