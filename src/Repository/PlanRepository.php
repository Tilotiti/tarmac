<?php

namespace App\Repository;

use App\Entity\Enum\EquipmentType;
use App\Entity\Plan;
use App\Service\ClubResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plan>
 */
class PlanRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClubResolver $clubResolver
    ) {
        parent::__construct($registry, Plan::class);
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

        return $this->createQueryBuilder('plan')
            ->where('plan.club = :club')
            ->setParameter('club', $club);
    }

    /**
     * Query plans with filters applied
     */
    public function queryByFilters(?array $filters = []): QueryBuilder
    {
        $qb = $this->queryAll();

        if (!empty($filters['search'])) {
            $qb = $this->filterBySearch($qb, $filters['search']);
        }

        if (!empty($filters['equipmentType'])) {
            $qb = $this->filterByEquipmentType($qb, EquipmentType::from($filters['equipmentType']));
        }

        return $qb;
    }

    public function filterByEquipmentType(QueryBuilder $qb, EquipmentType $type): QueryBuilder
    {
        return $qb
            ->andWhere('plan.equipmentType = :equipmentType')
            ->setParameter('equipmentType', $type);
    }

    public function filterBySearch(QueryBuilder $qb, string $search): QueryBuilder
    {
        return $qb
            ->andWhere('LOWER(plan.name) LIKE LOWER(:search) OR LOWER(plan.description) LIKE LOWER(:search)')
            ->setParameter('search', '%' . $search . '%');
    }

    public function orderByName(QueryBuilder $qb): QueryBuilder
    {
        return $qb->addOrderBy('plan.name', 'ASC');
    }
}

