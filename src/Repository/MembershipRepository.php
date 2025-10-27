<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Membership;
use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Membership>
 */
class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    /**
     * Find a specific user-club relationship
     */
    public function findByUserAndClub(int|User $user, int|Club $club): ?Membership
    {
        return $this->findOneBy(['user' => $user, 'club' => $club]);
    }

    /**
     * Find a membership by user email and club
     */
    public function findByEmailAndClub(string $email, Club $club): ?Membership
    {
        return $this->createQueryBuilder('m')
            ->innerJoin('m.user', 'u')
            ->where('LOWER(u.email) = LOWER(:email)')
            ->andWhere('m.club = :club')
            ->setParameter('email', $email)
            ->setParameter('club', $club)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Query memberships by club with filters
     */
    public function queryByClubAndFilters(Club $club, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.user', 'u')
            ->where('m.club = :club')
            ->setParameter('club', $club)
            ->orderBy('u.lastname', 'ASC')
            ->addOrderBy('u.firstname', 'ASC');

        // Search filter (name or email)
        if (!empty($filters['search'])) {
            $qb->andWhere('LOWER(u.firstname) LIKE :search OR LOWER(u.lastname) LIKE :search OR LOWER(u.email) LIKE :search')
                ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }

        // Role filter
        if (!empty($filters['role'])) {
            switch ($filters['role']) {
                case 'manager':
                    $qb->andWhere('m.isManager = true');
                    break;
                case 'inspector':
                    $qb->andWhere('m.isInspector = true');
                    break;
                case 'member':
                    $qb->andWhere('m.isManager = false AND m.isInspector = false');
                    break;
            }
        }

        return $qb;
    }
}

