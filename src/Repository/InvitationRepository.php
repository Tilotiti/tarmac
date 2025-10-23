<?php

namespace App\Repository;

use App\Entity\Invitation;
use App\Entity\Club;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    /**
     * Find an invitation by token
     */
    public function findOneByToken(string $token): ?Invitation
    {
        return $this->findOneBy(['token' => $token]);
    }

    /**
     * Find a valid (not expired) invitation by token
     */
    public function findValidByToken(string $token): ?Invitation
    {
        return $this->createQueryBuilder('i')
            ->where('i.token = :token')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find pending invitations by email
     */
    public function findPendingByEmail(string $email): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.email = :email')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Query pending invitations by club with filters
     */
    public function findPendingByClub(Club $club, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.club = :club')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('club', $club)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('i.createdAt', 'DESC');

        // Search filter (email or name)
        if (!empty($filters['search'])) {
            $qb->andWhere('LOWER(i.email) LIKE :search OR LOWER(i.firstname) LIKE :search OR LOWER(i.lastname) LIKE :search')
                ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }

        // Role filter
        if (!empty($filters['role'])) {
            switch ($filters['role']) {
                case 'manager':
                    $qb->andWhere('i.isManager = true');
                    break;
                case 'inspector':
                    $qb->andWhere('i.isInspector = true');
                    break;
                case 'member':
                    $qb->andWhere('i.isManager = false AND i.isInspector = false');
                    break;
            }
        }

        return $qb;
    }
}

