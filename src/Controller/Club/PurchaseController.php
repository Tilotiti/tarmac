<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Activity;
use App\Entity\Enum\ActivityType;
use App\Entity\Enum\PurchaseStatus;
use App\Entity\Purchase;
use App\Form\ActivityFormType;
use App\Form\Filter\PurchaseFilterType;
use App\Form\PurchasePurchasedType;
use App\Form\PurchaseType;
use App\Repository\MembershipRepository;
use App\Repository\Paginator;
use App\Repository\PurchaseRepository;
use App\Security\Voter\PurchaseVoter;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/purchases', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www).*'])]
#[IsGranted('ROLE_USER')]
#[IsGranted('VIEW')]
class PurchaseController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly PurchaseRepository $purchaseRepository,
        private readonly MembershipRepository $membershipRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_purchases')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'purchases'],
    ])]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        // Handle filters
        $filters = $this->createFilter(
            PurchaseFilterType::class,
            [
                'status' => ['requested', 'pending_approval', 'approved'],
            ],
            [
                'user' => $user,
            ]
        );
        $filters->handleRequest($request);

        // Build query with filters
        $filterData = $this->getFilterData($filters);
        if ($user) {
            $filterData['user'] = $user;
        }

        $qb = $this->purchaseRepository->queryByFilters($filterData);

        // Order by creation date (newest first)
        $qb = $this->purchaseRepository->orderByCreatedAt($qb, 'DESC');

        $purchases = Paginator::paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/purchase/index.html.twig', [
            'club' => $club,
            'purchases' => $purchases,
            'filters' => $filters->createView(),
        ]);
    }

    #[Route('/new', name: 'club_purchase_new')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'purchases', 'route' => 'club_purchases'],
        ['label' => 'createPurchase'],
    ])]
    public function new(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();
        $isManager = $this->isGranted('MANAGE');

        $purchase = new Purchase();
        $purchase->setClub($club);
        $purchase->setCreatedBy($user);

        // Set initial status based on creator role
        // Member-created purchases are PENDING_APPROVAL, manager-created are APPROVED
        if ($isManager) {
            $purchase->setStatus(PurchaseStatus::APPROVED);
        } else {
            $purchase->setStatus(PurchaseStatus::PENDING_APPROVAL);
        }

        $form = $this->createForm(PurchaseType::class, $purchase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($purchase);
            $this->entityManager->flush();

            // Log purchase creation activity
            $activity = new Activity();
            $activity->setPurchase($purchase);
            $activity->setType(ActivityType::CREATED);
            $activity->setUser($user);
            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'purchaseCreated');

            return $this->redirectToRoute('club_purchase_show', ['id' => $purchase->getId()]);
        }

        return $this->render('club/purchase/new.html.twig', [
            'club' => $club,
            'purchase' => $purchase,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'club_purchase_show', requirements: ['id' => '\d+'])]
    #[IsGranted(PurchaseVoter::VIEW, 'purchase')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'purchases', 'route' => 'club_purchases'],
        ['label' => '$purchase.name'],
    ])]
    public function show(Purchase $purchase): Response
    {
        $club = $this->clubResolver->resolve();

        // Comment form
        $commentForm = null;
        if ($this->isGranted(PurchaseVoter::COMMENT, $purchase)) {
            $commentForm = $this->createForm(ActivityFormType::class, null, [
                'label' => 'comment',
                'placeholder' => 'addComment',
            ]);
        }

        $isManager = $this->isGranted('MANAGE');
        $nextPrimaryAction = $purchase->getNextPrimaryAction($this->getUser(), $isManager);

        return $this->render('club/purchase/show.html.twig', [
            'club' => $club,
            'purchase' => $purchase,
            'commentForm' => $commentForm,
            'nextPrimaryAction' => $nextPrimaryAction,
            'isManager' => $isManager,
        ]);
    }

    #[Route('/{id}/edit', name: 'club_purchase_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(PurchaseVoter::EDIT, 'purchase')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'purchases', 'route' => 'club_purchases'],
        ['label' => '$purchase.name', 'route' => 'club_purchase_show', 'params' => ['id' => '$purchase.id']],
        ['label' => 'editPurchase'],
    ])]
    public function edit(Purchase $purchase, Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        $form = $this->createForm(PurchaseType::class, $purchase);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            // Log purchase edit activity
            $activity = new Activity();
            $activity->setPurchase($purchase);
            $activity->setType(ActivityType::EDITED);
            $activity->setUser($user);
            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'purchaseUpdated');

            return $this->redirectToRoute('club_purchase_show', ['id' => $purchase->getId()]);
        }

        return $this->render('club/purchase/edit.html.twig', [
            'club' => $club,
            'purchase' => $purchase,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/cancel', name: 'club_purchase_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PurchaseVoter::CANCEL, 'purchase')]
    public function cancel(Purchase $purchase): Response
    {
        $user = $this->getUser();
        $now = new \DateTimeImmutable();

        $purchase->setStatus(PurchaseStatus::CANCELLED);
        $purchase->setCancelledBy($user);
        $purchase->setCancelledAt($now);

        $this->entityManager->flush();

        // Log cancellation activity
        $activity = new Activity();
        $activity->setPurchase($purchase);
        $activity->setType(ActivityType::CANCELLED);
        $activity->setUser($user);
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $this->addFlash('success', 'purchaseCancelled');

        return $this->redirectToRoute('club_purchases');
    }

    #[Route('/{id}/approve', name: 'club_purchase_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PurchaseVoter::APPROVE, 'purchase')]
    public function approve(Purchase $purchase): Response
    {
        $user = $this->getUser();
        $now = new \DateTimeImmutable();

        $purchase->setStatus(PurchaseStatus::APPROVED);
        $purchase->setApprovedBy($user);
        $purchase->setApprovedAt($now);

        $this->entityManager->flush();

        // Log approval activity
        $activity = new Activity();
        $activity->setPurchase($purchase);
        $activity->setType(ActivityType::INSPECTED_APPROVED);
        $activity->setUser($user);
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $this->addFlash('success', 'purchaseApproved');

        return $this->redirectToRoute('club_purchase_show', ['id' => $purchase->getId()]);
    }

    #[Route('/{id}/mark-purchased', name: 'club_purchase_mark_purchased', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(PurchaseVoter::MARK_PURCHASED, 'purchase')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'purchases', 'route' => 'club_purchases'],
        ['label' => '$purchase.name', 'route' => 'club_purchase_show', 'params' => ['id' => '$purchase.id']],
        ['label' => 'markAsPurchased'],
    ])]
    public function markPurchased(Purchase $purchase, Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();
        $isManager = $this->isGranted('MANAGE');

        $currentMembership = $this->membershipRepository->findOneBy([
            'user' => $user,
            'club' => $club,
        ]);

        $form = $this->createForm(PurchasePurchasedType::class, $purchase, [
            'club' => $club,
            'is_manager' => $isManager,
            'current_membership' => $currentMembership,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $now = new \DateTimeImmutable();

            // The UploadedFileTypeExtension automatically sets billImage on the purchase entity
            // Handle purchasedBy - if manager selected someone, use that; otherwise use current user
            if ($isManager && $form->has('purchasedByMembership')) {
                $selectedMembership = $form->get('purchasedByMembership')->getData();
                if ($selectedMembership) {
                    $purchase->setPurchasedBy($selectedMembership->getUser());
                } else {
                    $purchase->setPurchasedBy($user);
                }
            } else {
                $purchase->setPurchasedBy($user);
            }

            $purchase->setStatus(PurchaseStatus::PURCHASED);
            $purchase->setPurchasedAt($now);

            $this->entityManager->flush();

            // Log purchase activity
            $activity = new Activity();
            $activity->setPurchase($purchase);
            $activity->setType(ActivityType::DONE);
            $activity->setUser($purchase->getPurchasedBy());
            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'purchaseMarkedAsPurchased');

            return $this->redirectToRoute('club_purchase_show', ['id' => $purchase->getId()]);
        }

        return $this->render('club/purchase/mark_purchased.html.twig', [
            'club' => $club,
            'purchase' => $purchase,
            'form' => $form,
            'isManager' => $isManager,
        ]);
    }

    #[Route('/{id}/mark-delivered', name: 'club_purchase_mark_delivered', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PurchaseVoter::MARK_DELIVERED, 'purchase')]
    public function markDelivered(Purchase $purchase): Response
    {
        $user = $this->getUser();
        $now = new \DateTimeImmutable();

        // Auto-complete when delivered
        $purchase->setStatus(PurchaseStatus::COMPLETE);
        $purchase->setDeliveredBy($user);
        $purchase->setDeliveredAt($now);

        $this->entityManager->flush();

        // Log delivery activity
        $activity = new Activity();
        $activity->setPurchase($purchase);
        $activity->setType(ActivityType::CLOSED);
        $activity->setUser($user);
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $this->addFlash('success', 'purchaseMarkedAsDelivered');

        return $this->redirectToRoute('club_purchase_show', ['id' => $purchase->getId()]);
    }

    #[Route('/{id}/mark-reimbursed', name: 'club_purchase_mark_reimbursed', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PurchaseVoter::MARK_REIMBURSED, 'purchase')]
    public function markReimbursed(Purchase $purchase): Response
    {
        $user = $this->getUser();
        $now = new \DateTimeImmutable();

        $purchase->setStatus(PurchaseStatus::REIMBURSED);
        $purchase->setReimbursedBy($user);
        $purchase->setReimbursedAt($now);

        $this->entityManager->flush();

        // Log reimbursement activity
        $activity = new Activity();
        $activity->setPurchase($purchase);
        $activity->setType(ActivityType::DONE); // Using DONE for reimbursed (final completion step)
        $activity->setUser($user);
        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        $this->addFlash('success', 'purchaseMarkedAsReimbursed');

        return $this->redirectToRoute('club_purchase_show', ['id' => $purchase->getId()]);
    }

    #[Route('/{id}/comment', name: 'club_purchase_comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PurchaseVoter::COMMENT, 'purchase')]
    public function addComment(Purchase $purchase, Request $request): Response
    {
        $form = $this->createForm(ActivityFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $activity = new Activity();
            $activity->setPurchase($purchase);
            $activity->setType(ActivityType::COMMENT);
            $activity->setUser($this->getUser());
            $activity->setMessage($data['message']);

            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->addFlash('success', 'commentAdded');
        }

        return $this->redirectToRoute('club_purchase_show', ['id' => $purchase->getId()]);
    }
}

