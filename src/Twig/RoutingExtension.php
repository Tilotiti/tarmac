<?php

namespace App\Twig;

use App\Service\SubdomainService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RoutingExtension extends AbstractExtension
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SubdomainService $subdomainService,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('path', [$this, 'getPath']),
            new TwigFunction('url', [$this, 'getUrl']),
        ];
    }

    /**
     * Custom path function that automatically injects subdomain for club routes
     */
    public function getPath(string $name, array $parameters = [], bool $relative = false): string
    {
        // Auto-inject subdomain for club routes if we're on a club subdomain
        $parameters = $this->injectSubdomainIfNeeded($name, $parameters);

        return $this->urlGenerator->generate($name, $parameters, $relative ? UrlGeneratorInterface::RELATIVE_PATH : UrlGeneratorInterface::ABSOLUTE_PATH);
    }

    /**
     * Custom url function that automatically injects subdomain for club routes
     */
    public function getUrl(string $name, array $parameters = [], bool $schemeRelative = false): string
    {
        // Auto-inject subdomain for club routes if we're on a club subdomain
        $parameters = $this->injectSubdomainIfNeeded($name, $parameters);

        return $this->urlGenerator->generate($name, $parameters, $schemeRelative ? UrlGeneratorInterface::NETWORK_PATH : UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Inject subdomain parameter if needed for club routes
     */
    private function injectSubdomainIfNeeded(string $routeName, array $parameters): array
    {
        // Only inject if:
        // 1. Route starts with 'club_' (it's a club route)
        // 2. Subdomain parameter is not already set
        // 3. We're currently on a club subdomain
        if (str_starts_with($routeName, 'club_') && !isset($parameters['subdomain'])) {
            $subdomain = $this->subdomainService->getClubSubdomain();
            if ($subdomain) {
                $parameters['subdomain'] = $subdomain;
            }
        }

        return $parameters;
    }
}

