<?php

namespace App\Controller\Club;

use App\Entity\Club;
use App\Entity\Invitation;
use App\Form\InvitationType;
use App\Form\InvitationImportType;
use App\Repository\InvitationRepository;
use App\Repository\Paginator;
use App\Controller\ExtendedController;
use App\Service\ClubResolver;
use App\Service\InvitationService;
use App\Service\InvitationImportService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/invitations', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('MANAGE')]
class InvitationController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly InvitationRepository $invitationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly InvitationService $invitationService,
        private readonly InvitationImportService $invitationImportService,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_invitations')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'members', 'route' => 'club_members'],
        ['label' => 'invitations'],
    ])]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // Get pending invitations with pagination
        $invitations = Paginator::paginate(
            $this->invitationRepository->queryPendingByClub($club),
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/members/invitations/index.html.twig', [
            'club' => $club,
            'invitations' => $invitations,
        ]);
    }

    #[Route('/create', name: 'club_invitation_create')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'members', 'route' => 'club_members'],
        ['label' => 'invite'],
    ])]
    public function create(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

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
                'isPilote' => $invitation->isPilote(),
            ]);

            // Send invitation email
            $this->invitationService->sendInvitation($invitation);

            $this->addFlash('success', 'invitationSent');

            return $this->redirectToRoute('club_invitations');
        }

        return $this->render('club/members/invitations/invite.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    #[Route('/import', name: 'club_invitation_import')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'members', 'route' => 'club_members'],
        ['label' => 'invitations', 'route' => 'club_invitations'],
        ['label' => 'import'],
    ])]
    public function import(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $form = $this->createForm(InvitationImportType::class);
        $form->handleRequest($request);

        $result = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $file = $form->get('file')->getData();

            if ($file) {
                $result = $this->invitationImportService->importFromFile($file, $club);

                if (!$result->hasErrors()) {
                    $this->addFlash('success', 'importProcessed');
                }
            }
        }

        return $this->render('club/members/invitations/import.html.twig', [
            'club' => $club,
            'form' => $form,
            'result' => $result,
        ]);
    }

    #[Route('/{id}', name: 'club_invitation_show')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'members', 'route' => 'club_members'],
        ['label' => 'invitations', 'route' => 'club_invitations'],
        ['label' => '$invitation.email'],
    ])]
    public function show(Request $request, Invitation $invitation): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure invitation belongs to this club
        if ($invitation->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        // Create form for the modal (email disabled since it can't be changed)
        $form = $this->createForm(InvitationType::class, $invitation, [
            'disable_email' => true,
        ]);
        $form->handleRequest($request);

        $openModal = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'invitationUpdated');

            return $this->redirectToRoute('club_invitation_show', ['id' => $invitation->getId()]);
        } elseif ($form->isSubmitted()) {
            // Form was submitted but has errors, keep modal open
            $openModal = true;
        }

        return $this->render('club/members/invitations/show.html.twig', [
            'club' => $club,
            'invitation' => $invitation,
            'form' => $form,
            'openModal' => $openModal,
        ]);
    }

    #[Route('/{id}/resend', name: 'club_invitation_resend', methods: ['POST'])]
    public function resendInvitation(Invitation $invitation): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure invitation belongs to this club
        if ($invitation->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $this->invitationService->resendInvitation($invitation);

        $this->addFlash('success', 'invitationResent');

        return $this->redirectToRoute('club_invitation_show', ['id' => $invitation->getId()]);
    }

    #[Route('/{id}/delete', name: 'club_invitation_delete', methods: ['POST'])]
    public function deleteInvitation(Invitation $invitation): Response
    {
        $club = $this->clubResolver->resolve();

        // Ensure invitation belongs to this club
        if ($invitation->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $this->invitationService->cancelInvitation($invitation);

        $this->addFlash('success', 'invitationCancelled');

        return $this->redirectToRoute('club_invitations');
    }
}

