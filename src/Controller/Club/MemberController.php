<?php

namespace App\Controller\Club;

use App\Entity\Club;
use App\Entity\Invitation;
use App\Entity\Membership;
use App\Form\InvitationType;
use App\Form\Filter\MemberFilterType;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\Paginator;
use App\Controller\ExtendedController;
use App\Service\ClubResolver;
use App\Service\InvitationService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/members', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('ROLE_USER')]
class MemberController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly MembershipRepository $membershipRepository,
        private readonly InvitationRepository $invitationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationService $invitationService,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_members')]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $this->denyAccessUnlessGranted('MANAGE', $club);

        // Handle filters
        $filterForm = $this->createForm(MemberFilterType::class);
        $filterForm->handleRequest($request);

        $filters = [];
        if ($filterForm->isSubmitted() && $filterForm->isValid()) {
            $filters = $filterForm->getData();
            $filters = array_filter($filters, fn($value) => $value !== null && $value !== '');
        }

        // Get members with pagination
        $memberships = Paginator::paginate(
            $this->membershipRepository->queryByClubAndFilters($club, $filters),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/members/index.html.twig', [
            'club' => $club,
            'memberships' => $memberships,
            'filterForm' => $filterForm,
        ]);
    }

    #[Route('/invitations', name: 'club_invitations')]
    public function invitations(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $this->denyAccessUnlessGranted('MANAGE', $club);

        // Get pending invitations with pagination
        $invitations = Paginator::paginate(
            $this->invitationRepository->findPendingByClub($club),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/members/invitations.html.twig', [
            'club' => $club,
            'invitations' => $invitations,
        ]);
    }

    #[Route('/invite', name: 'club_member_invite')]
    public function invite(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $this->denyAccessUnlessGranted('MANAGE', $club);

        $invitation = new Invitation();
        $form = $this->createForm(InvitationType::class, $invitation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invitation = $this->invitationService->createInvitation($club, [
                'email' => $invitation->getEmail(),
                'firstname' => $invitation->getFirstname(),
                'lastname' => $invitation->getLastname(),
                'isManager' => $invitation->isManager(),
                'isInspector' => $invitation->isInspector(),
            ]);

            // Send invitation email
            $this->invitationService->sendInvitation($invitation);

            $this->addFlash('success', 'L\'invitation a été envoyée avec succès.');

            return $this->redirectToRoute('club_members');
        }

        return $this->render('club/members/invite.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/member/{id}/delete', name: 'club_member_delete', methods: ['POST'])]
    public function deleteMember(Membership $membership): Response
    {
        $club = $this->clubResolver->resolve();
        $this->denyAccessUnlessGranted('MANAGE', $club);

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
                $this->addFlash('danger', 'Vous ne pouvez pas vous retirer, vous êtes le dernier manager du club.');
                return $this->redirectToRoute('club_members');
            }
        }

        $userName = $membership->getUser()->getFullname();
        $this->entityManager->remove($membership);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('%s a été retiré du club.', $userName));

        return $this->redirectToRoute('club_members');
    }

    #[Route('/invitation/{id}/resend', name: 'club_invitation_resend', methods: ['POST'])]
    public function resendInvitation(Invitation $invitation): Response
    {
        $club = $this->clubResolver->resolve();
        $this->denyAccessUnlessGranted('MANAGE', $club);

        // Ensure invitation belongs to this club
        if ($invitation->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $this->invitationService->resendInvitation($invitation);

        $this->addFlash('success', 'L\'invitation a été renvoyée.');

        return $this->redirectToRoute('club_invitations');
    }

    #[Route('/invitation/{id}/delete', name: 'club_invitation_delete', methods: ['POST'])]
    public function deleteInvitation(Invitation $invitation): Response
    {
        $club = $this->clubResolver->resolve();
        $this->denyAccessUnlessGranted('MANAGE', $club);

        // Ensure invitation belongs to this club
        if ($invitation->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $this->invitationService->cancelInvitation($invitation);

        $this->addFlash('success', 'L\'invitation a été annulée.');

        return $this->redirectToRoute('club_invitations');
    }
}

