<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class SubdomainService
{
    private const WWW_SUBDOMAIN = 'www';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string $domain
    ) {
    }

    /**
     * Get the current subdomain from the request
     */
    public function getCurrentSubdomain(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return null;
        }

        $host = $request->getHost();
        return $this->extractSubdomain($host);
    }

    /**
     * Check if current request is on www subdomain
     */
    public function isWwwSubdomain(): bool
    {
        return $this->getCurrentSubdomain() === self::WWW_SUBDOMAIN;
    }


    /**
     * Check if current request is on a club subdomain
     */
    public function isClubSubdomain(): bool
    {
        $subdomain = $this->getCurrentSubdomain();
        return $subdomain !== null
            && $subdomain !== self::WWW_SUBDOMAIN;
    }

    /**
     * Get the club subdomain (returns null if not a club subdomain)
     */
    public function getClubSubdomain(): ?string
    {
        if (!$this->isClubSubdomain()) {
            return null;
        }

        return $this->getCurrentSubdomain();
    }


    /**
     * Generate URL for www subdomain
     */
    public function generateWwwUrl(string $path = '/', array $parameters = []): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $scheme = $request?->getScheme() ?? 'https';

        $url = sprintf('%s://www.%s%s', $scheme, $this->domain, $path);

        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        return $url;
    }

    /**
     * Generate URL for a club subdomain
     */
    public function generateClubUrl(string $subdomain, string $path = '/', array $parameters = []): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $scheme = $request?->getScheme() ?? 'https';

        $url = sprintf('%s://%s.%s%s', $scheme, $subdomain, $this->domain, $path);

        if (!empty($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        return $url;
    }

    /**
     * Extract subdomain from host
     */
    private function extractSubdomain(string $host): ?string
    {
        // Remove port if present
        $host = explode(':', $host)[0];

        // Check if host ends with the configured domain
        if (!str_ends_with($host, $this->domain)) {
            return null;
        }

        // Extract subdomain
        $subdomain = substr($host, 0, -(strlen($this->domain) + 1));

        // If no subdomain, it's the root domain
        if (empty($subdomain)) {
            return self::WWW_SUBDOMAIN;
        }

        return $subdomain;
    }

    /**
     * Get the configured domain
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Get the current request
     */
    public function getCurrentRequest()
    {
        return $this->requestStack->getCurrentRequest();
    }
}

