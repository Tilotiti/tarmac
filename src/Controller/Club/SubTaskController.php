<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Enum\ActivityType;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Entity\Activity;
use App\Form\SubTaskType;
use App\Form\ActivityFormType;
use App\Security\Voter\SubTaskVoter;
use App\Security\Voter\TaskVoter;
use App\Service\ClubResolver;
use App\Service\Maintenance\TaskStatusService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use SlopeIt\BreadcrumbBundle\Service\BreadcrumbBuilder;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tasks/{taskId}/subtasks', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*', 'taskId' => '\d+'])]
#[IsGranted('ROLE_USER')]
class SubTaskController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly TaskStatusService $taskStatusService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('/new', name: 'club_subtask_new', methods: ['GET', 'POST'])]
    #[IsGranted(TaskVoter::CREATE_SUBTASK, 'task')]
    public function new(
        #[MapEntity(id: 'taskId')] Task $task,
        Request $request,
        BreadcrumbBuilder $breadcrumbBuilder
    ): Response {

        $club = $this->clubResolver->resolve();

        // Configure breadcrumb programmatically
        $breadcrumbBuilder
            ->addItem('home', 'club_dashboard')
            ->addItem('tasks', 'club_tasks')
            ->addItem($task->getTitle(), 'club_task_show', ['id' => $task->getId()])
            ->addItem('newSubTask');

        $subTask = new SubTask();
        $subTask->setTask($task);

        // Auto-set position
        $maxPosition = 0;
        foreach ($task->getSubTasks() as $existingSubTask) {
            $maxPosition = max($maxPosition, $existingSubTask->getPosition());
        }
        $subTask->setPosition($maxPosition + 1);

        $form = $this->createForm(SubTaskType::class, $subTask);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($subTask);
            $this->entityManager->flush();

            // Log subtask creation activity
            $activity = new Activity();
            $activity->setTask($task);
            $activity->setSubTask($subTask);
            $activity->setType(ActivityType::CREATED);
            $activity->setUser($this->getUser());
            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'subTaskCreated');

            return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
        }

        return $this->render('club/task/subtask/new.html.twig', [
            'club' => $club,
            'task' => $task,
            'subTask' => $subTask,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'club_subtask_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::EDIT, 'subTask')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'tasks', 'route' => 'club_tasks'],
        ['label' => '$subTask.task.title', 'route' => 'club_task_show', 'routeParameters' => ['id' => '$subTask.task.id']],
        ['label' => 'editSubTask'],
    ])]
    public function edit(SubTask $subTask, Request $request): Response
    {

        $club = $this->clubResolver->resolve();
        $task = $subTask->getTask();

        $form = $this->createForm(SubTaskType::class, $subTask);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            // Log subtask edit activity
            $activity = new Activity();
            $activity->setTask($task);
            $activity->setSubTask($subTask);
            $activity->setType(ActivityType::EDITED);
            $activity->setUser($this->getUser());
            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'subTaskUpdated');

            return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
        }

        return $this->render('club/task/subtask/edit.html.twig', [
            'club' => $club,
            'task' => $task,
            'subTask' => $subTask,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/do', name: 'club_subtask_do', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::DO , 'subTask')]
    public function do(SubTask $subTask): Response
    {

        $this->taskStatusService->handleSubTaskDone($subTask, $this->getUser());

        $this->addFlash('success', 'subTaskMarkedAsDone');

        return $this->redirectToRoute('club_task_show', ['id' => $subTask->getTask()->getId()]);
    }

    #[Route('/{id}/cancel', name: 'club_subtask_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::CANCEL, 'subTask')]
    public function cancel(SubTask $subTask, Request $request): Response
    {

        $form = $this->createForm(ActivityFormType::class, null, [
            'required' => false,
            'label' => 'cancellationReason',
            'placeholder' => 'optionalReason',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->taskStatusService->handleCancelSubTask($subTask, $this->getUser(), $data['message'] ?? null);

            $this->addFlash('success', 'subTaskCancelled');

            return $this->redirectToRoute('club_task_show', ['id' => $subTask->getTask()->getId()]);
        }

        $this->addFlash('error', 'invalidRequest');
        return $this->redirectToRoute('club_task_show', ['id' => $subTask->getTask()->getId()]);
    }

    #[Route('/{id}/comment', name: 'club_subtask_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::COMMENT, 'subTask')]
    public function addComment(SubTask $subTask, Request $request): Response
    {

        $form = $this->createForm(ActivityFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $activity = new Activity();
            $activity->setTask($subTask->getTask());
            $activity->setSubTask($subTask);
            $activity->setType(ActivityType::COMMENT);
            $activity->setUser($this->getUser());
            $activity->setMessage($data['message']);

            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'commentAdded');
        }

        return $this->redirectToRoute('club_task_show', ['id' => $subTask->getTask()->getId()]);
    }
}

