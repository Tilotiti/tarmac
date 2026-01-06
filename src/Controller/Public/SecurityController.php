<?php

namespace App\Controller\Public;

use App\Entity\Invitation;
use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\RegistrationFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\InvitationRepository;
use App\Service\InvitationService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/', host: 'www.%domain%')]
class SecurityController extends AbstractController
{
    use ResetPasswordControllerTrait;
    use TargetPathTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationRepository $invitationRepository,
        private readonly InvitationService $invitationService,
        private readonly SubdomainService $subdomainService,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/login', name: 'public_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already logged in, check for pending invitations
        if ($this->getUser() instanceof User) {
            $user = $this->getUser();

            // Auto-accept any pending invitations
            $acceptedClubs = $this->invitationService->autoAcceptInvitationsForUser($user);

            if (!empty($acceptedClubs)) {
                $this->addFlash('success', 'invitationsAutoAccepted');
                // Redirect to the first club
                $clubUrl = $this->subdomainService->generateClubUrl($acceptedClubs[0]->getSubdomain());
                return new RedirectResponse($clubUrl);
            }

            // Check if there's an invitation token in the URL
            if ($request->query->has('invitation')) {
                return $this->redirectToRoute('public_invitation_accept', [
                    'token' => $request->query->get('invitation'),
                ]);
            }

            if ($redirectUrl = $this->resolveLoginRedirectUrl($request)) {
                return new RedirectResponse($redirectUrl);
            }

            return $this->redirectToRoute('public_clubs');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Check if there's an invitation token to preserve
        $invitationToken = $request->query->get('invitation');

        return $this->render('public/security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'invitation_token' => $invitationToken,
        ]);
    }

    #[Route('/logout', name: 'public_logout')]
    public function logout(): void
    {
        // controller can be blank: it will never be executed!
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }

    #[Route('/invitation/{token}', name: 'public_invitation_accept')]
    public function acceptInvitation(
        string $token,
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher
    ): Response {
        // Find invitation by token
        $invitation = $this->invitationRepository->findValidByToken($token);

        if (!$invitation) {
            $this->addFlash('danger', 'invitationInvalid');
            return $this->redirectToRoute('public_login');
        }

        // If user is logged in, accept invitation directly
        if ($this->getUser() instanceof User) {
            try {
                $this->invitationService->acceptInvitation($this->getUser(), $invitation);
                $message = $this->translator->trans('invitationAccepted', ['clubName' => $invitation->getClub()->getName()]);
                $this->addFlash('success', $message);

                // Redirect to club dashboard
                $clubUrl = $this->subdomainService->generateClubUrl($invitation->getClub()->getSubdomain());
                return new RedirectResponse($clubUrl);
            } catch (\Exception $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('public_landing');
            }
        }

        // User not logged in - show registration or login option
        $form = $this->createForm(RegistrationFormType::class, null, [
            'include_firstname' => !$invitation->getFirstname(),
            'include_lastname' => !$invitation->getLastname(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Create new user
            $user = new User();
            $user->setEmail($invitation->getEmail());
            $user->setFirstname($invitation->getFirstname() ?? ($form->has('firstname') ? $form->get('firstname')->getData() : null));
            $user->setLastname($invitation->getLastname() ?? ($form->has('lastname') ? $form->get('lastname')->getData() : null));
            $user->setVerified(true); // Email verified through invitation

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Validate the entity (UniqueEntity constraint will check email uniqueness)
            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                // Add errors to the form
                foreach ($errors as $error) {
                    $form->addError(new \Symfony\Component\Form\FormError($this->translator->trans($error->getMessage())));
                }
            } else {
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // Accept the invitation
                try {
                    $this->invitationService->acceptInvitation($user, $invitation);
                    $message = $this->translator->trans('accountCreatedAndInvitationAccepted', ['clubName' => $invitation->getClub()->getName()]);
                    $this->addFlash('success', $message);
                } catch (\Exception $e) {
                    $this->addFlash('warning', $e->getMessage());
                }

                // Automatically authenticate the user
                $this->security->login($user);

                // Redirect to club dashboard
                $clubUrl = $this->subdomainService->generateClubUrl($invitation->getClub()->getSubdomain());
                return new RedirectResponse($clubUrl);
            }
        }

        return $this->render('public/security/register.html.twig', [
            'registrationForm' => $form,
            'invitation' => $invitation,
            'loginUrl' => $this->generateUrl('public_login', ['invitation' => $token]),
        ]);
    }

    /**
     * Display & process form to request a password reset.
     */
    #[Route('/reset-password', name: 'public_reset_password_request')]
    public function request(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();

            return $this->processSendingPasswordResetEmail($email, $mailer);
        }

        return $this->render('public/security/resetPassword/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     */
    #[Route('/reset-password/reset/{token}', name: 'public_reset_password')]
    public function reset(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        ?string $token = null
    ): Response {
        if ($token) {
            // We store the token in session and remove it from the URL, to avoid the URL being
            // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('public_reset_password');
        }

        $token = $this->getTokenFromSession();

        if (null === $token) {
            throw $this->createNotFoundException('Aucun jeton de réinitialisation de mot de passe trouvé dans l\'URL ou dans la session.');
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            $this->addFlash('danger', sprintf(
                'Un problème est survenu lors de la validation de votre demande de réinitialisation - %s',
                $e->getReason()
            ));

            return $this->redirectToRoute('public_reset_password_request');
        }

        // The token is valid; allow the user to change their password.
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // A password reset token should be used only once, remove it.
            $this->resetPasswordHelper->removeResetRequest($token);

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Encode(hash) the plain password, and set it.
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            $this->entityManager->flush();

            // The session is cleaned up after the password has been changed.
            $this->cleanSessionAfterReset();

            $this->addFlash('success', 'passwordResetSuccess');

            // Auto-login the user and redirect to clubs page
            $this->security->login($user);

            return $this->redirectToRoute('public_clubs');
        }

        return $this->render('public/security/resetPassword/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    private function resolveLoginRedirectUrl(Request $request): ?string
    {
        $candidates = [];
        $session = $request->getSession();
        $sessionTarget = null;

        if ($session instanceof SessionInterface) {
            $sessionTarget = $this->getTargetPath($session, 'main');
            if (is_string($sessionTarget) && $sessionTarget !== '') {
                $candidates[] = ['value' => $sessionTarget, 'type' => 'session'];
            }
        }

        $targetParam = $request->query->get('_target_path');
        if (is_string($targetParam) && $targetParam !== '') {
            $candidates[] = ['value' => $targetParam, 'type' => 'query'];
        }

        $referer = $request->headers->get('referer');
        if (is_string($referer) && $referer !== '') {
            $candidates[] = ['value' => $referer, 'type' => 'referer'];
        }

        foreach ($candidates as $candidate) {
            $url = $candidate['value'];

            if ($this->isSafeRedirectUrl($url) && !$this->isLoginRouteUrl($url)) {
                if ($candidate['type'] === 'session' && $session instanceof SessionInterface && $sessionTarget === $url) {
                    $this->removeTargetPath($session, 'main');
                }

                return $url;
            }
        }

        return null;
    }

    private function isSafeRedirectUrl(string $url): bool
    {
        if (str_starts_with($url, '/')) {
            return true;
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return false;
        }

        $scheme = $parts['scheme'] ?? 'https';
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            return false;
        }

        return $this->isSameApplicationHost($parts['host']);
    }

    private function isLoginRouteUrl(string $url): bool
    {
        $loginPath = $this->generateUrl('public_login');
        $absoluteLoginUrl = $this->generateUrl('public_login', [], UrlGeneratorInterface::ABSOLUTE_URL);

        if ($url === $absoluteLoginUrl || $url === $loginPath) {
            return true;
        }

        if (str_starts_with($url, '/')) {
            return $url === $loginPath;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $path = $parts['path'] ?? null;
        if ($path !== $loginPath) {
            return false;
        }

        if (!isset($parts['host'])) {
            return true;
        }

        return $this->isSameApplicationHost($parts['host']);
    }

    private function isSameApplicationHost(string $host): bool
    {
        $host = strtolower(explode(':', $host)[0]);
        $domain = strtolower($this->subdomainService->getDomain());

        if ($host === $domain) {
            return true;
        }

        if ($host === 'www.' . $domain) {
            return true;
        }

        return str_ends_with($host, '.' . $domain);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer): RedirectResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Always show the same message to prevent email enumeration attacks
        $this->addFlash('success', 'passwordResetEmailSent');

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute('public_login');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            // If there's already an active request, inform the user to check their email
            // This is safe to show as the generic message was already added above
            if (str_contains($e->getReason(), 'already requested')) {
                $this->addFlash('info', 'passwordResetAlreadyRequested');
            }
            return $this->redirectToRoute('public_login');
        }

        $email = (new TemplatedEmail())
            ->from(new Address('contact@tarmac.center', 'Tarmac'))
            ->to((string) $user->getEmail())
            ->subject('Votre demande de réinitialisation de mot de passe')
            ->htmlTemplate('email/resetPassword.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'user' => $user,
            ])
        ;

        $mailer->send($email);

        // Store the token object in session for retrieval in check-email route.
        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('public_login');
    }
}

