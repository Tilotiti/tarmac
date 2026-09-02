<?php

namespace App\Controller\Public;

use App\Controller\ExtendedController;
use App\Entity\Club;
use App\Entity\JoinRequest;
use App\Entity\User;
use App\Form\Onboarding\JoinRequestType;
use App\Form\Onboarding\OnboardingAccountType;
use App\Form\Onboarding\OnboardingClubType;
use App\Repository\ClubRepository;
use App\Repository\JoinRequestRepository;
use App\Repository\UserRepository;
use App\Service\Onboarding\ClubDuplicateFinder;
use App\Service\Onboarding\ClubRegistrationService;
use App\Service\Onboarding\DuplicateReport;
use App\Service\Onboarding\EmailVerificationService;
use App\Service\Onboarding\JoinRequestService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

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

    #[Route('', name: 'public_onboarding_account', methods: ['GET', 'POST'])]
    public function account(Request $request): Response
    {
        $current = $this->getUser();
        if ($current instanceof User) {
            return $this->redirectToRoute($current->isVerified() ? 'public_onboarding_club' : 'public_onboarding_verify');
        }

        $user = new User();
        $form = $this->createForm(OnboardingAccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Honeypot rempli : un robot. On ne crée rien et on ne le dit pas.
            if ((string) $form->get('website')->getData() !== '') {
                return $this->redirectToRoute('public_landing');
            }

            if (!$this->accountLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('danger', 'tooManyAttempts');

                return $this->redirectToRoute('public_onboarding_account');
            }

            // La contrainte UniqueEntity de l'entité User invalide déjà le formulaire sur un
            // e-mail existant (message « emailUnique »). On ne fait ici que préparer la reprise
            // du parcours après connexion, sans dupliquer ce message d'erreur.
            if ($user->getEmail() !== null && $this->userRepository->findOneBy(['email' => $user->getEmail()]) !== null) {
                // Après connexion, l'utilisateur reprend à l'étape « Club ».
                $this->saveTargetPath($request->getSession(), 'main', $this->generateUrl('public_onboarding_club'));
            } elseif ($form->isValid()) {
                $user->setPassword($this->passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
                $user->setVerified(false);
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $this->emailVerification->send($user);
                $this->security->login($user);

                return $this->redirectToRoute('public_onboarding_verify');
            }
        }

        // AbstractController::render() force le statut 422 sur un formulaire soumis-invalide
        // (convention du projet, cf. Admin\ClubController::new, Public\SecurityController::acceptInvitation) :
        // Turbo Drive ne réaffiche une réponse à une requête non-GET que sur redirection ou 4xx/5xx,
        // un 200 laisserait l'utilisateur sans retour visible sur une erreur de validation.
        return $this->render('public/onboarding/account.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/verification', name: 'public_onboarding_verify', methods: ['GET'])]
    public function verify(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('public_onboarding_account');
        }
        if ($user->isVerified()) {
            return $this->redirectToRoute('public_onboarding_club');
        }

        return $this->render('public/onboarding/verify.html.twig', [
            'email' => $user->getEmail(),
        ]);
    }

    #[Route('/verification/renvoyer', name: 'public_onboarding_verify_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->isVerified()) {
            return $this->redirectToRoute('public_onboarding_account');
        }
        if (!$this->isCsrfTokenValid('resend-verification', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->resendLimiter->create((string) $user->getId())->consume()->isAccepted()) {
            $this->addFlash('danger', 'tooManyAttempts');
        } else {
            $this->emailVerification->send($user);
            $this->addFlash('success', 'verificationEmailSent');
        }

        return $this->redirectToRoute('public_onboarding_verify');
    }

    #[Route('/verification/confirmer', name: 'public_onboarding_verify_confirm', methods: ['GET'])]
    public function confirm(Request $request): Response
    {
        $user = $this->userRepository->find($request->query->getInt('id'));
        if ($user === null) {
            $this->addFlash('danger', 'verificationLinkInvalid');

            return $this->redirectToRoute('public_onboarding_account');
        }

        try {
            $this->emailVerification->confirm($request, $user);
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('danger', 'verificationLinkExpired');

            return $this->redirectToRoute($this->getUser() instanceof User ? 'public_onboarding_verify' : 'public_onboarding_account');
        }

        if ($this->getUser() !== $user) {
            $this->security->login($user);
        }
        $this->addFlash('success', 'emailVerified');

        return $this->redirectToRoute('public_onboarding_club');
    }

    /** Renvoie l'utilisateur connecté et vérifié, ou la redirection vers l'écran adéquat. */
    private function verifiedUserOrRedirect(): User|Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('public_onboarding_account');
        }
        if (!$user->isVerified()) {
            return $this->redirectToRoute('public_onboarding_verify');
        }

        return $user;
    }

    #[Route('/club', name: 'public_onboarding_club', methods: ['GET', 'POST'])]
    public function club(Request $request): Response
    {
        $user = $this->verifiedUserOrRedirect();
        if ($user instanceof Response) {
            return $user;
        }

        $club = new Club();
        $form = $this->createForm(OnboardingClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $report = $this->duplicateFinder->find((string) $club->getName(), $club->getAerodrome());
            $confirmed = $form->get('confirmed')->getData() === '1';

            if (!$report->isEmpty() && !$confirmed) {
                return $this->render('public/onboarding/duplicate.html.twig', [
                    'form' => $form,
                    'report' => $report,
                ]);
            }

            $this->registration->register($user, $club, $confirmed ? $report : new DuplicateReport());

            return $this->redirectToRoute('public_onboarding_done', ['id' => $club->getId()]);
        }

        return $this->render('public/onboarding/club.html.twig', [
            'form' => $form,
            'domain' => $this->subdomainService->getDomain(),
        ]);
    }

    #[Route('/bienvenue/{id}', name: 'public_onboarding_done', methods: ['GET'])]
    public function done(Club $club): Response
    {
        $this->denyAccessUnlessGranted('MANAGE', $club);

        return $this->render('public/onboarding/done.html.twig', [
            'club' => $club,
            'clubUrl' => $this->subdomainService->generateClubUrl((string) $club->getSubdomain()),
        ]);
    }

    #[Route('/rejoindre/{id}', name: 'public_onboarding_join_request', methods: ['GET', 'POST'])]
    public function joinRequest(Request $request, Club $club): Response
    {
        if (!$club->isActive()) {
            throw $this->createNotFoundException();
        }

        $user = $this->verifiedUserOrRedirect();
        if ($user instanceof Response) {
            return $user;
        }
        if ($club->hasUser($user)) {
            $this->addFlash('info', 'alreadyMemberOfClub');

            return $this->redirectToRoute('public_clubs');
        }

        $form = $this->createForm(JoinRequestType::class, new JoinRequest());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->joinRequestService->submit($user, $club, $form->getData()->getMessage());

            return $this->render('public/onboarding/joinRequestSent.html.twig', ['club' => $club]);
        }

        return $this->render('public/onboarding/joinRequest.html.twig', ['club' => $club, 'form' => $form]);
    }
}
