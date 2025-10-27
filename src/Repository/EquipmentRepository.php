<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\Equipment;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Service\ClubResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipment>
 */
class EquipmentRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClubResolver $clubResolver
    ) {
        parent::__construct($registry, Equipment::class);
    }

    /**
     * Base query for all equipments in the current club context
     */
    public function queryAll(): QueryBuilder
    {
        $dql = $this->createQueryBuilder('equipment');

        // Filter by current club context if available
        $club = $this->clubResolver->getClub();
        if ($club) {
            $dql = $this->filterByClub($dql, $club);
        }

        $dql->orderBy('equipment.name', 'ASC');

        return $dql;
    }

    /**
     * Filter by club
     */
    public function filterByClub(QueryBuilder $dql, Club $club): QueryBuilder
    {
        $dql->andWhere('equipment.club = :club')
            ->setParameter('club', $club);

        return $dql;
    }

    /**
     * Filter by active status
     */
    public function filterByActive(QueryBuilder $dql, bool $active): QueryBuilder
    {
        $dql->andWhere('equipment.active = :active')
            ->setParameter('active', $active);

        return $dql;
    }

    /**
     * Filter by search (name)
     */
    public function filterBySearch(QueryBuilder $dql, string $search): QueryBuilder
    {
        $dql->andWhere('LOWER(equipment.name) LIKE LOWER(:search)')
            ->setParameter('search', '%' . $search . '%');

        return $dql;
    }

    /**
     * Filter by equipment type
     */
    public function filterByType(QueryBuilder $dql, EquipmentType|string $type): QueryBuilder
    {
        if (is_string($type)) {
            $type = EquipmentType::from($type);
        }

        $dql->andWhere('equipment.type = :type')
            ->setParameter('type', $type);

        return $dql;
    }

    /**
     * Filter by owner type
     */
    public function filterByOwner(QueryBuilder $dql, EquipmentOwner|string $owner): QueryBuilder
    {
        if (is_string($owner)) {
            $owner = EquipmentOwner::from($owner);
        }

        $dql->andWhere('equipment.owner = :owner')
            ->setParameter('owner', $owner);

        return $dql;
    }

    /**
     * Query equipments with filters
     */
    public function queryByFilters(?array $filters = []): QueryBuilder
    {
        $dql = $this->queryAll();

        // Force club context if provided
        if (!empty($filters['club'])) {
            $dql = $this->filterByClub($dql, $filters['club']);
        }

        if (!empty($filters['search'])) {
            $dql = $this->filterBySearch($dql, $filters['search']);
        }

        if (!empty($filters['type'])) {
            $dql = $this->filterByType($dql, $filters['type']);
        }

        if (isset($filters['active']) && $filters['active'] !== '') {
            $dql = $this->filterByActive($dql, (bool) $filters['active']);
        }

        if (isset($filters['owner']) && $filters['owner'] !== '') {
            $dql = $this->filterByOwner($dql, $filters['owner']);
        }

        return $dql;
    }

}
