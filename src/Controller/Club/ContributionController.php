<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Activity;
use App\Entity\Contribution;
use App\Entity\Enum\ActivityType;
use App\Form\ContributionAddFormType;
use App\Repository\ContributionRepository;
use App\Repository\MembershipRepository;
use App\Security\Voter\SubTaskVoter;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tasks/{taskId}/subtasks/{subTaskId}/contributions', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*', 'taskId' => '\d+', 'subTaskId' => '\d+'])]
#[IsGranted('ROLE_USER')]
class ContributionController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly MembershipRepository $membershipRepository,
        private readonly ContributionRepository $contributionRepository,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('/add', name: 'club_subtask_contribution_add', methods: ['POST'])]
    #[IsGranted(SubTaskVoter::CONTRIBUTE, 'subTask')]
    public function add(
        #[MapEntity(id: 'taskId')] \App\Entity\Task $task,
        #[MapEntity(id: 'subTaskId')] \App\Entity\SubTask $subTask,
        Request $request,
    ): Response {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        $currentMembership = $this->membershipRepository->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);

        if (!$currentMembership) {
            $this->addFlash('error', 'membershipRequired');
            return $this->redirectToRoute('club_subtask_show', [
                'taskId' => $task->getId(),
                'id' => $subTask->getId(),
            ]);
        }

        $existingContribution = $this->contributionRepository->findOneBySubTaskAndMembership($subTask, $currentMembership);

        $form = $this->createForm(ContributionAddFormType::class, $existingContribution ? [
            'timeSpent' => $existingContribution->getTimeSpent(),
        ] : ['timeSpent' => 1]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $timeSpent = (string) $form->get('timeSpent')->getData();

            if ($existingContribution) {
                $existingContribution->setTimeSpent($timeSpent);
                $contribution = $existingContribution;
            } else {
                $contribution = new Contribution();
                $contribution->setSubTask($subTask);
                $contribution->setMembership($currentMembership);
                $contribution->setTimeSpent($timeSpent);
                $this->entityManager->persist($contribution);
            }

            $activity = new Activity();
            $activity->setTask($task);
            $activity->setSubTask($subTask);
            $activity->setType(ActivityType::CONTRIBUTED);
            $activity->setUser($user);
            $this->entityManager->persist($activity);

            $this->entityManager->flush();

            $this->addFlash('success', 'contributionAdded');

            return $this->redirectToRoute('club_subtask_show', [
                'taskId' => $task->getId(),
                'id' => $subTask->getId(),
            ]);
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash('error', 'invalidContribution');
        }

        return $this->redirectToRoute('club_subtask_show', [
            'taskId' => $task->getId(),
            'id' => $subTask->getId(),
        ]);
    }

    #[Route('/remove', name: 'club_subtask_contribution_remove', methods: ['GET'])]
    #[IsGranted(SubTaskVoter::CONTRIBUTE, 'subTask')]
    public function remove(
        #[MapEntity(id: 'taskId')] \App\Entity\Task $task,
        #[MapEntity(id: 'subTaskId')] \App\Entity\SubTask $subTask,
        Request $request,
    ): Response {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        $token = $request->query->get('_token');
        if (!$token || !$this->isCsrfTokenValid('remove_contribution_' . $subTask->getId(), $token)) {
            $this->addFlash('error', 'invalidToken');
            return $this->redirectToRoute('club_subtask_show', [
                'taskId' => $task->getId(),
                'id' => $subTask->getId(),
            ]);
        }

        $currentMembership = $this->membershipRepository->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);

        if (!$currentMembership) {
            $this->addFlash('error', 'membershipRequired');
            return $this->redirectToRoute('club_subtask_show', [
                'taskId' => $task->getId(),
                'id' => $subTask->getId(),
            ]);
        }

        $existingContribution = $this->contributionRepository->findOneBySubTaskAndMembership($subTask, $currentMembership);
        if (!$existingContribution) {
            $this->addFlash('warning', 'noContributionToRemove');
            return $this->redirectToRoute('club_subtask_show', [
                'taskId' => $task->getId(),
                'id' => $subTask->getId(),
            ]);
        }

        $this->entityManager->remove($existingContribution);
        $this->entityManager->flush();

        $this->addFlash('success', 'contributionRemoved');

        return $this->redirectToRoute('club_subtask_show', [
            'taskId' => $task->getId(),
            'id' => $subTask->getId(),
        ]);
    }
}
