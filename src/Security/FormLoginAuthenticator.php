<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Psr\Log\LoggerInterface;

class FormLoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'public_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {
    }

    public function supports(Request $request): bool
    {
        // This authenticator supports POST requests to the login route
        return $request->isMethod('POST') 
            && $request->attributes->get('_route') === self::LOGIN_ROUTE;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->getPayload()->getString('_username');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->getPayload()->getString('_password')),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->logger->info('onAuthenticationSuccess called');

        // First, check if there's a saved target path
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            $this->logger->info('Using saved target path: ' . $targetPath);
            return new RedirectResponse($targetPath);
        }

        // Check if we're on a club subdomain by examining the host
        $host = $request->getHost();
        $this->logger->info('Current host: ' . $host);
        
        // If we're on a club subdomain (not www), redirect to club dashboard
        if (!str_starts_with($host, 'www.')) {
            $this->logger->info('On club subdomain, redirecting to club dashboard');
            // Get the current URL and redirect to the root of this subdomain (club_dashboard)
            $clubUrl = $request->getScheme() . '://' . $host . '/';
            return new RedirectResponse($clubUrl);
        }

        // Default: redirect to clubs selection page
        $this->logger->info('On www subdomain, redirecting to public_clubs');
        return new RedirectResponse($this->urlGenerator->generate('public_clubs', [], UrlGeneratorInterface::ABSOLUTE_URL));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE, [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}







