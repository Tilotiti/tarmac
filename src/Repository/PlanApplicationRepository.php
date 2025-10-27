<?php

namespace App\Repository;

use App\Entity\Equipment;
use App\Entity\Enum\EquipmentType;
use App\Entity\Plan;
use App\Entity\PlanApplication;
use App\Service\ClubResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanApplication>
 */
class PlanApplicationRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClubResolver $clubResolver
    ) {
        parent::__construct($registry, PlanApplication::class);
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

        return $this->createQueryBuilder('planApplication')
            ->join('planApplication.plan', 'plan')
            ->where('plan.club = :club')
            ->setParameter('club', $club);
    }

    public function filterByPlan(QueryBuilder $qb, Plan $plan): QueryBuilder
    {
        return $qb
            ->andWhere('planApplication.plan = :plan')
            ->setParameter('plan', $plan);
    }

    public function filterByEquipment(QueryBuilder $qb, Equipment $equipment): QueryBuilder
    {
        return $qb
            ->andWhere('planApplication.equipment = :equipment')
            ->setParameter('equipment', $equipment);
    }

    public function filterByEquipmentType(QueryBuilder $qb, EquipmentType $type): QueryBuilder
    {
        return $qb
            ->join('planApplication.equipment', 'equipment')
            ->andWhere('equipment.type = :equipmentType')
            ->setParameter('equipmentType', $type);
    }

    public function filterByCancelled(QueryBuilder $qb, bool $cancelled): QueryBuilder
    {
        if ($cancelled) {
            return $qb->andWhere('planApplication.cancelledBy IS NOT NULL');
        }
        
        return $qb->andWhere('planApplication.cancelledBy IS NULL');
    }

    public function filterByDueDate(QueryBuilder $qb, string $filter): QueryBuilder
    {
        $now = new \DateTimeImmutable();
        
        if ($filter === 'end_of_week') {
            $endOfWeek = $now->modify('Sunday this week')->setTime(23, 59, 59);
            return $qb
                ->andWhere('planApplication.dueAt <= :endOfWeek')
                ->setParameter('endOfWeek', $endOfWeek);
        }
        
        if ($filter === 'end_of_month') {
            $endOfMonth = $now->modify('last day of this month')->setTime(23, 59, 59);
            return $qb
                ->andWhere('planApplication.dueAt <= :endOfMonth')
                ->setParameter('endOfMonth', $endOfMonth);
        }
        
        // 'all' - no filter
        return $qb;
    }

    public function orderByDueDate(QueryBuilder $qb, string $direction = 'ASC'): QueryBuilder
    {
        return $qb
            ->addOrderBy('planApplication.dueAt', $direction)
            ->addOrderBy('planApplication.appliedAt', 'DESC');
    }
}

