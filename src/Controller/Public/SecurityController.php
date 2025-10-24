<?php

namespace App\Controller\Public;

use App\Entity\User;
use App\Entity\Invitation;
use App\Form\RegistrationFormType;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\InvitationRepository;
use App\Service\InvitationService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/', host: 'www.%domain%')]
class SecurityController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationRepository $invitationRepository,
        private readonly InvitationService $invitationService,
        private readonly SubdomainService $subdomainService,
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
                $this->addFlash('success', 'Vos invitations ont été acceptées automatiquement.');
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
            $this->addFlash('danger', 'Cette invitation n\'est pas valide ou a expiré.');
            return $this->redirectToRoute('public_login');
        }

        // If user is logged in, accept invitation directly
        if ($this->getUser() instanceof User) {
            try {
                $this->invitationService->acceptInvitation($this->getUser(), $invitation);
                $this->addFlash('success', 'Vous avez rejoint ' . $invitation->getClub()->getName() . ' !');

                // Redirect to club dashboard
                $clubUrl = $this->subdomainService->generateClubUrl($invitation->getClub()->getSubdomain());
                return new RedirectResponse($clubUrl);
            } catch (\Exception $e) {
                $this->addFlash('danger', $e->getMessage());
                return $this->redirectToRoute('public_landing');
            }
        }

        // User not logged in - show registration or login option
        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Create new user
            $user = new User();
            $user->setEmail($invitation->getEmail());
            $user->setFirstname($invitation->getFirstname() ?? $form->get('firstname')->getData());
            $user->setLastname($invitation->getLastname() ?? $form->get('lastname')->getData());
            $user->setVerified(true); // Email verified through invitation

            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre compte a été créé ! Veuillez vous connecter pour rejoindre ' . $invitation->getClub()->getName() . '.');

            // Redirect to login page with invitation token to auto-accept after login
            return $this->redirectToRoute('public_login', ['invitation' => $token]);
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
        UserAuthenticatorInterface $userAuthenticator,
        #[Autowire(service: 'security.authenticator.form_login.main')]
        FormLoginAuthenticator $formLoginAuthenticator,
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

            $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès. Vous êtes maintenant connecté.');

            // Auto-login the user and redirect to app dashboard
            return $userAuthenticator->authenticateUser(
                $user,
                $formLoginAuthenticator,
                $request
            );
        }

        return $this->render('public/security/resetPassword/reset.html.twig', [
            'resetForm' => $form,
        ]);
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer): RedirectResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $emailFormData,
        ]);

        // Always show the same message to prevent email enumeration attacks
        $this->addFlash('success', 'Si cette adresse e-mail existe dans notre base de données, vous recevrez un lien de réinitialisation.');

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
                $this->addFlash('info', 'Un e-mail de réinitialisation vous a déjà été envoyé. Veuillez vérifier votre boîte de réception ou réessayer dans quelques minutes.');
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

