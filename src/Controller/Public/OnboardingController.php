<?php

namespace App\Controller\Public;

use App\Controller\ExtendedController;
use App\Repository\ClubRepository;
use App\Repository\JoinRequestRepository;
use App\Repository\UserRepository;
use App\Service\Onboarding\ClubDuplicateFinder;
use App\Service\Onboarding\ClubRegistrationService;
use App\Service\Onboarding\EmailVerificationService;
use App\Service\Onboarding\JoinRequestService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/creer-mon-club', host: 'www.%domain%')]
class OnboardingController extends ExtendedController
{
    use TargetPathTrait;

    public function __construct(
        SubdomainService $subdomainService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ClubRepository $clubRepository,
        private readonly JoinRequestRepository $joinRequestRepository,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailVerificationService $emailVerification,
        private readonly ClubDuplicateFinder $duplicateFinder,
        private readonly ClubRegistrationService $registration,
        private readonly JoinRequestService $joinRequestService,
        #[Autowire(service: 'limiter.onboarding_account')] private readonly RateLimiterFactory $accountLimiter,
        #[Autowire(service: 'limiter.onboarding_resend')] private readonly RateLimiterFactory $resendLimiter,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('/sous-domaine', name: 'public_onboarding_subdomain_check', methods: ['GET'])]
    public function subdomainCheck(Request $request): JsonResponse
    {
        $value = mb_strtolower(trim((string) $request->query->get('value', '')));

        if (!$this->subdomainService->isWellFormed($value)) {
            return $this->json(['available' => false, 'message' => $this->translator->trans('subdomainInvalid')]);
        }
        if ($this->subdomainService->isReserved($value)) {
            return $this->json(['available' => false, 'message' => $this->translator->trans('subdomainReserved')]);
        }
        // findOneBy sans filtre actif : un club désactivé garde son sous-domaine (contrainte unique)
        if ($this->clubRepository->findOneBy(['subdomain' => $value]) !== null) {
            return $this->json(['available' => false, 'message' => $this->translator->trans('subdomainTaken')]);
        }

        return $this->json(['available' => true, 'message' => $this->translator->trans('subdomainAvailable')]);
    }
}
