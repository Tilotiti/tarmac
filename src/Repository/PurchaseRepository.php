<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\Purchase;
use App\Entity\Enum\PurchaseStatus;
use App\Entity\User;
use App\Service\ClubResolver;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Purchase>
 */
class PurchaseRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly ClubResolver $clubResolver
    ) {
        parent::__construct($registry, Purchase::class);
    }

    /**
     * Base query with club context automatically applied via ClubResolver
     */
    public function queryAll(): QueryBuilder
    {
        $club = $this->clubResolver->getClub();

        if (!$club) {
            throw new \RuntimeException('Club context is required for queryAll()');
        }

        return $this->createQueryBuilder('purchase')
            ->where('purchase.club = :club')
            ->setParameter('club', $club);
    }

    /**
     * Query purchases with filters applied
     */
    public function queryByFilters(?array $filters = []): QueryBuilder
    {
        $qb = $this->queryAll();

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $qb = $this->filterByStatuses($qb, $filters['status']);
            } else {
                $qb = $this->filterByStatus($qb, $filters['status']);
            }
        }

        if (!empty($filters['ownership'])) {
            $qb = $this->filterByOwnership($qb, $filters['ownership'], $filters['user'] ?? null);
        }

        return $qb;
    }

    public function filterByStatus(QueryBuilder $qb, string $status): QueryBuilder
    {
        if ($status === '' || $status === 'all') {
            return $qb;
        }

        try {
            $statusEnum = PurchaseStatus::from($status);
            return $qb
                ->andWhere('purchase.status = :status')
                ->setParameter('status', $statusEnum);
        } catch (\ValueError $e) {
            // Invalid status value, return query without filter
            return $qb;
        }
    }

    public function filterByStatuses(QueryBuilder $qb, array $statuses): QueryBuilder
    {
        if (empty($statuses)) {
            return $qb;
        }

        $statusEnums = [];
        foreach ($statuses as $status) {
            if ($status === '' || $status === 'all') {
                continue;
            }
            try {
                $statusEnums[] = PurchaseStatus::from($status);
            } catch (\ValueError $e) {
                // Invalid status value, skip it
                continue;
            }
        }

        if (empty($statusEnums)) {
            return $qb;
        }

        return $qb
            ->andWhere('purchase.status IN (:statuses)')
            ->setParameter('statuses', $statusEnums);
    }

    public function filterByOwnership(QueryBuilder $qb, string $ownership, ?User $user): QueryBuilder
    {
        if (!$user || $ownership === 'all' || $ownership === '') {
            return $qb;
        }

        if ($ownership === 'myRequests') {
            return $qb
                ->andWhere('purchase.createdBy = :user')
                ->setParameter('user', $user);
        }

        if ($ownership === 'myPurchases') {
            return $qb
                ->andWhere('purchase.purchasedBy = :user')
                ->setParameter('user', $user);
        }

        return $qb;
    }

    public function filterByCreatedBy(QueryBuilder $qb, User $user): QueryBuilder
    {
        return $qb
            ->andWhere('purchase.createdBy = :user')
            ->setParameter('user', $user);
    }

    public function filterByPurchasedBy(QueryBuilder $qb, User $user): QueryBuilder
    {
        return $qb
            ->andWhere('purchase.purchasedBy = :user')
            ->setParameter('user', $user);
    }

    public function filterByOwnPurchases(QueryBuilder $qb, User $user): QueryBuilder
    {
        return $qb
            ->andWhere('purchase.createdBy = :user OR purchase.purchasedBy = :user')
            ->setParameter('user', $user);
    }

    public function orderByCreatedAt(QueryBuilder $qb, string $direction = 'DESC'): QueryBuilder
    {
        return $qb->addOrderBy('purchase.createdAt', $direction);
    }

    public function countPurchasesWaitingDelivery(Club $club): int
    {
        return (int) $this->createQueryBuilder('purchase')
            ->select('COUNT(purchase.id)')
            ->where('purchase.club = :club')
            ->andWhere('purchase.status = :status')
            ->setParameter('club', $club)
            ->setParameter('status', PurchaseStatus::PURCHASED)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

