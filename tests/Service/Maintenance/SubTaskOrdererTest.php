<?php

namespace App\Tests\Service\Maintenance;

use App\Entity\SubTask;
use App\Entity\Task;
use App\Service\Maintenance\SubTaskOrderer;
use PHPUnit\Framework\TestCase;

class SubTaskOrdererTest extends TestCase
{
    private SubTaskOrderer $orderer;

    protected function setUp(): void
    {
        $this->orderer = new SubTaskOrderer();
    }

    /**
     * Construit une tâche dont les sous-tâches ont pour id 1..n et pour position 0..n-1.
     *
     * @param string[] $titles
     *
     * @return array{0: Task, 1: array<string, SubTask>} la tâche et ses sous-tâches indexées par titre
     */
    private function createTask(array $titles): array
    {
        $task = new Task();
        $subTasks = [];

        foreach (array_values($titles) as $index => $title) {
            $subTask = new SubTask();
            $subTask->setTitle($title);
            $subTask->setPosition($index);
            $this->setId($subTask, $index + 1);

            $task->addSubTask($subTask);
            $subTasks[$title] = $subTask;
        }

        return [$task, $subTasks];
    }

    private function setId(SubTask $subTask, int $id): void
    {
        $property = new \ReflectionProperty(SubTask::class, 'id');
        $property->setValue($subTask, $id);
    }

    /**
     * @return string[] les titres des sous-tâches triés par position
     */
    private function currentOrder(Task $task): array
    {
        $subTasks = $task->getSubTasks()->toArray();
        usort($subTasks, static fn (SubTask $a, SubTask $b) => $a->getPosition() <=> $b->getPosition());

        return array_map(static fn (SubTask $subTask) => $subTask->getTitle(), $subTasks);
    }

    private function positions(Task $task): array
    {
        $positions = [];
        foreach ($task->getSubTasks() as $subTask) {
            $positions[$subTask->getTitle()] = $subTask->getPosition();
        }

        return $positions;
    }

    public function testNormalizeRenumbersSparseAndDuplicatePositions(): void
    {
        [$task, $subTasks] = $this->createTask(['A', 'B', 'C']);
        $subTasks['A']->setPosition(5);
        $subTasks['B']->setPosition(5);
        $subTasks['C']->setPosition(40);

        $this->orderer->normalize($task);

        // A et B partagent la position 5 : l'id départage, A (id 1) reste devant B (id 2).
        $this->assertSame(['A' => 0, 'B' => 1, 'C' => 2], $this->positions($task));
    }

    public function testReorderReversesTheWholeList(): void
    {
        [$task, $subTasks] = $this->createTask(['A', 'B', 'C', 'D']);

        $changed = $this->orderer->reorder($task, [
            $subTasks['D']->getId(),
            $subTasks['C']->getId(),
            $subTasks['B']->getId(),
            $subTasks['A']->getId(),
        ]);

        $this->assertSame(['D', 'C', 'B', 'A'], $this->currentOrder($task));
        $this->assertSame(4, $changed);
    }

    public function testReorderKeepsHiddenSubTasksInTheirAbsoluteSlots(): void
    {
        // Liste complète : A, hidden1, B, hidden2, C
        // L'écran est filtré et n'affiche que A, B, C ; l'utilisateur les inverse en C, B, A.
        [$task, $subTasks] = $this->createTask(['A', 'hidden1', 'B', 'hidden2', 'C']);

        $this->orderer->reorder($task, [
            $subTasks['C']->getId(),
            $subTasks['B']->getId(),
            $subTasks['A']->getId(),
        ]);

        // Les sous-tâches masquées n'ont pas bougé de leur emplacement absolu (index 1 et 3),
        // et l'ordre relatif des visibles est bien celui demandé.
        $this->assertSame(['C', 'hidden1', 'B', 'hidden2', 'A'], $this->currentOrder($task));
    }

