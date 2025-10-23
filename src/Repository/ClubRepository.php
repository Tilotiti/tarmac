<?php

namespace App\Repository;

use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Club>
 */
class ClubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Club::class);
    }

    /**
     * Find a club by its subdomain
     */
    public function findBySubdomain(string $subdomain): ?Club
    {
        return $this->findOneBy(['subdomain' => $subdomain, 'active' => true]);
    }

    /**
     * Find all active clubs
     *
     * @return Club[]
     */
    public function findAllActive(): array
    {
        return $this->findBy(['active' => true], ['name' => 'ASC']);
    }

    /**
     * Find clubs with filters
     */
    public function queryByFilters(array $filters = []): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('c');

        if (!empty($filters['name'])) {
            $qb->andWhere('c.name LIKE :name')
                ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['subdomain'])) {
            $qb->andWhere('c.subdomain LIKE :subdomain')
                ->setParameter('subdomain', '%' . $filters['subdomain'] . '%');
        }

        if (isset($filters['active']) && $filters['active'] !== '') {
            $qb->andWhere('c.active = :active')
                ->setParameter('active', $filters['active']);
        }

        return $qb->orderBy('c.name', 'ASC');
    }
}

