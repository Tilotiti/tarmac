<?php

namespace App\Service\HealthCheck;

final readonly class HealthCheckItem
{
    /**
     * @param string $key clé de traduction : healthCheck<Key>Title et healthCheck<Key>Why
     * @param array<string, mixed> $routeParams
     */
    public function __construct(
        public string $key,
        public bool $done,
        public string $route,
        public array $routeParams = [],
        public bool $essential = false,
    ) {
    }

    public function translationKey(string $suffix): string
    {
        return 'healthCheck' . ucfirst($this->key) . $suffix;
    }
}
