<?php

namespace App\Repository;

use App\Entity\Aerodrome;
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
        return $this->findOneBy([
            'subdomain' => mb_strtolower($subdomain, 'UTF-8'),
            'active' => true,
        ]);
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
        $qb = $this->createQueryBuilder('club');

        if (!empty($filters['name'])) {
            $qb->andWhere('club.name LIKE :name')
                ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['subdomain'])) {
            $qb->andWhere('club.subdomain LIKE :subdomain')
                ->setParameter('subdomain', '%' . $filters['subdomain'] . '%');
        }

        if (isset($filters['active']) && $filters['active'] !== '') {
            $qb->andWhere('club.active = :active')
                ->setParameter('active', $filters['active']);
        }

        if (($filters['aerodrome'] ?? '') === 'without') {
            $qb->andWhere('club.aerodrome IS NULL');
        } elseif (($filters['aerodrome'] ?? '') === 'with') {
            $qb->andWhere('club.aerodrome IS NOT NULL');
        }

        return $qb->orderBy('club.name', 'ASC');
    }

    /**
     * @return Club[]
     */
    public function findCreatedSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('club')
            ->andWhere('club.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('club.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Club[]
     */
    public function findByAerodrome(Aerodrome $aerodrome, ?Club $except = null): array
    {
        $queryBuilder = $this->createQueryBuilder('club')
            ->andWhere('club.aerodrome = :aerodrome')
            ->andWhere('club.active = true')
            ->setParameter('aerodrome', $aerodrome)
            ->orderBy('club.name', 'ASC');

        if ($except !== null && $except->getId() !== null) {
            $queryBuilder->andWhere('club.id != :except')->setParameter('except', $except->getId());
        }

        return $queryBuilder->getQuery()->getResult();
    }
}

