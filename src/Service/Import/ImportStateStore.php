<?php

namespace App\Service\Import;

use App\Entity\Club;
use Symfony\Component\HttpFoundation\RequestStack;

final class ImportStateStore
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function get(Club $club, string $definition): ?ImportState
    {
        $data = $this->requestStack->getSession()->get($this->key($club, $definition));

        return is_array($data) ? ImportState::fromArray($data) : null;
    }

    public function save(Club $club, ImportState $state): void
    {
        $this->requestStack->getSession()->set($this->key($club, $state->definition), $state->toArray());
    }

    public function clear(Club $club, string $definition): void
    {
        $this->requestStack->getSession()->remove($this->key($club, $definition));
    }

    private function key(Club $club, string $definition): string
    {
        return sprintf('import.%d.%s', $club->getId(), $definition);
    }
}
