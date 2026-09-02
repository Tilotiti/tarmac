<?php

namespace App\Tests\Functional;

use App\Entity\User;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class OnboardingAccountTest extends FunctionalTestCase
{
    public function testAccountCreationSendsVerificationAndRedirects(): void
    {
        $this->client->request('GET', $this->www('/creer-mon-club'));
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Créer mon compte', [
            'onboarding_account[firstname]' => 'Jean',
            'onboarding_account[lastname]' => 'Dupont',
            'onboarding_account[email]' => 'jean@example.test',
            'onboarding_account[plainPassword][first]' => 'Planeur-Beauce-2026',
            'onboarding_account[plainPassword][second]' => 'Planeur-Beauce-2026',
        ]);

        $this->assertResponseRedirects('/creer-mon-club/verification');
        $this->assertEmailCount(1);
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'jean@example.test']);
        $this->assertNotNull($user);
        $this->assertFalse($user->isVerified());

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'jean@example.test');
    }

    public function testHoneypotSilentlyDropsSubmission(): void
    {
        $this->client->request('GET', $this->www('/creer-mon-club'));
        $this->client->submitForm('Créer mon compte', [
            'onboarding_account[firstname]' => 'Bot',
            'onboarding_account[lastname]' => 'Bot',
            'onboarding_account[email]' => 'bot@example.test',
            'onboarding_account[plainPassword][first]' => 'Planeur-Beauce-2026',
            'onboarding_account[plainPassword][second]' => 'Planeur-Beauce-2026',
            'onboarding_account[website]' => 'http://spam.example',
        ]);

        $this->assertResponseRedirects('/');
        $this->assertEmailCount(0);
        $this->assertNull($this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'bot@example.test']));
    }

    public function testExistingEmailShowsLoginHint(): void
    {
        $this->createUser('deja@example.test');

        $this->client->request('GET', $this->www('/creer-mon-club'));
        $this->client->submitForm('Créer mon compte', [
            'onboarding_account[firstname]' => 'Jean',
            'onboarding_account[lastname]' => 'Dupont',
            'onboarding_account[email]' => 'deja@example.test',
            'onboarding_account[plainPassword][first]' => 'Planeur-Beauce-2026',
            'onboarding_account[plainPassword][second]' => 'Planeur-Beauce-2026',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Un compte existe déjà avec cette adresse');
    }

    public function testVerifiedLoggedInUserSkipsToClubStep(): void
    {
        $this->client->loginUser($this->createUser('verifie@example.test'));

        $this->client->request('GET', $this->www('/creer-mon-club'));

        $this->assertResponseRedirects('/creer-mon-club/club');
    }

    public function testConfirmationLinkVerifiesAndRedirects(): void
    {
        $user = $this->createUser('averifier@example.test', verified: false);
        $signature = static::getContainer()->get(VerifyEmailHelperInterface::class)
            ->generateSignature('public_onboarding_verify_confirm', (string) $user->getId(), (string) $user->getEmail(), ['id' => $user->getId()]);

        $this->client->request('GET', $signature->getSignedUrl());

        $this->assertResponseRedirects('/creer-mon-club/club');
        $this->entityManager()->refresh($user);
        $this->assertTrue($user->isVerified());
    }

    public function testTamperedConfirmationLinkIsRejected(): void
    {
        $user = $this->createUser('averifier2@example.test', verified: false);

        $this->client->request('GET', $this->www('/creer-mon-club/verification/confirmer?id=' . $user->getId() . '&expires=1&signature=xyz&token=abc'));

        $this->assertResponseRedirects('/creer-mon-club');
        $this->entityManager()->refresh($user);
        $this->assertFalse($user->isVerified());
    }
}
