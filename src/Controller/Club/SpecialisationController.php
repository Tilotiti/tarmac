<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Specialisation;
use App\Form\Filter\SpecialisationFilterType;
use App\Form\SpecialisationType;
use App\Repository\Paginator;
use App\Repository\SpecialisationRepository;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/specialisations', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('MANAGE')]
class SpecialisationController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly SpecialisationRepository $specialisationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_specialisations', methods: ['GET'])]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'specialisations'],
    ])]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $filters = $this->createFilter(SpecialisationFilterType::class);
        $filters->handleRequest($request);

        $specialisations = Paginator::paginate(
            $this->specialisationRepository->queryByClubAndFilters($club, $filters->getData() ?? []),
            $request->query->getInt('page', 1),
            12
        );

        return $this->render('club/specialisation/index.html.twig', [
            'club' => $club,
            'specialisations' => $specialisations,
            'filters' => $filters->createView(),
        ]);
    }

    #[Route('/new', name: 'club_specialisation_new', methods: ['GET', 'POST'])]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'specialisations', 'route' => 'club_specialisations'],
        ['label' => 'newSpecialisation'],
    ])]
    public function new(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $specialisation = new Specialisation();
        $specialisation->setClub($club);

        $form = $this->createForm(SpecialisationType::class, $specialisation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($specialisation);
            $this->entityManager->flush();

            $this->addFlash('success', 'specialisationCreated');

            return $this->redirectToRoute('club_specialisations');
        }

        return $this->render('club/specialisation/new.html.twig', [
            'club' => $club,
            'specialisation' => $specialisation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'club_specialisation_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'specialisations', 'route' => 'club_specialisations'],
        ['label' => 'editSpecialisation'],
    ])]
    public function edit(Request $request, Specialisation $specialisation): Response
    {
        $club = $this->clubResolver->resolve();

        if ($specialisation->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(SpecialisationType::class, $specialisation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'specialisationUpdated');

            return $this->redirectToRoute('club_specialisations');
        }

        return $this->render('club/specialisation/edit.html.twig', [
            'club' => $club,
            'specialisation' => $specialisation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'club_specialisation_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Specialisation $specialisation): Response
    {
        $club = $this->clubResolver->resolve();

        if ($specialisation->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete' . $specialisation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'invalidCsrfToken');
            return $this->redirectToRoute('club_specialisations');
        }

        $this->entityManager->remove($specialisation);
        $this->entityManager->flush();

        $this->addFlash('success', 'specialisationDeleted');

        return $this->redirectToRoute('club_specialisations');
    }

    #[Route('/{id}/merge', name: 'club_specialisation_merge', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'specialisations', 'route' => 'club_specialisations'],
        ['label' => 'mergeSpecialisation'],
    ])]
    public function merge(Request $request, Specialisation $specialisation): Response
    {
        $club = $this->clubResolver->resolve();

        if ($specialisation->getClub() !== $club) {
            throw $this->createNotFoundException();
        }

        $others = array_filter(
            $this->specialisationRepository->findByClub($club),
            static fn (Specialisation $s) => $s->getId() !== $specialisation->getId()
        );

        if (empty($others)) {
            $this->addFlash('warning', 'noOtherSpecialisationToMerge');
            return $this->redirectToRoute('club_specialisations');
        }

        $form = $this->createFormBuilder()
            ->add('target', EntityType::class, [
                'class' => Specialisation::class,
                'choices' => $others,
                'choice_label' => 'name',
                'label' => 'mergeTargetSpecialisation',
                'attr' => ['class' => 'form-select'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Specialisation $target */
            $target = $form->get('target')->getData();

            foreach ($specialisation->getSubTasks() as $subTask) {
                if (!$subTask->getSpecialisations()->contains($target)) {
                    $subTask->addSpecialisation($target);
                }
                $subTask->removeSpecialisation($specialisation);
            }
            foreach ($specialisation->getPlanSubTasks() as $planSubTask) {
                if (!$planSubTask->getSpecialisations()->contains($target)) {
                    $planSubTask->addSpecialisation($target);
                }
                $planSubTask->removeSpecialisation($specialisation);
            }

            $this->entityManager->remove($specialisation);
            $this->entityManager->flush();

            $this->addFlash('success', 'specialisationMerged');

            return $this->redirectToRoute('club_specialisations');
        }

        return $this->render('club/specialisation/merge.html.twig', [
            'club' => $club,
            'specialisation' => $specialisation,
            'form' => $form,
        ]);
    }
}
