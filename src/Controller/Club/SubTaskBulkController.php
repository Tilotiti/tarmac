<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Activity;
use App\Entity\Contribution;
use App\Entity\Enum\ActivityType;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Repository\ContributionRepository;
use App\Repository\MembershipRepository;
use App\Security\Voter\SubTaskVoter;
use App\Service\ClubResolver;
use App\Service\Maintenance\TaskStatusService;
use App\Service\SubdomainService;
use App\Form\ActivityFormType;
use App\Form\ContributionAddFormType;
use App\Form\SubTaskCompleteFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tasks/{taskId}/subtasks/bulk', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*', 'taskId' => '\d+'])]
#[IsGranted('ROLE_USER')]
class SubTaskBulkController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly MembershipRepository $membershipRepository,
        private readonly ContributionRepository $contributionRepository,
        private readonly TaskStatusService $taskStatusService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    /**
     * Ajouter une contribution pour l'utilisateur courant sur plusieurs sous-tâches.
     *
     * Le temps total saisi est réparti équitablement entre les sous-tâches éligibles.
     * Les sous-tâches non éligibles (statut ou droits) sont ignorées.
     */
    #[Route('/add-contribution', name: 'club_subtasks_bulk_add_contribution', methods: ['POST'])]
    public function addContribution(
        #[MapEntity(id: 'taskId')] Task $task,
        Request $request,
    ): Response {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        $membership = $this->membershipRepository->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);

        if (!$membership) {
            return $this->json([
                'success' => false,
                'message' => 'membershipRequired',
            ], Response::HTTP_FORBIDDEN);
        }

        // Formulaire Symfony pour le temps total (réutilise ContributionAddFormType)
        $form = $this->createForm(ContributionAddFormType::class);
        $form->handleRequest($request);

        $subTaskIds = $this->getIdsFromRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'invalidRequest');
            return $this->redirectBackToTask($task, $request);
        }

        if (!$form->isValid()) {
            $this->addFlash('error', 'invalidRequest');
            return $this->redirectBackToTask($task, $request);
        }

        if (empty($subTaskIds)) {
            $this->addFlash('error', 'noSubTaskIdsSelected');
            return $this->redirectBackToTask($task, $request);
        }

        $timeSpent = (float) ($form->get('timeSpent')->getData() ?? 0);
        $timeTotalMinutes = (int) round($timeSpent * 60);

        if ($timeTotalMinutes <= 0) {
            $this->addFlash('error', 'timeSpentRequired');
            return $this->redirectBackToTask($task, $request);
        }

        $eligibleSubTasks = [];
        foreach ($task->getSubTasks() as $subTask) {
            if (!in_array($subTask->getId(), $subTaskIds, true)) {
                continue;
            }

            if (!$this->isGranted(SubTaskVoter::CONTRIBUTE, $subTask)) {
                continue;
            }

            // On ne contribue que sur les sous-tâches ouvertes
            if ($subTask->getStatus() !== 'open') {
                continue;
            }

            $eligibleSubTasks[] = $subTask;
        }

        $count = count($eligibleSubTasks);
        if ($count === 0) {
            $this->addFlash('error', 'noEligibleSubTasks');
            return $this->redirectBackToTask($task, $request);
        }

        $basePerSubTask = intdiv($timeTotalMinutes, $count);
        $remainder = $timeTotalMinutes % $count;

        $processed = 0;

        foreach ($eligibleSubTasks as $index => $subTask) {
            $minutes = $basePerSubTask + ($index < $remainder ? 1 : 0);
            if ($minutes <= 0) {
                continue;
            }

            // Stocker en heures décimales (1.5 = 1h30), pas en format HH.MM (1.30 serait 1h18)
            $decimalHours = round($minutes / 60, 2);
            $timeSpentString = (string) $decimalHours;

            $existingContribution = $this->contributionRepository->findOneBySubTaskAndMembership(
                $subTask,
                $membership
            );

            if ($existingContribution) {
                $existingContribution->setTimeSpent($timeSpentString);
                $contribution = $existingContribution;
            } else {
                $contribution = new Contribution();
                $contribution->setSubTask($subTask);
                $contribution->setMembership($membership);
                $contribution->setTimeSpent($timeSpentString);
                $this->entityManager->persist($contribution);
            }

            $activity = new Activity();
            $activity->setTask($task);
            $activity->setSubTask($subTask);
            $activity->setType(ActivityType::CONTRIBUTED);
            $activity->setUser($user);
            $this->entityManager->persist($activity);

            $processed++;
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'bulkContributionAdded');

        return $this->redirectBackToTask($task, $request);
    }

    /**
     * Clôturer plusieurs sous-tâches à partir d’une configuration globale de contributions.
     *
     * Le payload doit contenir une liste de contributions par membre avec un temps total en minutes.
     * Pour chaque membre, le temps est réparti équitablement entre les sous-tâches éligibles.
     * Si une contribution existe déjà pour ce membre sur une sous-tâche, elle est conservée et on ajoute uniquement une nouvelle contribution complémentaire.
     */
    #[Route('/close', name: 'club_subtasks_bulk_close', methods: ['POST'])]
    public function close(
        #[MapEntity(id: 'taskId')] Task $task,
        Request $request,
    ): Response {
        $user = $this->getUser();
        $club = $this->clubResolver->resolve();

        // Placeholder vierge pour le formulaire (aucune contribution préremplie)
        $placeholderSubTask = new SubTask();
        $placeholderSubTask->setTask($task);
        $placeholderSubTask->setTitle('-'); // Titre requis pour validation, non utilisé en bulk close
        $placeholderSubTask->setPosition(0);
        $placeholderSubTask->setDoneBy($user);

        $form = $this->createForm(SubTaskCompleteFormType::class, $placeholderSubTask, [
            'subtask' => $placeholderSubTask,
            'initial_done_by' => $user,
        ]);
        $form->handleRequest($request);

        $subTaskIds = $this->getIdsFromRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'invalidRequest');
            return $this->redirectBackToTask($task, $request);
        }

        if (!$form->isValid()) {
            $this->addFlash('error', 'invalidRequest');
            return $this->redirectBackToTask($task, $request);
        }

        if (empty($subTaskIds)) {
            $this->addFlash('error', 'noSubTaskIdsSelected');
            return $this->redirectBackToTask($task, $request);
        }

        /** @var SubTask $formSubTask */
        $formSubTask = $form->getData();
        $doneByUser = $formSubTask->getDoneBy();
        $contributions = $formSubTask->getContributions();

        if (!$doneByUser || $contributions->isEmpty()) {
            $this->addFlash('error', 'timeSpentRequired');
            return $this->redirectBackToTask($task, $request);
        }

        $club = $this->clubResolver->resolve();
        $doneByMembership = $this->membershipRepository->findOneBy([
            'user' => $doneByUser,
            'club' => $club,
        ]);

        if (!$doneByMembership) {
            $this->addFlash('error', 'membershipRequired');
            return $this->redirectBackToTask($task, $request);
        }

        /** @var SubTask[] $eligibleSubTasks */
        $eligibleSubTasks = [];
        foreach ($task->getSubTasks() as $subTask) {
            if (!in_array($subTask->getId(), $subTaskIds, true)) {
                continue;
            }

            if (!$this->isGranted(SubTaskVoter::DO, $subTask)) {
                continue;
            }

            if ($subTask->isClosed() || $subTask->isCancelled()) {
                continue;
            }

            $eligibleSubTasks[] = $subTask;
        }

        $countEligible = count($eligibleSubTasks);
        if ($countEligible === 0) {
            $this->addFlash('error', 'noEligibleSubTasks');
            return $this->redirectBackToTask($task, $request);
        }

        // Répartir chaque contribution (membership + timeSpent) sur les sous-tâches éligibles
        foreach ($contributions as $formContribution) {
            $membership = $formContribution->getMembership();
            $timeSpent = (float) ($formContribution->getTimeSpent() ?? 0);
            $timeTotalMinutes = (int) round($timeSpent * 60);

            if ($timeTotalMinutes <= 0 || !$membership) {
                continue;
            }

            $basePerSubTask = intdiv($timeTotalMinutes, $countEligible);
            $remainder = $timeTotalMinutes % $countEligible;

            foreach ($eligibleSubTasks as $index => $subTask) {
                $minutes = $basePerSubTask + ($index < $remainder ? 1 : 0);
                if ($minutes <= 0) {
                    continue;
                }

                $decimalHours = round($minutes / 60, 2);
                $timeSpentString = (string) $decimalHours;

                $existingContribution = $this->contributionRepository
                    ->findOneBySubTaskAndMembership($subTask, $membership);

                if ($existingContribution) {
                    $existingContribution->setTimeSpent($timeSpentString);
                } else {
                    $contribution = new Contribution();
                    $contribution->setSubTask($subTask);
                    $contribution->setMembership($membership);
                    $contribution->setTimeSpent($timeSpentString);
                    $this->entityManager->persist($contribution);
                }
            }
        }

        $isInspector = $doneByMembership->isInspector();
        foreach ($eligibleSubTasks as $subTask) {
            if ($subTask->isDone() || $subTask->isClosed()) {
                continue;
            }

            $this->taskStatusService->handleSubTaskDone(
                $subTask,
                $doneByUser,
                $isInspector,
                $user
            );
        }

        foreach ($eligibleSubTasks as $subTask) {
            if (!$subTask->isClosed()) {
                continue;
            }

            $activity = new Activity();
            $activity->setTask($task);
            $activity->setSubTask($subTask);
            $activity->setType(ActivityType::CLOSED);
            $activity->setUser($user);
            $this->entityManager->persist($activity);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'bulkSubTasksClosed');

        return $this->redirectBackToTask($task, $request);
    }

    /**
     * Annuler plusieurs sous-tâches avec un commentaire commun (formulaire ActivityFormType).
     *
     * Les sous-tâches non annulables sont ignorées.
     */
    #[Route('/cancel', name: 'club_subtasks_bulk_cancel', methods: ['POST'])]
    public function cancel(
        #[MapEntity(id: 'taskId')] Task $task,
        Request $request,
    ): Response {
        $user = $this->getUser();

        $form = $this->createForm(ActivityFormType::class, null, [
            'label' => 'cancellationReason',
            'required' => false,
            'placeholder' => 'optionalReason',
        ]);
        $form->handleRequest($request);

        $subTaskIds = $this->getIdsFromRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'invalidRequest');
            return $this->redirectBackToTask($task, $request);
        }

        if (!$form->isValid()) {
            $this->addFlash('error', 'invalidRequest');
            return $this->redirectBackToTask($task, $request);
        }

        if (empty($subTaskIds)) {
            $this->addFlash('error', 'noSubTaskIdsSelected');
            return $this->redirectBackToTask($task, $request);
        }

        $data = $form->getData();
        $message = $data['message'] ?? null;

        $eligibleSubTasks = [];
        foreach ($task->getSubTasks() as $subTask) {
            if (!in_array($subTask->getId(), $subTaskIds, true)) {
                continue;
            }

            if (!$this->isGranted(SubTaskVoter::CANCEL, $subTask)) {
                continue;
            }

            if ($subTask->getStatus() !== 'open') {
                continue;
            }

            $eligibleSubTasks[] = $subTask;
        }

        if ($eligibleSubTasks === []) {
            $this->addFlash('error', 'noEligibleSubTasks');
            return $this->redirectBackToTask($task, $request);
        }

        foreach ($eligibleSubTasks as $subTask) {
            $this->taskStatusService->handleCancelSubTask($subTask, $user, $message);
        }

        $this->addFlash('success', 'bulkSubTasksCancelled');

        return $this->redirectBackToTask($task, $request);
    }

    /**
     * Extrait un tableau d'IDs de sous-tâches depuis la requête (formulaire classique).
     */
    private function getIdsFromRequest(Request $request): array
    {
        $ids = $request->request->all('subTaskIds');
        return $this->extractIdsFromArray($ids);
    }

    /**
     * Extrait un entier (>= 0) depuis la requête.
     */
    private function getIntFromRequest(Request $request, string $key): int
    {
        $value = $request->request->get($key);
        if ($value === null) {
            return 0;
        }

        return max(0, (int) $value);
    }

    /**
     * Normalise un tableau brut d'IDs (provenant d'un formulaire ou d'un payload JSON).
     *
     * @param mixed $rawIds
     *
     * @return int[]
     */
    private function extractIdsFromArray(mixed $rawIds): array
    {
        if (!is_array($rawIds)) {
            return [];
        }

        return array_values(array_unique(
            array_filter(
                array_map(static fn ($id) => (int) $id, $rawIds),
                static fn (int $id) => $id > 0
            )
        ));
    }

    private function redirectBackToTask(Task $task, Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('club_task_show', ['id' => $task->getId()]);
    }
}

