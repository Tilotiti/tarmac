<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\JoinRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JoinRequest>
 */
class JoinRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JoinRequest::class);
    }

    /**
     * @return JoinRequest[]
     */
    public function findOpenByClub(Club $club): array
    {
        return $this->createQueryBuilder('join_request')
            ->andWhere('join_request.club = :club')
            ->andWhere('join_request.handledAt IS NULL')
            ->setParameter('club', $club)
            ->orderBy('join_request.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOpenByUserAndClub(User $user, Club $club): ?JoinRequest
    {
        return $this->createQueryBuilder('join_request')
            ->andWhere('join_request.club = :club')
            ->andWhere('join_request.user = :user')
            ->andWhere('join_request.handledAt IS NULL')
            ->setParameter('club', $club)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countOpenByClub(Club $club): int
    {
        return (int) $this->createQueryBuilder('join_request')
            ->select('COUNT(join_request.id)')
            ->andWhere('join_request.club = :club')
            ->andWhere('join_request.handledAt IS NULL')
            ->setParameter('club', $club)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
