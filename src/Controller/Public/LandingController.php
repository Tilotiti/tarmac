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
        // If user is already logged in, redirect to clubs page
        if ($this->getUser()) {
            return $this->redirectToRoute('public_clubs');
        }

        return $this->render('public/landing.html.twig');
    }
}

