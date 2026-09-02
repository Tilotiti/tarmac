<?php

namespace App\Repository;

use App\Entity\Aerodrome;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Aerodrome>
 */
class AerodromeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Aerodrome::class);
    }

    /**
     * @return Aerodrome[]
     */
    public function search(string $query, int $limit = 10): array
    {
        $needle = Aerodrome::normalizeForSearch($query);
        if ($needle === '') {
            return [];
        }

        return $this->createQueryBuilder('aerodrome')
            ->andWhere('aerodrome.searchIndex LIKE :needle')
            ->setParameter('needle', '%' . addcslashes($needle, '%_') . '%')
            ->orderBy('aerodrome.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneBySourceId(int $sourceId): ?Aerodrome
    {
        return $this->findOneBy(['sourceId' => $sourceId]);
    }
}
