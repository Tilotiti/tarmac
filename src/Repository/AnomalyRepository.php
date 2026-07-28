<?php

namespace App\Repository;

use App\Entity\Anomaly;
use App\Entity\Club;
use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\AnomalyResolution;
use App\Entity\Enum\AnomalyStatus;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentType;
use App\Service\ClubResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Anomaly>
 */
class AnomalyRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClubResolver $clubResolver
    ) {
        parent::__construct($registry, Anomaly::class);
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

        return $this->createQueryBuilder('anomaly')
            ->where('anomaly.club = :club')
            ->setParameter('club', $club);
    }

    /**
     * Query anomalies with filters applied
     */
    public function queryByFilters(?array $filters = []): QueryBuilder
    {
        $qb = $this->queryAll();

        if (!empty(trim($filters['name'] ?? ''))) {
            $qb = $this->filterByTitle($qb, trim($filters['name']));
        }

        if (!empty($filters['equipment'])) {
            $qb = $this->filterByEquipment($qb, $filters['equipment']);
        }

        if (!empty($filters['equipmentType'])) {
            $qb = $this->filterByEquipmentType($qb, $filters['equipmentType']);
        }

        if (!empty($filters['impact'])) {
            $qb = $this->filterByImpact($qb, $filters['impact']);
        }

        if (!empty($filters['status'])) {
            $qb = $this->filterByStatus($qb, $filters['status']);
        }

        return $qb;
    }

    public function filterByTitle(QueryBuilder $qb, string $search): QueryBuilder
    {
        return $qb
            ->andWhere('LOWER(anomaly.title) LIKE LOWER(:anomalyTitle) OR LOWER(anomaly.description) LIKE LOWER(:anomalyTitle)')
            ->setParameter('anomalyTitle', '%' . addcslashes($search, '%_') . '%');
    }

    public function filterByEquipment(QueryBuilder $qb, Equipment $equipment): QueryBuilder
    {
        return $qb
            ->andWhere('anomaly.equipment = :equipment')
            ->setParameter('equipment', $equipment);
    }

    public function filterByEquipmentType(QueryBuilder $qb, EquipmentType $type): QueryBuilder
    {
        return $qb
            ->join('anomaly.equipment', 'equipment')
            ->andWhere('equipment.type = :equipmentType')
            ->setParameter('equipmentType', $type);
    }

    /**
     * @param string|AnomalyImpact|array<string|AnomalyImpact> $impact
     */
    public function filterByImpact(QueryBuilder $qb, string|AnomalyImpact|array $impact): QueryBuilder
    {
        $impacts = [];

        foreach (is_array($impact) ? $impact : [$impact] as $value) {
            $case = $value instanceof AnomalyImpact ? $value : AnomalyImpact::tryFrom((string) $value);

            if ($case !== null) {
                $impacts[] = $case;
            }
        }

        if ($impacts === []) {
            return $qb;
        }

        return $qb
            ->andWhere('anomaly.impact IN (:impacts)')
            ->setParameter('impacts', $impacts);
    }

    /**
     * The anomaly status is derived, never stored: each requested status becomes
     * a SQL condition, and the conditions are OR-ed together.
     *
     *  - treated / ignored  -> the stored resolution
     *  - acknowledged       -> unresolved, with at least one non-cancelled task
     *  - reported           -> unresolved, without any non-cancelled task
     *
     * @param string|array<string> $status Single status or array of statuses (multi-select)
     */
    public function filterByStatus(QueryBuilder $qb, string|array $status): QueryBuilder
    {
        $statuses = array_values(array_filter(
            array_map(
                static fn ($value) => $value instanceof AnomalyStatus ? $value->value : (string) $value,
                is_array($status) ? $status : [$status]
            ),
            static fn (string $value): bool => AnomalyStatus::tryFrom($value) !== null
        ));

        if ($statuses === []) {
            return $qb;
        }

        $conditions = [];
        $resolutions = [];

        if (in_array(AnomalyStatus::TREATED->value, $statuses, true)) {
            $resolutions[] = AnomalyResolution::TREATED;
        }

        if (in_array(AnomalyStatus::IGNORED->value, $statuses, true)) {
            $resolutions[] = AnomalyResolution::IGNORED;
        }

        if ($resolutions !== []) {
            $conditions[] = 'anomaly.resolution IN (:resolutions)';
            $qb->setParameter('resolutions', $resolutions);
        }

        $wantsAcknowledged = in_array(AnomalyStatus::ACKNOWLEDGED->value, $statuses, true);
        $wantsReported = in_array(AnomalyStatus::REPORTED->value, $statuses, true);

        if ($wantsAcknowledged && $wantsReported) {
            // Together they cover every unresolved anomaly, so the task subquery
            // is pointless — and injecting it twice would clash on its alias.
            $conditions[] = 'anomaly.resolution IS NULL';
        } elseif ($wantsAcknowledged || $wantsReported) {
            $subQuery = $this->createQueryBuilder('anomaly_with_task')
                ->select('1')
                ->join('anomaly_with_task.tasks', 'linked_task')
                ->where('linked_task.status != :cancelledTaskStatus')
                ->andWhere('anomaly_with_task.id = anomaly.id')
                ->getDQL();

            $qb->setParameter('cancelledTaskStatus', 'cancelled');

            $conditions[] = $wantsAcknowledged
                ? '(anomaly.resolution IS NULL AND EXISTS (' . $subQuery . '))'
                : '(anomaly.resolution IS NULL AND NOT EXISTS (' . $subQuery . '))';
        }

        if ($conditions !== []) {
            $qb->andWhere(implode(' OR ', $conditions));
        }

        return $qb;
    }

    /**
     * Unresolved anomalies only — used for the equipment page and the counters.
     */
    public function filterByUnresolved(QueryBuilder $qb): QueryBuilder
    {
        return $qb->andWhere('anomaly.resolution IS NULL');
    }

    /**
     * Grounding anomalies first, then most recently declared.
     */
    public function orderByRelevance(QueryBuilder $qb): QueryBuilder
    {
        return $qb
            ->addOrderBy('CASE WHEN anomaly.impact = :groundedImpact AND anomaly.resolution IS NULL THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('CASE WHEN anomaly.resolution IS NULL THEN 0 ELSE 1 END', 'ASC')
            ->addOrderBy('anomaly.reportedAt', 'DESC')
            ->addOrderBy('anomaly.createdAt', 'DESC')
            ->setParameter('groundedImpact', AnomalyImpact::GROUNDED);
    }

    public function queryByEquipment(Equipment $equipment): QueryBuilder
    {
        return $this->filterByEquipment($this->queryAll(), $equipment);
    }

    public function countUnresolvedByEquipment(Equipment $equipment): int
    {
        return (int) $this->createQueryBuilder('anomaly')
            ->select('COUNT(anomaly.id)')
            ->where('anomaly.equipment = :equipment')
            ->andWhere('anomaly.resolution IS NULL')
            ->setParameter('equipment', $equipment)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUnresolvedByClub(Club $club): int
    {
        return (int) $this->createQueryBuilder('anomaly')
            ->select('COUNT(anomaly.id)')
            ->where('anomaly.club = :club')
            ->andWhere('anomaly.resolution IS NULL')
            ->setParameter('club', $club)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
