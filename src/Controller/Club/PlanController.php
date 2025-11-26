<?php

namespace App\Controller\Club;

use App\Entity\Plan;
use App\Entity\PlanTask;
use App\Entity\PlanSubTask;
use App\Form\PlanImportType;
use App\Form\PlanType;
use App\Entity\Equipment;
use App\Form\PlanApplyType;
use App\Repository\Paginator;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use App\Repository\PlanRepository;
use App\Form\Filter\PlanFilterType;
use App\Controller\ExtendedController;
use App\Service\Maintenance\PlanApplier;
use App\Service\PlanSpreadsheetService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PlanApplicationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/plans', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('ROLE_USER')]
class PlanController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly PlanRepository $planRepository,
        private readonly PlanApplicationRepository $applicationRepository,
        private readonly PlanApplier $planApplier,
        private readonly EntityManagerInterface $entityManager,
        private readonly PlanSpreadsheetService $planSpreadsheetService,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_plans')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'plans'],
    ])]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // Handle filters
        $filters = $this->createFilter(PlanFilterType::class);
        $filters->handleRequest($request);

        $qb = $this->planRepository->queryByFilters($filters->getData() ?? []);
        $qb = $this->planRepository->orderByName($qb);

        $plans = Paginator::paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/plan/index.html.twig', [
            'club' => $club,
            'plans' => $plans,
            'filters' => $filters->createView(),
        ]);
    }

    #[Route('/new', name: 'club_plan_new')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'plans', 'route' => 'club_plans'],
        ['label' => 'newPlan'],
    ])]
    public function new(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $plan = new Plan();
        $plan->setClub($club);
        $plan->setCreatedBy($this->getUser());

        // Force creation of first task and its first subtask
        if ($plan->getTaskTemplates()->count() === 0) {
            $firstTask = new PlanTask();
            $firstTask->setTitle('');
            $firstTask->setDescription('');
            $firstTask->setPosition(0);
            $plan->addTaskTemplate($firstTask);

            $firstSubTask = new PlanSubTask();
            $firstSubTask->setTitle('');
            $firstSubTask->setDescription('');
            $firstSubTask->setDifficulty(3);
            $firstSubTask->setRequiresInspection(false);
            $firstSubTask->setPosition(0);
            $firstTask->addSubTaskTemplate($firstSubTask);
        }

        $form = $this->createForm(PlanType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set positions for task templates
            $position = 0;
            foreach ($plan->getTaskTemplates() as $taskTemplate) {
                $taskTemplate->setPosition($position++);

                // Set positions for subtask templates
                $subPosition = 0;
                foreach ($taskTemplate->getSubTaskTemplates() as $subTaskTemplate) {
                    $subTaskTemplate->setPosition($subPosition++);
                }
            }

            $this->entityManager->persist($plan);
            $this->entityManager->flush();

            $this->addFlash('success', 'planCreated');

            return $this->redirectToRoute('club_plan_show', ['id' => $plan->getId()]);
        }

        return $this->render('club/plan/new.html.twig', [
            'club' => $club,
            'plan' => $plan,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'club_plan_show', requirements: ['id' => '\d+'])]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'plans', 'route' => 'club_plans'],
        ['label' => '$plan.name'],
    ])]
    public function show(Plan $plan): Response
    {
        $club = $this->clubResolver->resolve();

        $importForm = null;
        if ($this->isGranted('MANAGE', $club)) {
            $importForm = $this->createForm(PlanImportType::class);
        }

        // Get applications for this plan
        $qb = $this->applicationRepository->queryAll();
        $qb = $this->applicationRepository->filterByPlan($qb, $plan);
        $qb = $this->applicationRepository->filterByCancelled($qb, false);
        $qb = $this->applicationRepository->orderByDueDate($qb, 'ASC');

        $applications = $qb->getQuery()->getResult();

        return $this->render('club/plan/show.html.twig', [
            'club' => $club,
            'plan' => $plan,
            'applications' => $applications,
            'importForm' => $importForm?->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'club_plan_edit', requirements: ['id' => '\d+'])]
    #[IsGranted('MANAGE')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'plans', 'route' => 'club_plans'],
        ['label' => '$plan.name', 'route' => 'club_plan_show', 'routeParameters' => ['id' => '$plan.id']],
        ['label' => 'edit'],
    ])]
    public function edit(Plan $plan, Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $form = $this->createForm(PlanType::class, $plan);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update positions
            $position = 0;
            foreach ($plan->getTaskTemplates() as $taskTemplate) {
                $taskTemplate->setPosition($position++);

                $subPosition = 0;
                foreach ($taskTemplate->getSubTaskTemplates() as $subTaskTemplate) {
                    $subTaskTemplate->setPosition($subPosition++);
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'planUpdated');

            return $this->redirectToRoute('club_plan_show', ['id' => $plan->getId()]);
        }

        return $this->render('club/plan/edit.html.twig', [
            'club' => $club,
            'plan' => $plan,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'club_plan_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('MANAGE')]
    public function delete(Plan $plan, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $plan->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($plan);
            $this->entityManager->flush();

            $this->addFlash('success', 'planDeleted');
        }

        return $this->redirectToRoute('club_plans');
    }

    #[Route('/{id}/apply', name: 'club_plan_apply', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('MANAGE')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'plans', 'route' => 'club_plans'],
        ['label' => '$plan.name', 'route' => 'club_plan_show', 'routeParameters' => ['id' => '$plan.id']],
        ['label' => 'apply'],
    ])]
    public function apply(Plan $plan, Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $form = $this->createForm(PlanApplyType::class, null, [
            'equipment_type' => $plan->getEquipmentType(),
            'club' => $club,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $equipment = $data['equipment'];
            $dueAt = $data['dueAt'];

            // Convert DateTime to DateTimeImmutable if needed
            if ($dueAt instanceof \DateTime) {
                $dueAt = \DateTimeImmutable::createFromMutable($dueAt);
            }

            try {
                $application = $this->planApplier->applyPlan(
                    $plan,
                    $equipment,
                    $this->getUser(),
                    $dueAt
                );

                $this->addFlash('success', 'planApplied');

                return $this->redirectToRoute('club_equipment_show', ['id' => $equipment->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        // Get timeline data if applying with a due date
        $timeline = [];
        if ($form->isSubmitted() && $form->get('dueAt')->getData()) {
            $dueAt = $form->get('dueAt')->getData();
            $equipment = $form->get('equipment')->getData();

            if ($equipment && $dueAt) {
                // Convert DateTime to DateTimeImmutable if needed
                if ($dueAt instanceof \DateTime) {
                    $dueAt = \DateTimeImmutable::createFromMutable($dueAt);
                }

                $startDate = $dueAt->modify('-2 weeks');
                $endDate = $dueAt->modify('+2 weeks');
                $timeline = $this->planApplier->getApplicationsInDateRange($equipment, $startDate, $endDate);
            }
        }

        return $this->render('club/plan/apply.html.twig', [
            'club' => $club,
            'plan' => $plan,
            'form' => $form,
            'timeline' => $timeline,
        ]);
    }

    #[Route('/{id}/export', name: 'club_plan_export', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted('MANAGE')]
    public function export(Plan $plan): Response
    {
        $spreadsheet = $this->planSpreadsheetService->generateSpreadsheet($plan);
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        $tempBase = tempnam(sys_get_temp_dir(), 'plan_export_');
        if ($tempBase === false) {
            $this->addFlash('error', 'planExportFailed');
            return $this->redirectToRoute('club_plan_show', ['id' => $plan->getId()]);
        }

        $tempFile = $tempBase . '.xlsx';

        try {
            $writer->save($tempFile);
        } catch (\Throwable $exception) {
            $this->addFlash('error', 'planExportFailed');
            @unlink($tempBase);
            return $this->redirectToRoute('club_plan_show', ['id' => $plan->getId()]);
        }

        @unlink($tempBase);

        $filename = sprintf('plan-%d.xlsx', $plan->getId());

        return $this->file($tempFile, $filename, ResponseHeaderBag::DISPOSITION_ATTACHMENT)
            ->deleteFileAfterSend(true);
    }

    #[Route('/{id}/import', name: 'club_plan_import', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('MANAGE')]
    public function import(Plan $plan, Request $request): Response
    {
        $form = $this->createForm(PlanImportType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'planImportInvalidForm');
            return $this->redirectToRoute('club_plan_show', ['id' => $plan->getId()]);
        }

        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile $file */
        $file = $form->get('file')->getData();

        $result = $this->planSpreadsheetService->importFromFile($plan, $file);

        if ($result->hasErrors()) {
            foreach ($result->getErrors() as $error) {
                $context = $this->formatImportContext($error['context'] ?? []);
                $this->addFlash('error', $this->translator->trans('planImportError.' . $error['code'], $context));
            }

            foreach ($result->getRowMessages() as $message) {
                if ($message['severity'] === 'error') {
                    $context = $this->formatImportContext($message['context'] ?? []);
                    $context['{row}'] = (string) $message['row'];
                    $this->addFlash('error', $this->translator->trans('planImportRowError.' . $message['code'], $context));
                }
            }

            return $this->redirectToRoute('club_plan_show', ['id' => $plan->getId()]);
        }

        $this->entityManager->flush();

        foreach ($result->getRowMessages() as $message) {
            if ($message['severity'] === 'warning') {
                $context = $this->formatImportContext($message['context'] ?? []);
                $context['{row}'] = (string) $message['row'];
                $this->addFlash('warning', $this->translator->trans('planImportRowWarning.' . $message['code'], $context));
            }
        }

        $this->addFlash('success', $this->translator->trans('planImportSuccess', [
            '{tasks}' => $result->getTaskCount(),
            '{subtasks}' => $result->getSubtaskCount(),
        ]));

        return $this->redirectToRoute('club_plan_show', ['id' => $plan->getId()]);
    }
    private function formatImportContext(array $context): array
    {
        $formatted = [];

        foreach ($context as $key => $value) {
            $placeholder = sprintf('{%s}', $key);
            if (is_array($value)) {
                $formatted[$placeholder] = implode(', ', array_map(static fn($item) => (string) $item, $value));
            } else {
                $formatted[$placeholder] = (string) $value;
            }
        }

        return $formatted;
    }
}

