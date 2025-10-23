<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Redirects bare domain/subdomains to www subdomain
 * Examples:
 * - tarmac.app → www.tarmac.app (production)
 * - staging.tarmac.app → www.staging.tarmac.app
 * - tarmac-test-xxx.herokuapp.com → www.tarmac.app (review apps)
 */
final class RedirectController extends AbstractController
{
    /**
     * Redirect bare Heroku URLs to custom domain
     * Catches review app and other Heroku URLs like appname.herokuapp.com
     */
    #[Route(
        '/{path}',
        name: 'redirect_heroku_to_custom',
        condition: "request.getHost() matches '/\\.herokuapp\\.com$/'",
        schemes: ['https'],
        priority: 110,
        requirements: [
            'path' => '.*'
        ],
        defaults: [
            'path' => ''
        ]
    )]
    public function redirectHerokuToCustom(Request $request): RedirectResponse
    {
        $domain = $this->getParameter('domain');

        // Build the www URL using the configured custom domain
        $url = sprintf(
            'https://www.%s%s',
            $domain,
            $request->getRequestUri()
        );

        return new RedirectResponse($url, 302); // 302 temporary for review apps
    }

    /**
     * Redirect bare production domain to www
     */
    #[Route(
        '/{path}',
        name: 'redirect_root_to_www',
        host: '%domain%',
        schemes: ['https'],
        priority: 100,
        requirements: [
            'path' => '.*'
        ],
        defaults: [
            'path' => ''
        ]
    )]
    public function redirectRootToWww(Request $request): RedirectResponse
    {
        $domain = $this->getParameter('domain');

        // Build the www URL
        $url = sprintf(
            'https://www.%s%s',
            $domain,
            $request->getRequestUri()
        );

        return new RedirectResponse($url, 301);
    }
}