    public function testReorderIgnoresUnknownIds(): void
    {
        [$task, $subTasks] = $this->createTask(['A', 'B', 'C']);

        $this->orderer->reorder($task, [
            999,
            $subTasks['C']->getId(),
            $subTasks['A']->getId(),
            1234,
        ]);

        // Seuls A et C sont concernés : ils échangent leurs emplacements 0 et 2, B ne bouge pas.
        $this->assertSame(['C', 'B', 'A'], $this->currentOrder($task));
    }

    public function testReorderIgnoresDuplicateIds(): void
    {
        [$task, $subTasks] = $this->createTask(['A', 'B', 'C']);

        $this->orderer->reorder($task, [
            $subTasks['C']->getId(),
            $subTasks['C']->getId(),
            $subTasks['A']->getId(),
        ]);

        $this->assertSame(['C', 'B', 'A'], $this->currentOrder($task));
    }

    public function testReorderWithFewerThanTwoKnownIdsChangesNothing(): void
    {
        [$task, $subTasks] = $this->createTask(['A', 'B', 'C']);

        $changed = $this->orderer->reorder($task, [$subTasks['B']->getId()]);

        $this->assertSame(0, $changed);
        $this->assertSame(['A', 'B', 'C'], $this->currentOrder($task));
    }

    public function testReorderOnEmptyTaskIsANoop(): void
    {
        $task = new Task();

        $this->assertSame(0, $this->orderer->reorder($task, [1, 2]));
    }

    public function testReorderReturnsZeroWhenOrderIsUnchanged(): void
    {
        [$task, $subTasks] = $this->createTask(['A', 'B', 'C']);

        $changed = $this->orderer->reorder($task, [
            $subTasks['A']->getId(),
            $subTasks['B']->getId(),
            $subTasks['C']->getId(),
        ]);

        $this->assertSame(0, $changed);
        $this->assertSame(['A', 'B', 'C'], $this->currentOrder($task));
    }

    public function testInsertDefaultsToTheEnd(): void
    {
        [$task, ] = $this->createTask(['A', 'B']);
        $new = new SubTask();
        $new->setTitle('N');
        $task->addSubTask($new);

        $this->orderer->insert($task, $new);

        $this->assertSame(['A', 'B', 'N'], $this->currentOrder($task));
    }

    public function testInsertAtStart(): void
    {
        [$task, ] = $this->createTask(['A', 'B']);
        $new = new SubTask();
        $new->setTitle('N');
        $task->addSubTask($new);

        $this->orderer->insert($task, $new, null, true);

        $this->assertSame(['N', 'A', 'B'], $this->currentOrder($task));
        $this->assertSame(['A' => 1, 'B' => 2, 'N' => 0], $this->positions($task));
    }

    public function testInsertAfterAGivenSubTask(): void
    {
        [$task, $subTasks] = $this->createTask(['A', 'B', 'C']);
        $new = new SubTask();
        $new->setTitle('N');
        $task->addSubTask($new);

        $this->orderer->insert($task, $new, $subTasks['A']);

        $this->assertSame(['A', 'N', 'B', 'C'], $this->currentOrder($task));
    }

    public function testInsertMovesASubTaskAlreadyInTheTask(): void
    {
        [$task, $subTasks] = $this->createTask(['A', 'B', 'C']);

        $this->orderer->insert($task, $subTasks['C'], null, true);

        $this->assertSame(['C', 'A', 'B'], $this->currentOrder($task));
    }

    public function testInsertAfterASubTaskFromAnotherTaskFallsBackToTheEnd(): void
    {
        [$task, ] = $this->createTask(['A', 'B']);
        [, $others] = $this->createTask(['X', 'Y']);

        $new = new SubTask();
        $new->setTitle('N');
        $task->addSubTask($new);

        $this->orderer->insert($task, $new, $others['X']);

        $this->assertSame(['A', 'B', 'N'], $this->currentOrder($task));
    }
}
