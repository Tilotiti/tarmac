<?php

namespace App\Repository;

use App\Entity\Contribution;
use App\Entity\Club;
use App\Entity\Membership;
use App\Entity\Specialisation;
use App\Entity\SubTask;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * Base query for a member's contributions within a club, with common joins.
     */
    private function createLogbookBaseQuery(Membership $membership, Club $club): QueryBuilder
    {
        return $this->createQueryBuilder('contribution')
            ->join('contribution.subTask', 'subtask')
            ->join('subtask.task', 'task')
            ->join('task.equipment', 'equipment')
            ->where('contribution.membership = :membership')
            ->andWhere('task.club = :club')
            ->setParameter('membership', $membership)
            ->setParameter('club', $club);
    }

    /**
     * Apply logbook filters to a base query.
     *
     * Expected filters:
     *  - search: string (on task and subtask titles)
     *  - specialisation: Specialisation
     *  - equipmentType: EquipmentType
     *  - equipment: Equipment
     *  - periodStart: \DateTimeInterface
     *  - periodEnd: \DateTimeInterface
     *  - signedOnly: bool (filter by subtask.completedBy = logbook user)
     *  - status: string (subtask status)
     */
    private function applyLogbookFilters(QueryBuilder $qb, array $filters, Membership $membership): QueryBuilder
    {
        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $qb
                    ->andWhere('LOWER(task.title) LIKE :search OR LOWER(subtask.title) LIKE :search')
                    ->setParameter('search', '%' . mb_strtolower(addcslashes($search, '%_')) . '%');
            }
        }

        if (!empty($filters['specialisation'])) {
            $qb->join('subtask.specialisations', 'specialisation');

            // Support single or multiple specialisations
            if (is_iterable($filters['specialisation'])) {
                $qb
                    ->andWhere('specialisation IN (:specialisations)')
                    ->setParameter('specialisations', $filters['specialisation']);
            } elseif ($filters['specialisation'] instanceof Specialisation) {
                $qb
                    ->andWhere('specialisation = :specialisation')
                    ->setParameter('specialisation', $filters['specialisation']);
            }
        }

        if (!empty($filters['equipmentType'])) {
            $qb
                ->andWhere('equipment.type = :equipmentType')
                ->setParameter('equipmentType', $filters['equipmentType']);
        }

        if (!empty($filters['equipment'])) {
            $qb
                ->andWhere('task.equipment = :equipment')
                ->setParameter('equipment', $filters['equipment']);
        }

        if (!empty($filters['periodStart']) && $filters['periodStart'] instanceof \DateTimeInterface) {
            $qb
                ->andWhere('contribution.createdAt >= :periodStart')
                ->setParameter('periodStart', $filters['periodStart']);
        }

        if (!empty($filters['periodEnd']) && $filters['periodEnd'] instanceof \DateTimeInterface) {
            $qb
                ->andWhere('contribution.createdAt <= :periodEnd')
                ->setParameter('periodEnd', $filters['periodEnd']);
        }

        if (!empty($filters['signedOnly'])) {
            $qb
                ->andWhere('subtask.completedBy = :signedUser')
                ->setParameter('signedUser', $membership->getUser());
        }

        if (!empty($filters['status'])) {
            if (is_iterable($filters['status'])) {
                $qb
                    ->andWhere('subtask.status IN (:statuses)')
                    ->setParameter('statuses', $filters['status']);
            } else {
                $qb
                    ->andWhere('subtask.status = :status')
                    ->setParameter('status', $filters['status']);
            }
        }

        return $qb;
    }

    /**
     * Query builder for a member's contributions with filters applied (for listing with pagination).
     */
    public function queryByMembershipWithFilters(Membership $membership, Club $club, array $filters = []): QueryBuilder
    {
        $qb = $this->createLogbookBaseQuery($membership, $club);

        $qb = $this->applyLogbookFilters($qb, $filters, $membership);

        // Order by contribution date (newest first), then task and subtask for stable ordering
        $qb
            ->orderBy('contribution.createdAt', 'DESC')
            ->addOrderBy('task.id', 'ASC')
            ->addOrderBy('subtask.position', 'ASC');

        return $qb;
    }

    /**
     * Compute global and per-specialisation statistics for a member's contributions
     * based on the same filters as the logbook list.
     *
     * Returns an array:
     * [
     *   'global' => ['count' => int, 'time' => float],
     *   'bySpecialisation' => [
     *      [
     *        'specialisation' => Specialisation,
     *        'count' => int,
     *        'time' => float,
     *      ],
     *      ...
     *   ],
     * ]
     */
    public function getFacetsByMembershipAndFilters(Membership $membership, Club $club, array $filters = []): array
    {
        // Base query without pagination
        $baseQb = $this->createLogbookBaseQuery($membership, $club);
        $baseQb = $this->applyLogbookFilters($baseQb, $filters, $membership);

        // Global stats
        $globalQb = clone $baseQb;
        $globalQb
            ->select('COUNT(DISTINCT contribution.id) AS contributionsCount')
            ->addSelect('COALESCE(SUM(contribution.timeSpent), 0) AS totalTime');

        $globalResult = $globalQb->getQuery()->getSingleResult();

        $global = [
            'count' => (int) ($globalResult['contributionsCount'] ?? 0),
            'time' => (float) ($globalResult['totalTime'] ?? 0.0),
        ];

        // Per-specialisation stats: select specialisation id + aggregates, then load entities
        $specialisationQb = clone $baseQb;
        $specialisationQb
            ->join('subtask.specialisations', 'facet_specialisation')
            ->select('facet_specialisation.id AS specialisationId')
            ->addSelect('COUNT(DISTINCT contribution.id) AS contributionsCount')
            ->addSelect('COALESCE(SUM(contribution.timeSpent), 0) AS totalTime')
            ->groupBy('facet_specialisation.id');

        $specialisationResults = $specialisationQb->getQuery()->getResult();

        $bySpecialisation = [];
        if (!empty($specialisationResults)) {
            $specialisationIds = array_map(fn ($row) => $row['specialisationId'], $specialisationResults);
            $specialisations = $this->getEntityManager()->getRepository(Specialisation::class)
                ->createQueryBuilder('s')
                ->where('s.id IN (:ids)')
                ->setParameter('ids', $specialisationIds)
                ->indexBy('s', 's.id')
                ->getQuery()
                ->getResult();

            foreach ($specialisationResults as $row) {
                $id = $row['specialisationId'];
                $specialisation = $specialisations[$id] ?? null;
                if ($specialisation instanceof Specialisation) {
                    $bySpecialisation[] = [
                        'specialisation' => $specialisation,
                        'count' => (int) ($row['contributionsCount'] ?? 0),
                        'time' => (float) ($row['totalTime'] ?? 0.0),
                    ];
                }
            }
        }

        return [
            'global' => $global,
            'bySpecialisation' => $bySpecialisation,
        ];
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
            // Order by subtask position (plan-aware)
            ->orderBy('CASE WHEN subtask.planPosition IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('subtask.planPosition', 'ASC')
            ->addOrderBy('subtask.position', 'ASC')
            ->addOrderBy('contribution.timeSpent', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a contribution by subtask and membership
     */
    public function findOneBySubTaskAndMembership(SubTask $subTask, Membership $membership): ?Contribution
    {
        return $this->createQueryBuilder('c')
            ->where('c.subTask = :subTask')
            ->andWhere('c.membership = :membership')
            ->setParameter('subTask', $subTask)
            ->setParameter('membership', $membership)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all contributions for a subtask indexed by membership ID
     *
     * @return array<int, Contribution>
     */
    public function findBySubTaskIndexedByMembership(SubTask $subTask): array
    {
        $contributions = $this->createQueryBuilder('c')
            ->where('c.subTask = :subTask')
            ->setParameter('subTask', $subTask)
            ->getQuery()
            ->getResult();

        $indexed = [];
        foreach ($contributions as $contribution) {
            $indexed[$contribution->getMembership()->getId()] = $contribution;
        }

        return $indexed;
    }
}

