<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', host: 'www.%domain%')]
class DesignLabController extends AbstractController
{
    #[Route('design-lab', name: 'public_design_lab')]
    public function index(): Response
    {
        return $this->render('public/designLab.html.twig');
    }
}
