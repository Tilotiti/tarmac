<?php

namespace App\Service\Maintenance;

use App\Entity\PlanSubTask;
use App\Entity\SubTask;
use Doctrine\ORM\EntityManagerInterface;

class SpecialisationSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Sync specialisations from a PlanSubTask to all applied SubTasks that match
     * by plan + task title + subtask title. Only the specialisations collection is updated.
     */
    public function syncSpecialisationsToAppliedSubTasks(PlanSubTask $planSubTask): void
    {
        $plan = $planSubTask->getTaskTemplate()?->getPlan();
        if ($plan === null) {
            return;
        }

        $taskTemplateTitle = $planSubTask->getTaskTemplate()->getTitle();
        $planSubTaskTitle = $planSubTask->getTitle();

        foreach ($plan->getApplications() as $application) {
            if ($application->isCancelled()) {
                continue;
            }

            foreach ($application->getTasks() as $task) {
                if ($task->getTitle() !== $taskTemplateTitle) {
                    continue;
                }

                foreach ($task->getSubTasks() as $subTask) {
                    if ($subTask->getTitle() !== $planSubTaskTitle) {
                        continue;
                    }

                    $this->replaceSubTaskSpecialisations($subTask, $planSubTask);
                }
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Sync specialisations to applied SubTasks without flushing (caller must flush).
     * Use this when batching updates (e.g. during plan import).
     */
    public function syncSpecialisationsToAppliedSubTasksNoFlush(PlanSubTask $planSubTask): void
    {
        $plan = $planSubTask->getTaskTemplate()?->getPlan();
        if ($plan === null) {
            return;
        }

        $taskTemplateTitle = $planSubTask->getTaskTemplate()->getTitle();
        $planSubTaskTitle = $planSubTask->getTitle();

        foreach ($plan->getApplications() as $application) {
            if ($application->isCancelled()) {
                continue;
            }

            foreach ($application->getTasks() as $task) {
                if ($task->getTitle() !== $taskTemplateTitle) {
                    continue;
                }

                foreach ($task->getSubTasks() as $subTask) {
                    if ($subTask->getTitle() !== $planSubTaskTitle) {
                        continue;
                    }

                    $this->replaceSubTaskSpecialisations($subTask, $planSubTask);
                }
            }
        }
    }

    private function replaceSubTaskSpecialisations(SubTask $subTask, PlanSubTask $planSubTask): void
    {
        $subTask->getSpecialisations()->clear();

        foreach ($planSubTask->getSpecialisations() as $specialisation) {
            $subTask->addSpecialisation($specialisation);
        }
    }
}
