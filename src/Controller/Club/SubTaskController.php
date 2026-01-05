<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Contribution;
use App\Entity\Enum\ActivityType;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Entity\Activity;
use App\Entity\Membership;
use App\Form\SubTaskType;
use App\Form\ActivityFormType;
use App\Form\SubTaskCompleteFormType;
use App\Repository\ContributionRepository;
use App\Repository\MembershipRepository;
use App\Security\Voter\SubTaskVoter;
use App\Security\Voter\TaskVoter;
use App\Service\ClubResolver;
use App\Service\Maintenance\TaskStatusService;
use App\Service\SubdomainService;
use App\Service\TaskCommentNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use SlopeIt\BreadcrumbBundle\Service\BreadcrumbBuilder;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/tasks/{taskId}/subtasks', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*', 'taskId' => '\d+'])]
#[IsGranted('ROLE_USER')]
class SubTaskController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly TaskStatusService $taskStatusService,
        private readonly EntityManagerInterface $entityManager,
        private readonly MembershipRepository $membershipRepository,
        private readonly ContributionRepository $contributionRepository,
        private readonly Filesystem $s3Filesystem,
        private readonly TranslatorInterface $translator,
        private readonly TaskCommentNotificationService $commentNotificationService,
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
        $subTask->setCreatedBy($this->getUser());

        // Auto-set position
        $maxPosition = 0;
        foreach ($task->getSubTasks() as $existingSubTask) {
            $maxPosition = max($maxPosition, $existingSubTask->getPosition());
        }
        $subTask->setPosition($maxPosition + 1);

        // If task is priority, inherit to new subtask
        if ($task->isPriority()) {
            $subTask->setPriority(true);
        }

        $form = $this->createForm(SubTaskType::class, $subTask);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ensure priority inheritance if task is priority
            if ($task->isPriority() && !$subTask->isPriority()) {
                $subTask->setPriority(true);
            }

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

            return $this->redirectToRoute('club_subtask_show', [
                'taskId' => $task->getId(),
                'id' => $subTask->getId(),
            ]);
        }

        return $this->render('club/task/subtask/edit.html.twig', [
            'club' => $club,
            'task' => $task,
            'subTask' => $subTask,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'club_subtask_show', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::VIEW, 'subTask')]
    public function show(SubTask $subTask, Request $request, BreadcrumbBuilder $breadcrumbBuilder): Response
    {
        $club = $this->clubResolver->resolve();
        $task = $subTask->getTask();
        $user = $this->getUser();

        // Configure breadcrumb
        $breadcrumbBuilder
            ->addItem('home', 'club_dashboard')
            ->addItem('tasks', 'club_tasks')
            ->addItem($task->getTitle(), 'club_task_show', ['id' => $task->getId()])
            ->addItem($subTask->getTitle());

        // Complete form (if user can do the subtask)
        $completeForm = null;
        if ($this->isGranted(SubTaskVoter::DO , $subTask) && !$subTask->isDone() && $subTask->getStatus() === 'open') {
            $currentMembership = $this->membershipRepository->findOneBy([
                'user' => $user,
                'club' => $club,
            ]);
            $isManager = $this->isGranted('MANAGE');

            // Check for existing contributions (e.g., after rejection or reopen)
            $existingContributions = $this->contributionRepository->findBySubTaskIndexedByMembership($subTask);
            $formData = null;

            // Find who closed the subtask last (from activities)
            $lastDoneByMembership = null;
            $activities = $subTask->getActivities()->toArray();
            // Filter activities for DONE or INSPECTED_APPROVED (these indicate who closed the subtask)
            $closingActivities = array_filter($activities, function ($activity) {
                return in_array($activity->getType(), [ActivityType::DONE, ActivityType::INSPECTED_APPROVED]);
            });
            
            if (!empty($closingActivities)) {
                // Sort by creation date descending to get the most recent one
                usort($closingActivities, function ($a, $b) {
                    return $b->getCreatedAt() <=> $a->getCreatedAt();
                });
                
                $lastClosingActivity = $closingActivities[0];
                $lastDoneByUser = $lastClosingActivity->getUser();
                
                if ($lastDoneByUser) {
                    $lastDoneByMembership = $this->membershipRepository->findOneBy([
                        'user' => $lastDoneByUser,
                        'club' => $club,
                    ]);
                }
            }

            // Pre-fill form with existing data (if contributions exist or we found who closed it)
            $formData = [];
            
            if ($lastDoneByMembership !== null) {
                // Pre-fill doneBy with the last person who closed the subtask
                $formData['doneBy'] = $lastDoneByMembership;
            } elseif ($currentMembership) {
                // Default to current user if no previous closer found
                $formData['doneBy'] = $currentMembership;
            }
            
            if (!empty($existingContributions)) {
                // Pre-fill time and contributors from existing contributions
                $totalTimeSpent = 0;
                $contributorMemberships = [];

                foreach ($existingContributions as $contribution) {
                    $totalTimeSpent += $contribution->getTimeSpent();
                    $contributorMemberships[] = $contribution->getMembership();
                }

                $formData['timeSpent'] = (int) ceil($totalTimeSpent); // Convert back to integer hours
                $formData['contributors'] = $contributorMemberships;
            } else {
                // Default values if no contributions exist
                $formData['timeSpent'] = 1;
                $formData['contributors'] = $currentMembership ? [$currentMembership] : [];
            }

            $completeForm = $this->createForm(SubTaskCompleteFormType::class, $formData, [
                'club' => $club,
                'current_membership' => $currentMembership,
                'is_manager' => $isManager,
                'subtask' => $subTask,
            ]);
        }

        // Comment form
        $commentForm = null;
        if ($this->isGranted(SubTaskVoter::COMMENT, $subTask)) {
            $commentForm = $this->createForm(ActivityFormType::class, null, [
                'label' => 'comment',
                'placeholder' => $this->translator->trans('addComment'),
            ]);
            $commentForm->handleRequest($request);

            if ($commentForm->isSubmitted() && $commentForm->isValid()) {
                $data = $commentForm->getData();

                $activity = new Activity();
                $activity->setTask($task);
                $activity->setSubTask($subTask);
                $activity->setType(ActivityType::COMMENT);
                $activity->setUser($user);
                $activity->setMessage($data['message']);

                $this->entityManager->persist($activity);
                $this->entityManager->flush();

                // Send email notifications to users with activity on the subtask
                $this->commentNotificationService->sendSubTaskCommentNotifications($subTask, $activity, $user);

                $this->addFlash('success', 'commentAdded');

                return $this->redirectToRoute('club_subtask_show', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
            }
        }

        return $this->render('club/task/subtask/show.html.twig', [
            'club' => $club,
            'task' => $task,
            'subTask' => $subTask,
            'completeForm' => $completeForm?->createView(),
            'commentForm' => $commentForm?->createView(),
        ]);
    }

    #[Route('/{id}/complete', name: 'club_subtask_complete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::DO , 'subTask')]
    public function complete(SubTask $subTask, Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $task = $subTask->getTask();
        $user = $this->getUser();

        $currentMembership = $this->membershipRepository->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);
        $isManager = $this->isGranted('MANAGE');

        $form = $this->createForm(SubTaskCompleteFormType::class, null, [
            'club' => $club,
            'current_membership' => $currentMembership,
            'is_manager' => $isManager,
            'subtask' => $subTask,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            /** @var Membership $doneByMembership */
            $doneByMembership = $data['doneBy'];
            $timeSpent = $data['timeSpent'];
            /** @var array<Membership>|Collection $contributors */
            $contributors = $data['contributors'] ?? [];
            
            // Convert to array if it's a Collection
            if ($contributors instanceof \Doctrine\Common\Collections\Collection) {
                $contributors = $contributors->toArray();
            }
            // Ensure it's an array
            if (!is_array($contributors)) {
                $contributors = [];
            }

            // Validate: if timeSpent is 0, the subtask must be closable (no inspection required or user is inspector)
            $isInspector = $doneByMembership->isInspector();
            if ($timeSpent === 0 && $subTask->requiresInspection() && !$isInspector) {
                $this->addFlash('error', 'timeSpentZeroRequiresClosed');
                return $this->redirectToRoute('club_subtask_show', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
            }

            // Mark subtask as done (auto-validate if user is qualified)
            $this->taskStatusService->handleSubTaskDone(
                $subTask,
                $doneByMembership->getUser(),
                $isInspector,
                $user // completedBy - the logged-in user who submitted the form
            );

            // Always include doneBy as a contributor
            if (!in_array($doneByMembership, $contributors, true)) {
                $contributors[] = $doneByMembership;
            }

            // Calculate time per contributor (divided evenly with decimal precision)
            $timePerContributor = count($contributors) > 0 ? round($timeSpent / count($contributors), 2) : 0;

            // Update or create contributions
            $existingContributions = $this->contributionRepository->findBySubTaskIndexedByMembership($subTask);

            foreach ($contributors as $contributorMembership) {
                $membershipId = $contributorMembership->getId();

                // Check if contribution already exists (e.g., after rejection)
                if (isset($existingContributions[$membershipId])) {
                    // Update existing contribution
                    $contribution = $existingContributions[$membershipId];
                    $contribution->setTimeSpent($timePerContributor);
                    // Remove from array so we can delete unused contributions later
                    unset($existingContributions[$membershipId]);
                } else {
                    // Create new contribution
                    $contribution = new Contribution();
                    $contribution->setSubTask($subTask);
                    $contribution->setMembership($contributorMembership);
                    $contribution->setTimeSpent($timePerContributor);
                    $this->entityManager->persist($contribution);
                }
            }

            // Remove contributions for members no longer selected
            foreach ($existingContributions as $unusedContribution) {
                $this->entityManager->remove($unusedContribution);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'subTaskMarkedAsDone');

            // Check if user can inspect the subtask after completion
            // If not, redirect to task page instead of subtask page
            if (!$this->isGranted(SubTaskVoter::INSPECT, $subTask)) {
                return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
            }

            return $this->redirectToRoute('club_subtask_show', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
        }

        $this->addFlash('error', 'invalidRequest');
        return $this->redirectToRoute('club_subtask_show', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
    }

    #[Route('/{id}/delete-documentation', name: 'club_subtask_delete_documentation', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::EDIT, 'subTask')]
    public function deleteDocumentation(SubTask $subTask, Request $request): Response
    {
        $task = $subTask->getTask();
        $token = $request->query->get('_token');

        if (!$token || !$this->isCsrfTokenValid('del_subtask_doc' . $subTask->getId(), $token)) {
            $this->addFlash('danger', 'invalidToken');
            return $this->redirectToRoute('club_subtask_edit', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
        }

        if (!$subTask->getDocumentation()) {
            $this->addFlash('warning', 'noDocumentationToDelete');
            return $this->redirectToRoute('club_subtask_edit', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
        }

        $documentationUrl = $subTask->getDocumentation();
        $parsedUrl = parse_url($documentationUrl);
        $filePath = $parsedUrl['path'] ?? null;

        if ($filePath) {
            $filePath = ltrim($filePath, '/');
            try {
                if ($this->s3Filesystem->fileExists($filePath)) {
                    $this->s3Filesystem->delete($filePath);
                }
            } catch (\Throwable) {
                // Ignore storage deletion issues, but remove DB reference.
            }
        }

        $subTask->setDocumentation(null);
        $activity = new Activity();
        $activity->setTask($task);
        $activity->setSubTask($subTask);
        $activity->setType(ActivityType::EDITED);
        $activity->setUser($this->getUser());
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $this->addFlash('success', 'subTaskDocumentationDeleted');

        return $this->redirectToRoute('club_subtask_edit', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
    }

    #[Route('/{id}/inspect/approve', name: 'club_subtask_inspect_approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::INSPECT, 'subTask')]
    public function inspectApprove(SubTask $subTask): Response
    {
        $task = $subTask->getTask();
        $this->taskStatusService->handleSubTaskInspectApprove($subTask, $this->getUser());

        $this->addFlash('success', 'inspectionApproved');

        return $this->redirectToRoute('club_subtask_show', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
    }

    #[Route('/{id}/inspect/reject', name: 'club_subtask_inspect_reject', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::INSPECT, 'subTask')]
    public function inspectReject(SubTask $subTask, Request $request): Response
    {
        $task = $subTask->getTask();
        $form = $this->createForm(ActivityFormType::class, null, [
            'required' => true,
            'label' => 'rejectionReason',
            'placeholder' => 'explainReason',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $this->taskStatusService->handleSubTaskInspectReject($subTask, $this->getUser(), $data['message'] ?? null);

            $this->addFlash('success', 'inspectionRejected');

            return $this->redirectToRoute('club_subtask_show', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
        }

        $this->addFlash('error', 'rejectionReasonRequired');
        return $this->redirectToRoute('club_subtask_show', ['taskId' => $task->getId(), 'id' => $subTask->getId()]);
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

    #[Route('/{id}/reopen', name: 'club_subtask_reopen', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted(SubTaskVoter::REOPEN, 'subTask')]
    public function reopen(SubTask $subTask, Request $request): Response
    {
        $task = $subTask->getTask();

        $reason = null;
        
        // Try to get reason from form if present (for modal usage)
        if ($request->request->has('activity_form')) {
            $form = $this->createForm(ActivityFormType::class, null, [
                'required' => false,
                'label' => 'reopenReason',
                'placeholder' => 'optionalReason',
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                $data = $form->getData();
                $reason = $data['message'] ?? null;
            }
        }

        try {
            $this->taskStatusService->handleSubTaskReopen($subTask, $this->getUser(), $reason);

            $this->addFlash('success', 'subTaskReopened');

            return $this->redirectToRoute('club_subtask_show', [
                'taskId' => $task->getId(),
                'id' => $subTask->getId(),
            ]);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('club_subtask_show', [
                'taskId' => $task->getId(),
                'id' => $subTask->getId(),
            ]);
        }
    }

    #[Route('/{id}/toggle-priority', name: 'club_subtask_toggle_priority', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('MANAGE')]
    public function togglePriority(SubTask $subTask): Response
    {
        $club = $this->clubResolver->resolve();
        $task = $subTask->getTask();

        // Check user has access to this task's club
        if (!$this->getUser()->hasAccessToClub($club) || $task->getClub() !== $club) {
            $this->addFlash('error', 'accessDenied');
            return $this->redirectToRoute('club_tasks');
        }

        // Toggle priority
        $newPriority = !$subTask->isPriority();
        $subTask->setPriority($newPriority);

        $this->entityManager->flush();

        $this->addFlash('success', $newPriority ? 'subTaskMarkedAsPriority' : 'subTaskUnmarkedAsPriority');

        return $this->redirectToRoute('club_subtask_show', [
            'taskId' => $task->getId(),
            'id' => $subTask->getId(),
        ]);
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

            // Send email notifications to users with activity on the subtask
            $this->commentNotificationService->sendSubTaskCommentNotifications($subTask, $activity, $this->getUser());

            $this->addFlash('success', 'commentAdded');
        }

        return $this->redirectToRoute('club_task_show', ['id' => $subTask->getTask()->getId()]);
    }
}

