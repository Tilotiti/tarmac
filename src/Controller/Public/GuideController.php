<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/guide', host: 'www.%domain%')]
class GuideController extends AbstractController
{
    #[Route('', name: 'public_guide')]
    public function index(): Response
    {
        return $this->render('public/guide/index.html.twig');
    }
}
