<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\Specialisation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Specialisation>
 */
class SpecialisationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Specialisation::class);
    }

    public function queryByClubAndFilters(Club $club, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.club = :club')
            ->setParameter('club', $club)
            ->orderBy('s.name', 'ASC');

        if (!empty($filters['search'])) {
            $qb->andWhere('LOWER(s.name) LIKE :search OR LOWER(s.description) LIKE :search')
                ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }

        return $qb;
    }

    /**
     * @return Specialisation[]
     */
    public function findByClub(Club $club): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.club = :club')
            ->setParameter('club', $club)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByClubAndName(Club $club, string $name): ?Specialisation
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.club = :club')
            ->andWhere('LOWER(s.name) = LOWER(:name)')
            ->setParameter('club', $club)
            ->setParameter('name', trim($name))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
