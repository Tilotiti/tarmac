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
        return $this->createQueryBuilder('membership')
            ->innerJoin('membership.user', 'user')
            ->where('LOWER(user.email) = LOWER(:email)')
            ->andWhere('membership.club = :club')
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
        $qb = $this->createQueryBuilder('membership')
            ->leftJoin('membership.user', 'user')
            ->where('membership.club = :club')
            ->setParameter('club', $club)
            ->orderBy('user.lastname', 'ASC')
            ->addOrderBy('user.firstname', 'ASC');

        // Search filter (name or email)
        if (!empty($filters['search'])) {
            $qb->andWhere('LOWER(user.firstname) LIKE :search OR LOWER(user.lastname) LIKE :search OR LOWER(user.email) LIKE :search')
                ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }

        // Role filter
        if (!empty($filters['role'])) {
            switch ($filters['role']) {
                case 'manager':
                    $qb->andWhere('membership.isManager = true');
                    break;
                case 'inspector':
                    $qb->andWhere('membership.isInspector = true');
                    break;
                case 'pilot':
                    $qb->andWhere('membership.isPilote = true');
                    break;
                case 'member':
                    $qb->andWhere('membership.isManager = false AND membership.isInspector = false AND membership.isPilote = false');
                    break;
            }
        }

        return $qb;
    }
}

