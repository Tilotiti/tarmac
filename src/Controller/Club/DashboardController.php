<?php

namespace App\Controller\Club;

use App\Service\ClubResolver;
use App\Entity\Enum\TaskStatus;
use App\Service\SubdomainService;
use App\Repository\TaskRepository;
use App\Repository\SubTaskRepository;
use App\Repository\PurchaseRepository;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\PurchaseStatus;
use App\Controller\ExtendedController;
use App\Repository\PlanApplicationRepository;
use App\Form\WelcomeMessageType;
use Doctrine\ORM\EntityManagerInterface;
use Nucleos\DompdfBundle\Wrapper\DompdfWrapperInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www).*'])]
#[IsGranted('ROLE_USER')]
#[IsGranted('VIEW')]
class DashboardController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly TaskRepository $taskRepository,
        private readonly SubTaskRepository $subTaskRepository,
        private readonly PlanApplicationRepository $applicationRepository,
        private readonly PurchaseRepository $purchaseRepository,
        private readonly DompdfWrapperInterface $dompdf,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_dashboard')]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $user = $this->getUser();

        $isManager = $this->isGranted('MANAGE');
        $isInspector = $this->isGranted('INSPECT');
        $isPilote = $this->isGranted('PILOT');

        // === 1. SUBTASKS AWAITING INSPECTION (for inspectors only) ===
        $awaitingInspectionSubTasks = [];
        $awaitingInspectionCount = 0;
        if ($isInspector) {
            $inspectionQb = $this->subTaskRepository->createQueryBuilder('subtask')
                ->join('subtask.task', 'task')
                ->join('task.equipment', 'equipment')
                ->where('task.club = :club')
                ->setParameter('club', $club)
                ->andWhere('subtask.requiresInspection = :true')
                ->setParameter('true', true)
                ->andWhere('subtask.status = :done')
                ->setParameter('done', 'done')
                ->andWhere('subtask.doneBy IS NOT NULL')
                ->andWhere('subtask.inspectedBy IS NULL');

            // Inspectors can see subtasks on private equipment (exception to privacy rule)
            // No additional privacy filter needed for inspection subtasks

            // Get count
            $countQb = clone $inspectionQb;
            $awaitingInspectionCount = (int) $countQb->select('COUNT(DISTINCT subtask.id)')
                ->getQuery()
                ->getSingleScalarResult();

            // Get limited results - order by task dueAt with NULL values last, then by plan position
            $inspectionQb->addOrderBy('CASE WHEN task.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('task.dueAt', 'ASC')
                ->addOrderBy('CASE WHEN task.planPosition IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('task.planPosition', 'ASC')
                ->addOrderBy('CASE WHEN subtask.planPosition IS NULL THEN 1 ELSE 0 END', 'ASC')
                ->addOrderBy('subtask.planPosition', 'ASC')
                ->addOrderBy('subtask.position', 'ASC')
                ->setMaxResults(5);
            $awaitingInspectionSubTasks = $inspectionQb->getQuery()->getResult();
        }

        // === 2. PRIORITY TASKS ===
        // Show only priority tasks (tasks with priority flag or tasks with priority subtasks)
        $priorityQb = $this->taskRepository->queryAll();
        $priorityQb->join('task.equipment', 'equipment_priority')
            ->where('task.club = :club')
            ->setParameter('club', $club)
            ->andWhere('task.status IN (:taskStatuses)')
            ->setParameter('taskStatuses', ['open', 'done'])
            ->andWhere('(
                task.priority = :true 
                OR EXISTS (
                    SELECT 1 FROM App\Entity\SubTask st 
                    WHERE st.task = task AND st.priority = :true
                )
            )')
            ->setParameter('true', true);

        // Apply pilot visibility filter: non-pilotes can only see facility equipment
        if (!$isPilote && !$isManager && !$isInspector) {
            $priorityQb = $this->taskRepository->filterByFacilityEquipment($priorityQb);
        }

        // Apply privacy filter for non-managers
        if (!$isManager) {
            // Show only club equipment OR private equipment owned by the user
            $priorityQb->leftJoin('equipment_priority.owners', 'owners')
                ->andWhere('equipment_priority.owner = :owner_club OR (equipment_priority.owner = :owner_private AND owners.id = :user)')
                ->setParameter('owner_club', EquipmentOwner::CLUB)
                ->setParameter('owner_private', EquipmentOwner::PRIVATE)
                ->setParameter('user', $user->getId());
        }

        // Get count
        $priorityCountQb = clone $priorityQb;
        $priorityTasksCount = (int) $priorityCountQb->select('COUNT(DISTINCT task.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get limited results - order by dueAt with NULL values last, then by plan position
        $priorityQb->addOrderBy('CASE WHEN task.dueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('task.dueAt', 'ASC')
            ->addOrderBy('CASE WHEN task.planPosition IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('task.planPosition', 'ASC')
            ->addOrderBy('task.createdAt', 'ASC')
            ->setMaxResults(20);
        $priorityTasks = $priorityQb->getQuery()->getResult();

        // === 4. PURCHASES WAITING FOR DELIVERY ===
        $purchasesWaitingDeliveryQb = $this->purchaseRepository->queryAll();
        $purchasesWaitingDeliveryQb->andWhere('purchase.status = :purchasedStatus')
            ->setParameter('purchasedStatus', PurchaseStatus::PURCHASED)
            ->orderBy('purchase.purchasedAt', 'ASC')
            ->setMaxResults(5);

        $purchasesWaitingDelivery = $purchasesWaitingDeliveryQb->getQuery()->getResult();
        $purchasesWaitingDeliveryCount = $this->purchaseRepository->countPurchasesWaitingDelivery($club);

        return $this->render('club/dashboard.html.twig', [
            'club' => $club,
            'priorityTasks' => $priorityTasks,
            'priorityTasksCount' => $priorityTasksCount,
            'awaitingInspectionSubTasks' => $awaitingInspectionSubTasks,
            'awaitingInspectionCount' => $awaitingInspectionCount,
            'purchasesWaitingDelivery' => $purchasesWaitingDelivery,
            'purchasesWaitingDeliveryCount' => $purchasesWaitingDeliveryCount,
        ]);
    }

    #[Route('/print-priority-subtasks', name: 'club_dashboard_print_priority_subtasks', methods: ['GET'])]
    #[IsGranted('MANAGE')]
    public function printPrioritySubTasks(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $subdomain = $club->getSubdomain();

        // Query all priority subtasks
        $prioritySubTasksQb = $this->subTaskRepository->createQueryBuilder('subtask')
            ->join('subtask.task', 'task')
            ->join('task.equipment', 'equipment')
            ->where('task.club = :club')
            ->setParameter('club', $club)
            ->andWhere('subtask.priority = :true')
            ->setParameter('true', true)
            ->andWhere('subtask.status = :open')
            ->setParameter('open', 'open')
            // Order by dueAt, then by plan position (task and subtask)
            ->orderBy('task.dueAt', 'ASC')
            ->addOrderBy('CASE WHEN task.planPosition IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('task.planPosition', 'ASC')
            ->addOrderBy('CASE WHEN subtask.planPosition IS NULL THEN 1 ELSE 0 END', 'ASC')
            ->addOrderBy('subtask.planPosition', 'ASC')
            ->addOrderBy('subtask.position', 'ASC');

        $prioritySubTasks = $prioritySubTasksQb->getQuery()->getResult();

        // Include QR codes automatically if 100 or fewer subtasks (for performance)
        $includeQrCodes = count($prioritySubTasks) <= 100;

        // Build subtasks data
        $subTasksData = [];
        $qrCodeUrls = [];

        foreach ($prioritySubTasks as $index => $subTask) {
            $task = $subTask->getTask();

            $subTaskUrl = $this->urlGenerator->generate(
                'club_subtask_show',
                [
                    'subdomain' => $subdomain,
                    'taskId' => $task->getId(),
                    'id' => $subTask->getId(),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            if ($includeQrCodes) {
                $qrCodeUrls[$index] = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
                    'size' => '80x80',
                    'data' => $subTaskUrl,
                    'format' => 'png',
                    'margin' => '2',
                ]);
            }

            $subTasksData[$index] = [
                'subTask' => $subTask,
                'index' => $index + 1,
            ];
        }

        // Fetch QR codes in parallel if needed
        if ($includeQrCodes && !empty($qrCodeUrls)) {
            $qrCodeDataUris = $this->fetchQrCodesInParallel($qrCodeUrls);
            foreach ($qrCodeDataUris as $index => $dataUri) {
                $subTasksData[$index]['qrCodeUrl'] = $dataUri;
            }
        }

        // Generate QR code for club dashboard (always included)
        $clubDashboardUrl = $this->urlGenerator->generate(
            'club_dashboard',
            ['subdomain' => $subdomain],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        $clubQrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => '100x100',
            'data' => $clubDashboardUrl,
            'format' => 'png',
            'margin' => '2',
        ]);

        $html = $this->renderView('club/dashboard/printPrioritySubtasks.html.twig', [
            'club' => $club,
            'subTasks' => $subTasksData,
            'includeQrCodes' => $includeQrCodes,
            'clubQrCodeUrl' => $clubQrCodeUrl,
        ]);

        if ($request->query->getBoolean('preview')) {
            return new Response($html);
        }

        $filename = sprintf('sous-taches-prioritaires-%s.pdf', $club->getSubdomain());

        $response = $this->dompdf->getStreamResponse($html, $filename, [
            'format' => 'A4',
            'orientation' => 'landscape',
            'margin-top' => '20mm',
            'margin-right' => '15mm',
            'margin-bottom' => '20mm',
            'margin-left' => '15mm',
            'Attachment' => false,
        ]);

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/edit-welcome-message', name: 'club_dashboard_edit_welcome_message', methods: ['GET', 'POST'])]
    #[IsGranted('MANAGE')]
    public function editWelcomeMessage(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $form = $this->createForm(WelcomeMessageType::class, ['welcomeMessage' => $club->getWelcomeMessage()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $club->setWelcomeMessage($data['welcomeMessage'] ?? null);
            $this->entityManager->flush();

            $this->addFlash('success', 'welcomeMessageUpdated');

            return $this->redirectToRoute('club_dashboard');
        }

        return $this->render('club/dashboard/editWelcomeMessage.html.twig', [
            'club' => $club,
            'form' => $form,
        ]);
    }

    /**
     * Fetch multiple QR codes in parallel using multi-curl and return as data URIs.
     * 
     * @param array<int, string> $urls
     * @return array<int, string>
     */
    private function fetchQrCodesInParallel(array $urls): array
    {
        if (empty($urls)) {
            return [];
        }

        $multiHandle = curl_multi_init();
        $handles = [];

        // Initialize all curl handles
        foreach ($urls as $index => $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            curl_multi_add_handle($multiHandle, $ch);
            $handles[$index] = $ch;
        }

        // Execute all requests in parallel
        do {
            $status = curl_multi_exec($multiHandle, $active);
            if ($active) {
                curl_multi_select($multiHandle);
            }
        } while ($active && $status === CURLM_OK);

        // Collect results
        $results = [];
        foreach ($handles as $index => $ch) {
            $content = curl_multi_getcontent($ch);
            if ($content !== false && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
                $results[$index] = 'data:image/png;base64,' . base64_encode($content);
            } else {
                // Fallback to URL if fetch failed
                $results[$index] = $urls[$index];
            }
            curl_multi_remove_handle($multiHandle, $ch);
            curl_close($ch);
        }

        curl_multi_close($multiHandle);

        return $results;
    }
}

