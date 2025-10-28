<?php

namespace App\Repository;

use App\Entity\Contribution;
use App\Entity\Membership;
use App\Entity\SubTask;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contribution>
 */
class ContributionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contribution::class);
    }

    /**
     * Get all contributions for a subtask
     *
     * @return Contribution[]
     */
    public function findBySubTask(SubTask $subTask): array
    {
        return $this->createQueryBuilder('contribution')
            ->where('contribution.subTask = :subTask')
            ->setParameter('subTask', $subTask)
            ->orderBy('contribution.timeSpent', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get total time contributed by a member across all tasks
     */
    public function getTotalTimeByMember(Membership $membership): int
    {
        $result = $this->createQueryBuilder('contribution')
            ->select('SUM(contribution.timeSpent)')
            ->where('contribution.membership = :membership')
            ->setParameter('membership', $membership)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Find all contributions for a task's subtasks
     *
     * @return Contribution[]
     */
    public function findByTask(Task $task): array
    {
        return $this->createQueryBuilder('contribution')
            ->join('contribution.subTask', 'subtask')
            ->where('subtask.task = :task')
            ->setParameter('task', $task)
            ->orderBy('subtask.position', 'ASC')
            ->addOrderBy('contribution.timeSpent', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

