<?php

namespace App\Service;

use App\Entity\Club;
use App\Repository\ClubRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClubResolver
{
    private const CLUB_ATTRIBUTE = '_club';

    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly SubdomainService $subdomainService
    ) {
    }

    /**
     * Resolve the club from the current request
     * 
     * @throws NotFoundHttpException if club not found or inactive
     */
    public function resolve(): ?Club
    {
        $request = $this->subdomainService->getCurrentRequest();
        if (!$request) {
            return null;
        }

        // Check if already cached in request attributes
        if ($request->attributes->has(self::CLUB_ATTRIBUTE)) {
            return $request->attributes->get(self::CLUB_ATTRIBUTE);
        }

        // Only resolve for club subdomains
        if (!$this->subdomainService->isClubSubdomain()) {
            return null;
        }

        $subdomain = $this->subdomainService->getClubSubdomain();
        if (!$subdomain) {
            throw new NotFoundHttpException('No club subdomain found');
        }

        $club = $this->clubRepository->findBySubdomain($subdomain);

        if (!$club) {
            throw new NotFoundHttpException(sprintf('Club with subdomain "%s" not found', $subdomain));
        }

        // Cache in request attributes
        $request->attributes->set(self::CLUB_ATTRIBUTE, $club);

        return $club;
    }

    /**
     * Get the resolved club from request attributes
     */
    public function getClub(): ?Club
    {
        $request = $this->subdomainService->getCurrentRequest();
        if (!$request) {
            return null;
        }

        return $request->attributes->get(self::CLUB_ATTRIBUTE);
    }

    /**
     * Get the current request
     */
    private function getCurrentRequest()
    {
        return $this->subdomainService->getCurrentRequest();
    }

    /**
     * Check if the current request is for a club
     */
    public function isClubRequest(): bool
    {
        return $this->subdomainService->isClubSubdomain();
    }
}

