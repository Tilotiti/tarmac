<?php

namespace App\Service\Maintenance;

use App\Entity\Activity;
use App\Entity\Enum\ActivityType;
use App\Entity\Equipment;
use App\Entity\Plan;
use App\Entity\PlanApplication;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class PlanApplier
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Apply a maintenance plan to equipment, creating tasks and subtasks
     */
    public function applyPlan(
        Plan $plan,
        Equipment $equipment,
        User $appliedBy,
        ?\DateTimeImmutable $dueAt = null
    ): PlanApplication {
        // Validate equipment type matches plan
        if ($equipment->getType() !== $plan->getEquipmentType()) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Equipment type "%s" does not match plan type "%s"',
                    $equipment->getType()->value,
                    $plan->getEquipmentType()->value
                )
            );
        }

        // Create the application
        $application = new PlanApplication();
        $application->setPlan($plan);
        $application->setEquipment($equipment);
        $application->setAppliedBy($appliedBy);
        $application->setDueAt($dueAt);

        $this->entityManager->persist($application);

        // Create tasks from templates
        $taskPosition = 0;
        foreach ($plan->getTaskTemplates() as $taskTemplate) {
            $task = new Task();
            $task->setClub($equipment->getClub());
            $task->setEquipment($equipment);
            $task->setTitle($taskTemplate->getTitle());
            $task->setDescription($taskTemplate->getDescription());
            $task->setDocumentation($taskTemplate->getDocumentation());
            $task->setCreatedBy($appliedBy);
            $task->setPlanApplication($application);
            // Copy plan position to preserve ordering from the maintenance plan
            $task->setPlanPosition($taskTemplate->getPosition());

            // Set due date if provided
            if ($dueAt) {
                $task->setDueAt($dueAt);
            }

            $this->entityManager->persist($task);
            $application->addTask($task);

            // Create subtasks from templates
            $subTaskPosition = 0;
            foreach ($taskTemplate->getSubTaskTemplates() as $subTaskTemplate) {
                $subTask = new SubTask();
                $subTask->setTask($task);
                $subTask->setTitle($subTaskTemplate->getTitle());
                $subTask->setDescription($subTaskTemplate->getDescription());
                $subTask->setDifficulty($subTaskTemplate->getDifficulty());
                $subTask->setRequiresInspection($subTaskTemplate->requiresInspection());
                $subTask->setDocumentation($subTaskTemplate->getDocumentation());
                $subTask->setPosition($subTaskPosition++);
                // Copy plan position to preserve ordering from the maintenance plan
                $subTask->setPlanPosition($subTaskTemplate->getPosition());

                $this->entityManager->persist($subTask);
                $task->addSubTask($subTask);

                // Log subtask creation activity
                $subTaskActivity = new Activity();
                $subTaskActivity->setTask($task);
                $subTaskActivity->setSubTask($subTask);
                $subTaskActivity->setType(ActivityType::CREATED);
                $subTaskActivity->setUser($appliedBy);
                $this->entityManager->persist($subTaskActivity);
            }

            // Log task creation activity
            $activity = new Activity();
            $activity->setTask($task);
            $activity->setType(ActivityType::CREATED);
            $activity->setUser($appliedBy);
            $this->entityManager->persist($activity);

            $taskPosition++;
        }

        $this->entityManager->flush();

        return $application;
    }

    /**
     * Get maintenance plan applications within a date range (for timeline)
     */
    public function getApplicationsInDateRange(
        Equipment $equipment,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->entityManager->getRepository(PlanApplication::class)
            ->createQueryBuilder('plan_application')
            ->where('plan_application.equipment = :equipment')
            ->andWhere('plan_application.dueAt BETWEEN :start AND :end')
            ->andWhere('plan_application.cancelledBy IS NULL')
            ->setParameter('equipment', $equipment)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('plan_application.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all applications for a specific equipment
     */
    public function getEquipmentApplications(Equipment $equipment): array
    {
        return $this->entityManager->getRepository(PlanApplication::class)
            ->createQueryBuilder('plan_application')
            ->where('plan_application.equipment = :equipment')
            ->andWhere('plan_application.cancelledBy IS NULL')
            ->setParameter('equipment', $equipment)
            ->orderBy('plan_application.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

