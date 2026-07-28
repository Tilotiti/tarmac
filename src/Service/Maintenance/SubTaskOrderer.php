<?php

namespace App\Service\Maintenance;

use App\Entity\SubTask;
use App\Entity\Task;

/**
 * Seul endroit du code qui écrit SubTask::$position.
 *
 * L'ordre des sous-tâches d'une tâche est porté exclusivement par `position`,
 * renumérotée densément (0..n-1) après chaque opération. `planPosition` ne
 * conserve que l'origine du plan d'entretien et n'intervient plus dans le tri.
 *
 * Le service mute les entités sans jamais flusher : c'est à l'appelant de le faire.
 */
class SubTaskOrderer
{
    /**
     * Renumérote les sous-tâches de 0 à n-1 en conservant l'ordre courant.
     */
    public function normalize(Task $task): void
    {
        $this->applyPositions($this->orderedSubTasks($task));
    }

    /**
     * Place une sous-tâche à un emplacement précis dans sa tâche.
     *
     * Par défaut la sous-tâche est ajoutée à la fin. `$atStart` la place en tête,
     * `$after` juste derrière une sous-tâche existante (les deux sont exclusifs,
     * `$atStart` primant). Une sous-tâche déjà présente dans la tâche est déplacée.
     */
    public function insert(Task $task, SubTask $subTask, ?SubTask $after = null, bool $atStart = false): void
    {
        $ordered = array_values(array_filter(
            $this->orderedSubTasks($task),
            static fn (SubTask $candidate) => $candidate !== $subTask
        ));

        if ($atStart) {
            $index = 0;
        } elseif ($after !== null && ($found = array_search($after, $ordered, true)) !== false) {
            $index = $found + 1;
        } else {
            $index = count($ordered);
        }

        array_splice($ordered, $index, 0, [$subTask]);

        $this->applyPositions($ordered);
    }

    /**
     * Réordonne les sous-tâches désignées par $orderedIds dans l'ordre reçu.
     *
     * $orderedIds peut ne couvrir qu'une partie de la tâche (liste filtrée à
     * l'écran) : seuls les emplacements déjà occupés par ces sous-tâches sont
     * réattribués, les sous-tâches absentes de la liste conservent leur
     * emplacement absolu. L'ordre relatif obtenu est donc exactement celui reçu.
     *
     * Les identifiants inconnus ou n'appartenant pas à la tâche sont ignorés.
     *
     * @param int[] $orderedIds
     *
     * @return int nombre de sous-tâches ayant réellement changé d'emplacement
     */
    public function reorder(Task $task, array $orderedIds): int
    {
        $all = $this->orderedSubTasks($task);

        if ($all === []) {
            return 0;
        }

        $indexById = [];
        foreach ($all as $index => $subTask) {
            if ($subTask->getId() !== null) {
                $indexById[$subTask->getId()] = $index;
            }
        }

        /** @var int[] $slots */
        $slots = [];
        /** @var SubTask[] $moved */
        $moved = [];
        foreach (array_unique($orderedIds) as $id) {
            if (!isset($indexById[$id])) {
                continue;
            }

            $slots[] = $indexById[$id];
            $moved[] = $all[$indexById[$id]];
        }

        // Moins de deux sous-tâches concernées : aucun réagencement possible.
        if (count($moved) < 2) {
            $this->applyPositions($all);

            return 0;
        }

        sort($slots);

        $previous = $all;
        foreach ($slots as $rank => $slot) {
            $all[$slot] = $moved[$rank];
        }

        $this->applyPositions($all);

        $changed = 0;
        foreach ($slots as $slot) {
            if ($previous[$slot] !== $all[$slot]) {
                ++$changed;
            }
        }

        return $changed;
    }

    /**
     * @return SubTask[] liste indexée, triée selon l'ordre courant
     */
    private function orderedSubTasks(Task $task): array
    {
        $subTasks = $task->getSubTasks()->toArray();

        // La collection est déjà triée par Doctrine, mais elle peut contenir des
        // sous-tâches ajoutées en mémoire (pas encore persistées, donc sans id).
        usort($subTasks, static function (SubTask $a, SubTask $b): int {
            return $a->getPosition() <=> $b->getPosition()
                ?: ($a->getId() ?? PHP_INT_MAX) <=> ($b->getId() ?? PHP_INT_MAX);
        });

        return array_values($subTasks);
    }

    /**
     * @param SubTask[] $ordered
     */
    private function applyPositions(array $ordered): void
    {
        foreach (array_values($ordered) as $index => $subTask) {
            if ($subTask->getPosition() !== $index) {
                $subTask->setPosition($index);
            }
        }
    }
}
