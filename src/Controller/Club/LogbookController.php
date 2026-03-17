<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Membership;
use App\Form\Filter\LogbookFilterType;
use App\Repository\ContributionRepository;
use App\Repository\MembershipRepository;
use App\Repository\Paginator;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use Nucleos\DompdfBundle\Wrapper\DompdfWrapperInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('VIEW')]
class LogbookController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly MembershipRepository $membershipRepository,
        private readonly ContributionRepository $contributionRepository,
        private readonly DompdfWrapperInterface $dompdf,
    ) {
        parent::__construct($subdomainService);
    }

    /**
     * Redirects to the current user's logbook for this club.
     */
    #[Route('/logbook', name: 'club_logbook')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'mechanicLogbook'],
    ])]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        $membership = $this->membershipRepository->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);

        if (!$membership) {
            throw $this->createAccessDeniedException();
        }

        return $this->redirectToRoute('club_member_logbook', [
            'id' => $membership->getId(),
        ] + $request->query->all());
    }

    /**
     * Show a specific member's logbook within the current club.
     */
    #[Route('/members/member/{id}/logbook', name: 'club_member_logbook')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'mechanicLogbook', 'route' => 'club_logbook'],
        ['label' => 'members', 'route' => 'club_members'],
        ['label' => '$membership.user.fullname'],
    ])]
    public function show(Request $request, #[MapEntity] Membership $membership): Response
    {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        if ($membership->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        // Current user's membership in this club (for permission checks)
        $currentMembership = $this->membershipRepository->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);

        $isOwnLogbook = $membership->getUser() === $user;
        $isManagerOrInspector = $currentMembership && ($currentMembership->isManager() || $currentMembership->isInspector());

        if (!$isOwnLogbook && !$isManagerOrInspector) {
            throw $this->createAccessDeniedException();
        }

        // Filters (default: last 365 days)
        $today = new \DateTimeImmutable('today');
        $filterForm = $this->createFilter(LogbookFilterType::class, [
            'periodStart' => $today->modify('-365 days'),
            'periodEnd' => $today,
            'status' => ['open', 'done', 'closed'],
        ], [
            'club' => $club,
            'logbook_owner_user' => $membership->getUser(),
        ]);
        $filterForm->handleRequest($request);
        $filterData = $this->getFilterData($filterForm);

        // Query contributions with pagination (list only)
        $qb = $this->contributionRepository->queryByMembershipWithFilters($membership, $club, $filterData);

        $page = max(1, $request->query->getInt('page', 1));
        $contributions = Paginator::paginate($qb, $page, 20);

        // Facets computed on the full (unpaginated) result set
        $facets = $this->contributionRepository->getFacetsByMembershipAndFilters($membership, $club, $filterData);

        return $this->render('club/logbook/show.html.twig', [
            'club' => $club,
            'membership' => $membership,
            'is_own_logbook' => $isOwnLogbook,
            'contributions' => $contributions,
            'facets' => $facets,
            'filters' => $filterForm->createView(),
        ]);
    }

    /**
     * PDF export of logbook statistics for a member.
     */
    #[Route('/members/member/{id}/logbook/pdf', name: 'club_member_logbook_pdf')]
    #[IsGranted('VIEW')]
    public function pdf(Request $request, #[MapEntity] Membership $membership): Response
    {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        if ($membership->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $currentMembership = $this->membershipRepository->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);

        $isOwnLogbook = $membership->getUser() === $user;
        $isManagerOrInspector = $currentMembership && ($currentMembership->isManager() || $currentMembership->isInspector());

        if (!$isOwnLogbook && !$isManagerOrInspector) {
            throw $this->createAccessDeniedException();
        }

        // Rebuild filters from query for consistent stats (same defaults as show)
        $today = new \DateTimeImmutable('today');
        $filterForm = $this->createFilter(LogbookFilterType::class, [
            'periodStart' => $today->modify('-365 days'),
            'periodEnd' => $today,
            'status' => ['open', 'done', 'closed'],
        ], [
            'club' => $club,
            'logbook_owner_user' => $membership->getUser(),
        ]);
        $filterForm->handleRequest($request);
        $filterData = $this->getFilterData($filterForm);

        $facets = $this->contributionRepository->getFacetsByMembershipAndFilters($membership, $club, $filterData);

        // Liste complète des contributions filtrées (sans pagination)
        $contributions = $this->contributionRepository
            ->queryByMembershipWithFilters($membership, $club, $filterData)
            ->getQuery()
            ->getResult();

        $html = $this->renderView('club/logbook/print.html.twig', [
            'club' => $club,
            'membership' => $membership,
            'facets' => $facets,
            'filters' => $filterForm->createView(),
            'contributions' => $contributions,
        ]);

        $filename = sprintf(
            'carnet-mecano-%s-%s.pdf',
            $membership->getUser()->getId(),
            (new \DateTimeImmutable())->format('Ymd')
        );

        $response = $this->dompdf->getStreamResponse($html, $filename, [
            'format' => 'A4',
            'orientation' => 'portrait',
            'margin-top' => '12mm',
            'margin-right' => '14mm',
            'margin-bottom' => '12mm',
            'margin-left' => '14mm',
            'Attachment' => false,
        ]);

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');

        return $response;
    }
}

