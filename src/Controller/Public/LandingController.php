<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', host: 'www.%domain%')]
class LandingController extends AbstractController
{
    #[Route('', name: 'public_landing')]
    public function index(): Response
    {
        // If user is already logged in, redirect to appropriate dashboard
        if ($this->getUser()) {
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            }

            // Regular users should be redirected to their club dashboard
            // This will be handled by the club subdomain
            return $this->redirectToRoute('public_login');
        }

        return $this->render('public/landing.html.twig');
    }
}

