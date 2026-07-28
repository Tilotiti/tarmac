<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Activity;
use App\Entity\Anomaly;
use App\Entity\Enum\ActivityType;
use App\Entity\Enum\AnomalyImpact;
use App\Entity\Enum\AnomalyStatus;
use App\Entity\Equipment;
use App\Entity\Task;
use App\Form\ActivityFormType;
use App\Form\AnomalyLinkTaskType;
use App\Form\AnomalyType;
use App\Form\Filter\AnomalyFilterType;
use App\Repository\AnomalyRepository;
use App\Repository\Paginator;
use App\Security\Voter\AnomalyVoter;
use App\Service\ClubResolver;
use App\Service\CommentNotificationService;
use App\Service\Maintenance\AnomalyStatusService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Tiloweb\UploadedFileTypeBundle\UploadedFileTypeService;

#[Route('/anomalies', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('ROLE_USER')]
class AnomalyController extends ExtendedController
{
    private const UPLOAD_CONFIGURATION = 'anomaly';

    private const MAX_PHOTO_BYTES = 10 * 1024 * 1024;

    /**
     * Formats raster uniquement : on écarte notamment le SVG, qui peut embarquer
     * du script.
     */
    private const ALLOWED_PHOTO_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/heic',
        'image/heif',
    ];

    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly AnomalyRepository $anomalyRepository,
        private readonly AnomalyStatusService $anomalyStatusService,
        private readonly EntityManagerInterface $entityManager,
        private readonly Filesystem $s3Filesystem,
        private readonly UploadedFileTypeService $uploadedFileTypeService,
        private readonly CommentNotificationService $commentNotificationService,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_anomalies')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'anomalies'],
    ])]
    public function index(Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        // Treated and ignored anomalies are hidden by default, but the filter stays clearable
        $filters = $this->createFilter(AnomalyFilterType::class, ['status' => AnomalyStatus::getDefaultFilter()], [
            'club' => $club,
        ]);
        $filters->handleRequest($request);

        $qb = $this->anomalyRepository->queryByFilters($filters->getData() ?? []);
        $qb = $this->anomalyRepository->orderByRelevance($qb);

        $anomalies = Paginator::paginate(
            $qb,
            $request->query->getInt('page', 1),
            20
        );

        return $this->render('club/anomaly/index.html.twig', [
            'club' => $club,
            'anomalies' => $anomalies,
            'filters' => $filters->createView(),
            // Two distinct instances so each modal renders its own field ids
            'bulkResolutionForm' => $this->createForm(ActivityFormType::class)->createView(),
            'bulkIgnoreForm' => $this->createForm(ActivityFormType::class)->createView(),
        ]);
    }

    #[Route('/new', name: 'club_anomaly_new')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'anomalies', 'route' => 'club_anomalies'],
        ['label' => 'newAnomaly'],
    ])]
    public function new(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $canManage = $this->isGranted('MANAGE', $club);

        $anomaly = new Anomaly();
        $anomaly->setClub($club);
        $anomaly->setCreatedBy($this->getUser());
        $anomaly->setReportedBy($this->getUser());

        // Coming from an equipment page: the equipment is imposed
        $equipment = $this->resolveRequestedEquipment($request, $club);

        if ($equipment !== null) {
            $anomaly->setEquipment($equipment);
        }

        $form = $this->createForm(AnomalyType::class, $anomaly, [
            'user' => $this->getUser(),
            'club' => $club,
            'can_manage' => $canManage,
            'lock_equipment' => $equipment !== null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Non-managers always report in their own name
            if (!$canManage) {
                $anomaly->setReportedBy($this->getUser());
            }

            if (!$this->isGranted(AnomalyVoter::VIEW, $anomaly)) {
                $this->addFlash('error', 'accessDenied');

                return $this->redirectToRoute('club_anomalies');
            }

            $this->entityManager->persist($anomaly);
            $this->entityManager->flush();

            $this->storeUploadedPhotos($anomaly, $this->collectPhotos($request));
            $this->anomalyStatusService->handleCreate($anomaly, $this->getUser());

            $this->addFlash('success', 'anomalyCreatedFlash');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        return $this->render('club/anomaly/new.html.twig', [
            'club' => $club,
            'anomaly' => $anomaly,
            'equipment' => $equipment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'club_anomaly_show', requirements: ['id' => '\d+'])]
    #[IsGranted(AnomalyVoter::VIEW, 'anomaly')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'anomalies', 'route' => 'club_anomalies'],
        ['label' => '$anomaly.title'],
    ])]
    public function show(Anomaly $anomaly): Response
    {
        $club = $this->clubResolver->resolve();

        $commentForm = null;
        if ($this->isGranted(AnomalyVoter::COMMENT, $anomaly)) {
            $commentForm = $this->createForm(ActivityFormType::class, null, [
                'label' => 'comment',
                'placeholder' => 'addComment',
                'required' => true,
            ])->createView();
        }

        $linkTaskForm = null;
        if ($this->isGranted(AnomalyVoter::LINK_TASK, $anomaly)) {
            $linkTaskForm = $this->createForm(AnomalyLinkTaskType::class, null, [
                'anomaly' => $anomaly,
            ])->createView();
        }

        return $this->render('club/anomaly/show.html.twig', [
            'club' => $club,
            'anomaly' => $anomaly,
            'anomalyImpacts' => AnomalyImpact::cases(),
            'commentForm' => $commentForm,
            'linkTaskForm' => $linkTaskForm,
            // One instance per modal so each renders its own field ids
            'treatForm' => $this->createForm(ActivityFormType::class)->createView(),
            'ignoreForm' => $this->createForm(ActivityFormType::class)->createView(),
            'reopenForm' => $this->createForm(ActivityFormType::class)->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'club_anomaly_edit', requirements: ['id' => '\d+'])]
    #[IsGranted(AnomalyVoter::EDIT, 'anomaly')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'anomalies', 'route' => 'club_anomalies'],
        ['label' => '$anomaly.title', 'route' => 'club_anomaly_show', 'routeParameters' => ['id' => '$anomaly.id']],
        ['label' => 'edit'],
    ])]
    public function edit(Anomaly $anomaly, Request $request): Response
    {
        $club = $this->clubResolver->resolve();

        $form = $this->createForm(AnomalyType::class, $anomaly, [
            'user' => $this->getUser(),
            'club' => $club,
            'can_manage' => $this->isGranted('MANAGE', $club),
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->storeUploadedPhotos($anomaly, $this->collectPhotos($request));
            $this->anomalyStatusService->handleEdit($anomaly, $this->getUser());

            $this->addFlash('success', 'anomalyUpdated');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        return $this->render('club/anomaly/edit.html.twig', [
            'club' => $club,
            'anomaly' => $anomaly,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/impact', name: 'club_anomaly_impact', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnomalyVoter::CHANGE_IMPACT, 'anomaly')]
    public function changeImpact(Anomaly $anomaly, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('anomaly_impact_' . $anomaly->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        $impact = AnomalyImpact::tryFrom((string) $request->request->get('impact'));

        if ($impact === null) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        $this->anomalyStatusService->handleChangeImpact($anomaly, $impact, $this->getUser());

        $this->addFlash('success', 'anomalyImpactUpdated');

        return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
    }

    #[Route('/{id}/treat', name: 'club_anomaly_treat', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnomalyVoter::TREAT, 'anomaly')]
    public function treat(Anomaly $anomaly, Request $request): Response
    {
        $form = $this->createForm(ActivityFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        if ($this->anomalyStatusService->handleMarkTreated($anomaly, $this->getUser(), $form->getData()['message'] ?? null)) {
            $this->addFlash('success', 'anomalyMarkedAsTreated');
        } else {
            $this->addFlash('error', 'anomalyCannotBeTreated');
        }

        return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
    }

    #[Route('/{id}/ignore', name: 'club_anomaly_ignore', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnomalyVoter::IGNORE, 'anomaly')]
    public function ignore(Anomaly $anomaly, Request $request): Response
    {
        $form = $this->createForm(ActivityFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        if ($this->anomalyStatusService->handleIgnore($anomaly, $this->getUser(), $form->getData()['message'] ?? null)) {
            $this->addFlash('success', 'anomalyIgnoredFlash');
        } else {
            $this->addFlash('error', 'invalidRequest');
        }

        return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
    }

    #[Route('/{id}/reopen', name: 'club_anomaly_reopen', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnomalyVoter::REOPEN, 'anomaly')]
    public function reopen(Anomaly $anomaly, Request $request): Response
    {
        $form = $this->createForm(ActivityFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        if ($this->anomalyStatusService->handleReopen($anomaly, $this->getUser(), $form->getData()['message'] ?? null)) {
            $this->addFlash('success', 'anomalyReopenedFlash');
        } else {
            $this->addFlash('error', 'invalidRequest');
        }

        return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
    }

    #[Route('/{id}/comment', name: 'club_anomaly_comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnomalyVoter::COMMENT, 'anomaly')]
    public function addComment(Anomaly $anomaly, Request $request): Response
    {
        $form = $this->createForm(ActivityFormType::class, null, ['required' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $activity = new Activity();
            $activity->setAnomaly($anomaly);
            $activity->setType(ActivityType::COMMENT);
            $activity->setUser($this->getUser());
            $activity->setMessage($form->getData()['message']);

            $this->entityManager->persist($activity);
            $this->entityManager->flush();

            $this->commentNotificationService->sendAnomalyCommentNotifications($anomaly, $activity, $this->getUser());

            $this->addFlash('success', 'commentAdded');
        }

        return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
    }

    #[Route('/{id}/tasks/link', name: 'club_anomaly_link_task', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnomalyVoter::LINK_TASK, 'anomaly')]
    public function linkTask(Anomaly $anomaly, Request $request): Response
    {
        $form = $this->createForm(AnomalyLinkTaskType::class, null, ['anomaly' => $anomaly]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        /** @var Task $task */
        $task = $form->getData()['task'];

        // The query builder already scopes to the equipment, this guards against tampering
        if ($task->getEquipment() !== $anomaly->getEquipment()) {
            $this->addFlash('error', 'anomalyTaskEquipmentMismatch');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        $this->anomalyStatusService->handleLinkTask($anomaly, $task, $this->getUser());

        $this->addFlash('success', 'anomalyTaskLinkedFlash');

        return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
    }

    #[Route('/{id}/tasks/{taskId}/unlink', name: 'club_anomaly_unlink_task', requirements: ['id' => '\d+', 'taskId' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnomalyVoter::LINK_TASK, 'anomaly')]
    public function unlinkTask(Anomaly $anomaly, int $taskId, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('anomaly_unlink_task_' . $anomaly->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        $task = $anomaly->getTasks()->findFirst(
            static fn (int $key, Task $candidate): bool => $candidate->getId() === $taskId
        );

        if ($task === null) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        $this->anomalyStatusService->handleUnlinkTask($anomaly, $task, $this->getUser());

        $this->addFlash('success', 'anomalyTaskUnlinkedFlash');

        return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
    }

    #[Route('/{id}/photos', name: 'club_anomaly_add_photos', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnomalyVoter::EDIT, 'anomaly')]
    public function addPhotos(Anomaly $anomaly, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('anomaly_add_photos_' . $anomaly->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        $added = $this->storeUploadedPhotos($anomaly, $this->collectPhotos($request));

        if ($added === 0) {
            $this->addFlash('warning', 'anomalyNoPhotoUploaded');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        $this->anomalyStatusService->handleEdit($anomaly, $this->getUser());

        $this->addFlash('success', 'anomalyPhotosAdded');

        return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
    }

    #[Route('/{id}/photos/delete', name: 'club_anomaly_delete_photo', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(AnomalyVoter::EDIT, 'anomaly')]
    public function deletePhoto(Anomaly $anomaly, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('anomaly_delete_photo_' . $anomaly->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        $url = (string) $request->request->get('url');

        if (!in_array($url, $anomaly->getPhotos(), true)) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
        }

        $this->deleteFromStorage($url);

        $anomaly->removePhoto($url);
        $this->anomalyStatusService->handleEdit($anomaly, $this->getUser());

        $this->addFlash('success', 'anomalyPhotoDeleted');

        return $this->redirectToRoute('club_anomaly_show', ['id' => $anomaly->getId()]);
    }

    /**
     * Photos submitted with the request, validated.
     *
     * The photo input is deliberately a raw HTML field rather than a Symfony
     * FileType: the uploaded-file-type bundle extends every FileType and its
     * POST_SUBMIT listener hands the submitted value to a service typed against a
     * single UploadedFile, which throws a TypeError as soon as `multiple` is on.
     * Reading the files here keeps the bundle out of the way — at the cost of
     * validating them ourselves.
     *
     * @return UploadedFile[]
     */
    private function collectPhotos(Request $request): array
    {
        $files = $request->files->all('photoFiles');
        $accepted = [];
        $rejected = 0;

        array_walk_recursive($files, static function ($file) use (&$accepted, &$rejected): void {
            if (!$file instanceof UploadedFile) {
                return;
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                // Notamment UPLOAD_ERR_INI_SIZE quand le fichier dépasse la limite PHP
                $rejected++;

                return;
            }

            if ($file->getSize() > self::MAX_PHOTO_BYTES || !in_array($file->getMimeType(), self::ALLOWED_PHOTO_MIME_TYPES, true)) {
                $rejected++;

                return;
            }

            $accepted[] = $file;
        });

        if ($rejected > 0) {
            $this->addFlash('warning', 'anomalyPhotosRejected');
        }

        return $accepted;
    }

    /**
     * Upload the given files to S3 and append their URLs to the anomaly.
     *
     * @param UploadedFile[] $files
     *
     * @return int number of photos actually stored
     */
    private function storeUploadedPhotos(Anomaly $anomaly, array $files): int
    {
        $added = 0;

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || $file->getError() !== UPLOAD_ERR_OK) {
                continue;
            }

            $extension = $file->guessExtension() ?: 'jpg';
            $filename = sprintf('%s.%s.%s', $anomaly->getId(), md5(microtime() . random_int(0, 1000)), $extension);

            $url = $this->uploadedFileTypeService->upload($filename, $file, self::UPLOAD_CONFIGURATION);

            if ($url) {
                $anomaly->addPhoto($url);
                $added++;
            }
        }

        if ($added > 0) {
            $this->entityManager->flush();
        }

        return $added;
    }

    private function deleteFromStorage(string $url): void
    {
        $filePath = parse_url($url, PHP_URL_PATH);

        if (!$filePath) {
            return;
        }

        try {
            $filePath = ltrim($filePath, '/');

            if ($this->s3Filesystem->fileExists($filePath)) {
                $this->s3Filesystem->delete($filePath);
            }
        } catch (\Throwable) {
            // Ignore storage deletion errors, we still drop the reference.
        }
    }

    private function resolveRequestedEquipment(Request $request, mixed $club): ?Equipment
    {
        $equipmentId = $request->query->getInt('equipment');

        if ($equipmentId <= 0) {
            return null;
        }

        $equipment = $this->entityManager->getRepository(Equipment::class)->find($equipmentId);

        if (!$equipment instanceof Equipment || $equipment->getClub() !== $club) {
            return null;
        }

        return $equipment;
    }
}
