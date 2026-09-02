<?php

namespace App\Service\Onboarding;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerificationService
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function send(User $user): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'public_onboarding_verify_confirm',
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => $user->getId()],
        );

        $this->mailer->send((new TemplatedEmail())
            ->from(new Address('contact@tarmac.center', 'Tarmac'))
            ->to((string) $user->getEmail())
            ->subject('Confirmez votre adresse e-mail')
            ->htmlTemplate('email/verifyEmail.html.twig')
            ->context([
                'user' => $user,
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAt' => $signature->getExpiresAt(),
            ]));
    }

    /**
     * @throws \SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface
     */
    public function confirm(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), (string) $user->getEmail());
        $user->setVerified(true);
        $this->entityManager->flush();
    }
}
