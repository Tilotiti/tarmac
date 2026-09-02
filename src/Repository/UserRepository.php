<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function queryAll(): QueryBuilder
    {
        $dql = $this->createQueryBuilder('user');

        $dql->orderBy('user.lastname', 'ASC');
        $dql->addOrderBy('user.firstname', 'ASC');

        return $dql;
    }

    public function queryByFilters(?array $filters = []): QueryBuilder
    {
        $dql = $this->queryAll();

        if (!empty($filters['search'])) {
            $dql->andWhere('LOWER(user.firstname) LIKE LOWER(:search) OR LOWER(user.lastname) LIKE LOWER(:search) OR LOWER(user.email) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $dql;
    }

    public function findOneByInvitationToken(string $token): ?User
    {
        return $this->createQueryBuilder('user')
            ->andWhere('user.invitationToken = :token')
            ->andWhere('user.password IS NULL')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * La colonne roles est un JSON : on filtre en PHP sur les utilisateurs actifs,
     * ce qui reste bon marché à l'échelle de Tarmac (quelques centaines de comptes).
     *
     * @return User[]
     */
    public function findAdmins(): array
    {
        return array_values(array_filter(
            $this->findBy(['active' => true]),
            static fn (User $user): bool => $user->isAdmin()
        ));
    }
}





