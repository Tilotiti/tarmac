<?php

namespace App\Tests\Repository;

use App\Entity\Anomaly;
use App\Entity\Club;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\AnomalyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The status filter rebuilds the derived status in SQL: it must follow the same
 * rule as Anomaly::getStatus(), active subtasks included.
 */
class AnomalyRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private Club $club;
    private User $user;
    private Equipment $equipment;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->user = new User();
        $this->user->setEmail('repository@example.test');
        $this->user->setFirstname('Test');
        $this->user->setLastname('Repository');
        $this->user->setPassword('$2y$04$abcdefghijklmnopqrstuu');
        $this->entityManager->persist($this->user);

        $this->club = new Club();
        $this->club->setName('Planeurs de Beauce');
        $this->club->setSubdomain('beauce');
        $this->entityManager->persist($this->club);

        $this->equipment = (new Equipment())
            ->setName('F-CJKL')
            ->setType(EquipmentType::GLIDER)
            ->setOwner(EquipmentOwner::CLUB)
            ->setClub($this->club)
            ->setCreatedBy($this->user);
        $this->entityManager->persist($this->equipment);
    }

    private function createSubTask(string $status, string $taskStatus): SubTask
    {
        $task = new Task();
        $task->setClub($this->club);
        $task->setEquipment($this->equipment);
        $task->setTitle('Visite annuelle');
        $task->setStatus($taskStatus);
        $task->setCreatedBy($this->user);
        $this->entityManager->persist($task);

        $subTask = new SubTask();
        $subTask->setTitle('Contrôle des pneus');
        $subTask->setStatus($status);
        $task->addSubTask($subTask);
        $this->entityManager->persist($subTask);

        return $subTask;
    }

    private function createAnomaly(string $title, ?SubTask $subTask = null): Anomaly
    {
        $anomaly = new Anomaly();
        $anomaly->setClub($this->club);
        $anomaly->setEquipment($this->equipment);
        $anomaly->setTitle($title);
        $anomaly->setDescription('Constatée à la visite prévol.');
        $anomaly->setReportedBy($this->user);

        if ($subTask !== null) {
            $anomaly->addSubTask($subTask);
        }

        $this->entityManager->persist($anomaly);

        return $anomaly;
    }

    /**
     * @param Anomaly[] $anomalies
     *
     * @return int[]
     */
    private function idsFor(string $status, array $anomalies): array
    {
        /** @var AnomalyRepository $repository */
        $repository = $this->entityManager->getRepository(Anomaly::class);

        $qb = $repository->createQueryBuilder('anomaly')
            ->where('anomaly.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (Anomaly $anomaly) => $anomaly->getId(), $anomalies));

        $ids = array_map(
            static fn (Anomaly $anomaly) => $anomaly->getId(),
            $repository->filterByStatus($qb, $status)->getQuery()->getResult()
        );
        sort($ids);

        return $ids;
    }

    public function testAcknowledgedAndReportedFollowTheActiveSubTasks(): void
    {
        $linked = $this->createAnomaly('Pneu usé', $this->createSubTask('open', 'open'));
        $unlinked = $this->createAnomaly('Compas perdu');
        // Sous-tâche close d'une tâche annulée : elle ne compte plus
        $linkedToCancelledTask = $this->createAnomaly('Patin', $this->createSubTask('closed', 'cancelled'));
        $this->entityManager->flush();

        $all = [$linked, $unlinked, $linkedToCancelledTask];

        $this->assertSame([$linked->getId()], $this->idsFor('acknowledged', $all));
        $this->assertEqualsCanonicalizing(
            [$unlinked->getId(), $linkedToCancelledTask->getId()],
            $this->idsFor('reported', $all)
        );
    }
}
