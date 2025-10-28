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
        return $this->createQueryBuilder('invitation')
            ->where('invitation.token = :token')
            ->andWhere('invitation.expiresAt > :now')
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
        return $this->createQueryBuilder('invitation')
            ->where('invitation.email = :email')
            ->andWhere('invitation.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a pending invitation by email and club
     */
    public function findPendingByEmailAndClub(string $email, Club $club): ?Invitation
    {
        return $this->createQueryBuilder('invitation')
            ->where('invitation.email = :email')
            ->andWhere('invitation.club = :club')
            ->andWhere('invitation.expiresAt > :now')
            ->setParameter('email', $email)
            ->setParameter('club', $club)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Query pending invitations by club with filters
     */
    public function queryPendingByClub(Club $club, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('invitation')
            ->where('invitation.club = :club')
            ->andWhere('invitation.expiresAt > :now')
            ->setParameter('club', $club)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('invitation.createdAt', 'DESC');

        // Search filter (email or name)
        if (!empty($filters['search'])) {
            $qb->andWhere('LOWER(invitation.email) LIKE :search OR LOWER(invitation.firstname) LIKE :search OR LOWER(invitation.lastname) LIKE :search')
                ->setParameter('search', '%' . strtolower($filters['search']) . '%');
        }

        // Role filter
        if (!empty($filters['role'])) {
            switch ($filters['role']) {
                case 'manager':
                    $qb->andWhere('invitation.isManager = true');
                    break;
                case 'inspector':
                    $qb->andWhere('invitation.isInspector = true');
                    break;
                case 'member':
                    $qb->andWhere('invitation.isManager = false AND invitation.isInspector = false');
                    break;
            }
        }

        return $qb;
    }
}

