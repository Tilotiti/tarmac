<?php

namespace App\Controller\Admin;

use App\Repository\ClubRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', host: 'www.%domain%')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(private readonly ClubRepository $clubRepository)
    {
    }

    #[Route('', name: 'admin_dashboard')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'recentClubs' => $this->clubRepository->findCreatedSince(new \DateTimeImmutable('-30 days')),
            'domain' => $this->getParameter('domain'),
        ]);
    }
}
