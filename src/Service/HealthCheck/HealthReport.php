<?php

namespace App\Service\HealthCheck;

final readonly class HealthReport
{
    /** @param HealthCheckItem[] $items */
    public function __construct(public array $items)
    {
    }

    public function total(): int
    {
        return count($this->items);
    }

    public function doneCount(): int
    {
        return count($this->done());
    }

    public function isComplete(): bool
    {
        return $this->todo() === [];
    }

    public function hasEssentialTodo(): bool
    {
        foreach ($this->todo() as $item) {
            if ($item->essential) {
                return true;
            }
        }

        return false;
    }

    /** @return HealthCheckItem[] */
    public function todo(): array
    {
        return array_values(array_filter($this->items, static fn (HealthCheckItem $item): bool => !$item->done));
    }

    /** @return HealthCheckItem[] */
    public function done(): array
    {
        return array_values(array_filter($this->items, static fn (HealthCheckItem $item): bool => $item->done));
    }
}
