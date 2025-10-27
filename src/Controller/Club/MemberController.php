<?php

namespace App\Controller\Club;

use App\Entity\Club;
use App\Entity\Membership;
use App\Form\MembershipType;
use App\Form\Filter\MemberFilterType;
use App\Repository\MembershipRepository;
use App\Repository\Paginator;
use App\Controller\ExtendedController;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/members', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('VIEW')]
class MemberController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly MembershipRepository $membershipRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_members')]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // Handle filters
        $filters = $this->createFilter(MemberFilterType::class);
        $filters->handleRequest($request);

        // Get members with pagination
        $memberships = Paginator::paginate(
            $this->membershipRepository->queryByClubAndFilters($club, $filters->getData() ?? []),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/members/index.html.twig', [
            'club' => $club,
            'memberships' => $memberships,
            'filters' => $filters->createView(),
        ]);
    }

    #[Route('/member/{id}/delete', name: 'club_member_delete', methods: ['POST'])]
    #[IsGranted('MANAGE')]
    public function deleteMember(Membership $membership): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure membership belongs to this club
        if ($membership->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        // Prevent removing self if last manager
        $user = $this->getUser();
        if ($membership->getUser() === $user && $membership->isManager()) {
            $managerCount = $this->membershipRepository->queryByClubAndFilters($club, ['role' => 'manager'])
                ->select('COUNT(m.id)')
                ->getQuery()
                ->getSingleScalarResult();

            if ($managerCount <= 1) {
                $this->addFlash('danger', 'cannotLeaveLastManager');
                return $this->redirectToRoute('club_members');
            }
        }

        $userName = $membership->getUser()->getFullname();
        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        $message = $this->translator->trans('memberRemoved', ['memberName' => $userName]);
        $this->addFlash('success', $message);

        return $this->redirectToRoute('club_members');
    }

    #[Route('/member/{id}', name: 'club_member_show')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'members', 'route' => 'club_members'],
        ['label' => '$membership.user.fullname'],
    ])]
    public function show(Request $request, Membership $membership): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure membership belongs to this club
        if ($membership->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        // Create form for the modal (only for managers)
        $form = null;
        $openModal = false;

        if ($this->isGranted('MANAGE')) {
            $form = $this->createForm(MembershipType::class, $membership);
            $form->handleRequest($request);
        }

        if ($form && $form->isSubmitted() && $form->isValid()) {
            // Prevent removing last manager
            $user = $this->getUser();
            if ($membership->getUser() === $user && !$membership->isManager()) {
                $managerCount = $this->membershipRepository->queryByClubAndFilters($club, ['role' => 'manager'])
                    ->select('COUNT(m.id)')
                    ->getQuery()
                    ->getSingleScalarResult();

                if ($managerCount <= 1) {
                    $this->addFlash('danger', 'cannotRemoveLastManager');
                    return $this->redirectToRoute('club_member_show', ['id' => $membership->getId()]);
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'rolesUpdated');

            return $this->redirectToRoute('club_member_show', ['id' => $membership->getId()]);
        } elseif ($form && $form->isSubmitted()) {
            // Form was submitted but has errors, keep modal open
            $openModal = true;
        }

        return $this->render('club/members/show.html.twig', [
            'club' => $club,
            'membership' => $membership,
            'form' => $form,
            'openModal' => $openModal,
        ]);
    }
}

