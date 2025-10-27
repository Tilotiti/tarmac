<?php

namespace App\Repository;

use App\Entity\Equipment;
use App\Entity\Enum\EquipmentType;
use App\Entity\PlanApplication;
use App\Entity\Task;
use App\Entity\User;
use App\Service\ClubResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClubResolver $clubResolver
    ) {
        parent::__construct($registry, Task::class);
    }

    /**
     * Base query with club context automatically applied via ClubResolver
     */
    public function queryAll(): QueryBuilder
    {
        $club = $this->clubResolver->getClub();

        if (!$club) {
            throw new \RuntimeException('Club context is required for queryAll()');
        }

        return $this->createQueryBuilder('task')
            ->where('task.club = :club')
            ->setParameter('club', $club);
    }

    /**
     * Query tasks with filters applied
     */
    public function queryByFilters(?array $filters = []): QueryBuilder
    {
        $qb = $this->queryAll();

        if (!empty($filters['equipment'])) {
            $qb = $this->filterByEquipment($qb, $filters['equipment']);
        }

        if (!empty($filters['equipmentType'])) {
            $qb = $this->filterByEquipmentType($qb, $filters['equipmentType']);
        }

        if (!empty($filters['status'])) {
            $qb = $this->filterByStatus($qb, $filters['status']);
        }

        if (!empty($filters['dueDate']) && $filters['dueDate'] !== 'all') {
            $qb = $this->filterByDueDate($qb, $filters['dueDate']);
        }

        if (!empty($filters['difficulty'])) {
            $qb = $this->filterByDifficulty($qb, (int) $filters['difficulty']);
        }

        if (!empty($filters['requiresInspection'])) {
            $qb = $this->filterByRequiresInspection($qb, $filters['requiresInspection'] === '1');
        }

        if (!empty($filters['awaitingInspection']) && $filters['awaitingInspection'] === '1') {
            $qb = $this->filterByAwaitingInspection($qb);
        }

        if (!empty($filters['claimedBy'])) {
            $qb = $this->filterByClaimedBy($qb, $filters['claimedBy']);
        }

        return $qb;
    }

    public function filterByEquipment(QueryBuilder $qb, Equipment $equipment): QueryBuilder
    {
        return $qb
            ->andWhere('task.equipment = :equipment')
            ->setParameter('equipment', $equipment);
    }

    public function filterByEquipmentType(QueryBuilder $qb, EquipmentType $type): QueryBuilder
    {
        return $qb
            ->join('task.equipment', 'equipment')
            ->andWhere('equipment.type = :equipmentType')
            ->setParameter('equipmentType', $type);
    }

    public function filterByStatus(QueryBuilder $qb, string $status): QueryBuilder
    {
        return $qb
            ->andWhere('task.status = :status')
            ->setParameter('status', $status);
    }

    public function filterByDueDate(QueryBuilder $qb, string $filter): QueryBuilder
    {
        $now = new \DateTimeImmutable();

        if ($filter === 'end_of_week') {
            $endOfWeek = $now->modify('Sunday this week')->setTime(23, 59, 59);
            return $qb
                ->andWhere('task.dueAt <= :endOfWeek')
                ->setParameter('endOfWeek', $endOfWeek);
        }

        if ($filter === 'end_of_month') {
            $endOfMonth = $now->modify('last day of this month')->setTime(23, 59, 59);
            return $qb
                ->andWhere('task.dueAt <= :endOfMonth')
                ->setParameter('endOfMonth', $endOfMonth);
        }

        // 'all' - no filter
        return $qb;
    }

    public function filterByDifficulty(QueryBuilder $qb, int $difficulty): QueryBuilder
    {
        return $qb
            ->andWhere('task.difficulty = :difficulty')
            ->setParameter('difficulty', $difficulty);
    }

    public function filterByDifficultyRange(QueryBuilder $qb, int $min, int $max): QueryBuilder
    {
        return $qb
            ->andWhere('task.difficulty BETWEEN :minDifficulty AND :maxDifficulty')
            ->setParameter('minDifficulty', $min)
            ->setParameter('maxDifficulty', $max);
    }

    public function filterByRequiresInspection(QueryBuilder $qb, bool $requiresInspection): QueryBuilder
    {
        return $qb
            ->andWhere('task.requiresInspection = :requiresInspection')
            ->setParameter('requiresInspection', $requiresInspection);
    }

    public function filterByAwaitingInspection(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->andWhere('task.requiresInspection = true')
            ->andWhere('task.doneBy IS NOT NULL')
            ->andWhere('task.status = :status')
            ->setParameter('status', 'open');
    }

    public function filterByClaimedBy(QueryBuilder $qb, User $user): QueryBuilder
    {
        return $qb
            ->andWhere('task.claimedBy = :user')
            ->setParameter('user', $user);
    }

    public function filterByPlanApplication(QueryBuilder $qb, PlanApplication $application): QueryBuilder
    {
        return $qb
            ->andWhere('task.planApplication = :application')
            ->setParameter('application', $application);
    }

    public function orderByDueDate(QueryBuilder $qb, string $direction = 'ASC'): QueryBuilder
    {
        return $qb
            ->addOrderBy('task.dueAt', $direction)
            ->addOrderBy('task.createdAt', $direction);
    }

    /**
     * Smart ordering: for non-done tasks order by dueAt (NULL last), for done tasks order by doneAt
     */
    public function orderByRelevantDate(QueryBuilder $qb, string $direction = 'ASC'): QueryBuilder
    {
        return $qb
            // First, order by status to group done/closed tasks separately
            ->addOrderBy('CASE WHEN task.status IN (:doneStatuses) THEN 1 ELSE 0 END', 'ASC')
            // For non-done tasks: put NULL dueAt last (as farthest in the future)
            ->addOrderBy('CASE WHEN task.status NOT IN (:doneStatuses) AND task.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
            // For non-done tasks: order by dueAt, for done tasks: order by doneAt
            ->addOrderBy('CASE WHEN task.status NOT IN (:doneStatuses) THEN task.dueAt ELSE task.doneAt END', $direction)
            // Final fallback: createdAt
            ->addOrderBy('task.createdAt', $direction)
            ->setParameter('doneStatuses', ['done', 'closed']);
    }

    public function orderByStatus(QueryBuilder $qb): QueryBuilder
    {
        return $qb->addOrderBy('task.status', 'ASC');
    }

    public function countPendingForApplication(PlanApplication $application): int
    {
        return (int) $this->createQueryBuilder('task')
            ->select('COUNT(task.id)')
            ->where('task.planApplication = :application')
            ->andWhere('task.status != :cancelled')
            ->setParameter('application', $application)
            ->setParameter('cancelled', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

