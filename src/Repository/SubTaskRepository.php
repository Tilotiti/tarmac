<?php

namespace App\Repository;

use App\Entity\SubTask;
use App\Entity\Task;
use App\Service\ClubResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SubTask>
 */
class SubTaskRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClubResolver $clubResolver
    ) {
        parent::__construct($registry, SubTask::class);
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

        return $this->createQueryBuilder('subTask')
            ->join('subTask.task', 'task')
            ->where('task.club = :club')
            ->setParameter('club', $club);
    }

    public function filterByTask(QueryBuilder $qb, Task $task): QueryBuilder
    {
        return $qb
            ->andWhere('subTask.task = :task')
            ->setParameter('task', $task);
    }

    public function filterByStatus(QueryBuilder $qb, string $status): QueryBuilder
    {
        return $qb
            ->andWhere('subTask.status = :status')
            ->setParameter('status', $status);
    }

    public function filterByDifficulty(QueryBuilder $qb, int $difficulty): QueryBuilder
    {
        return $qb
            ->andWhere('subTask.difficulty = :difficulty')
            ->setParameter('difficulty', $difficulty);
    }

    public function filterByDifficultyRange(QueryBuilder $qb, int $min, int $max): QueryBuilder
    {
        return $qb
            ->andWhere('subTask.difficulty BETWEEN :minDifficulty AND :maxDifficulty')
            ->setParameter('minDifficulty', $min)
            ->setParameter('maxDifficulty', $max);
    }

    public function filterByAwaitingInspection(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->andWhere('subTask.requiresInspection = true')
            ->andWhere('subTask.doneBy IS NOT NULL')
            ->andWhere('subTask.status = :status')
            ->setParameter('status', 'done');
    }

    /**
     * Ordonne les sous-tâches par leur position au sein de leur tâche.
     *
     * `position` est la seule source de vérité de l'ordre : elle est renumérotée
     * densément par App\Service\Maintenance\SubTaskOrderer à chaque création ou
     * réorganisation.
     */
    public function addPositionOrdering(QueryBuilder $qb, string $direction = 'ASC'): QueryBuilder
    {
        return $qb
            ->addOrderBy('subTask.position', $direction)
            ->addOrderBy('subTask.id', $direction);
    }

    public function orderByPosition(QueryBuilder $qb): QueryBuilder
    {
        return $this->addPositionOrdering($qb);
    }

    public function countAwaitingInspectionByClub(\App\Entity\Club $club): int
    {
        return (int) $this->createQueryBuilder('subTask')
            ->select('COUNT(DISTINCT subTask.id)')
            ->join('subTask.task', 'task')
            ->where('task.club = :club')
            ->andWhere('subTask.requiresInspection = :true')
            ->andWhere('subTask.status = :done')
            ->andWhere('subTask.doneBy IS NOT NULL')
            ->andWhere('subTask.inspectedBy IS NULL')
            ->setParameter('club', $club)
            ->setParameter('true', true)
            ->setParameter('done', 'done')
            ->getQuery()
            ->getSingleScalarResult();
    }
}

