<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Entity\Anomaly;
use App\Form\ActivityFormType;
use App\Repository\AnomalyRepository;
use App\Security\Voter\AnomalyVoter;
use App\Service\ClubResolver;
use App\Service\Maintenance\AnomalyStatusService;
use App\Service\SubdomainService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Actions de masse sur les anomalies, réservées aux gestionnaires.
 *
 * Les anomalies non éligibles (droits ou condition métier) sont ignorées
 * silencieusement : seule la synthèse est remontée à l'utilisateur.
 */
#[Route('/anomalies/bulk', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('ROLE_USER')]
#[IsGranted('MANAGE')]
class AnomalyBulkController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly AnomalyRepository $anomalyRepository,
        private readonly AnomalyStatusService $anomalyStatusService,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('/treat', name: 'club_anomalies_bulk_treat', methods: ['POST'])]
    public function treat(Request $request): Response
    {
        return $this->handleBulk(
            $request,
            AnomalyVoter::TREAT,
            fn (Anomaly $anomaly, ?string $message): bool => $this->anomalyStatusService->handleMarkTreated($anomaly, $this->getUser(), $message),
            'anomaliesBulkTreated',
        );
    }

    #[Route('/ignore', name: 'club_anomalies_bulk_ignore', methods: ['POST'])]
    public function ignore(Request $request): Response
    {
        return $this->handleBulk(
            $request,
            AnomalyVoter::IGNORE,
            fn (Anomaly $anomaly, ?string $message): bool => $this->anomalyStatusService->handleIgnore($anomaly, $this->getUser(), $message),
            'anomaliesBulkIgnored',
        );
    }

    /**
     * @param callable(Anomaly, ?string): bool $handle
     */
    private function handleBulk(Request $request, string $attribute, callable $handle, string $successMessage): Response
    {
        // Indispensable avant queryAll() : le club n'est mis en cache que par
        // resolve(). On ne peut pas compter sur ClubVoter pour l'avoir fait —
        // son bypass admin retourne avant d'y arriver, si bien que l'action
        // fonctionnait pour un gestionnaire et cassait pour un admin.
        $this->clubResolver->resolve();

        $form = $this->createForm(ActivityFormType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'invalidRequest');

            return $this->redirectBack($request);
        }

        $ids = $this->getIdsFromRequest($request);

        if ($ids === []) {
            $this->addFlash('error', 'noAnomalySelected');

            return $this->redirectBack($request);
        }

        $message = $form->getData()['message'] ?? null;

        // Scoped to the current club by queryAll(), so no cross-tenant leak
        $anomalies = $this->anomalyRepository->queryAll()
            ->andWhere('anomaly.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $handled = 0;

        foreach ($anomalies as $anomaly) {
            if (!$this->isGranted($attribute, $anomaly)) {
                continue;
            }

            if ($handle($anomaly, $message)) {
                $handled++;
            }
        }

        if ($handled === 0) {
            $this->addFlash('error', 'noEligibleAnomalies');

            return $this->redirectBack($request);
        }

        $this->addFlash('success', $successMessage);

        return $this->redirectBack($request);
    }

    /**
     * @return int[]
     */
    private function getIdsFromRequest(Request $request): array
    {
        $rawIds = $request->request->all('anomalyIds');

        if (!is_array($rawIds)) {
            return [];
        }

        return array_values(array_unique(
            array_filter(
                array_map(static fn ($id) => (int) $id, $rawIds),
                static fn (int $id) => $id > 0
            )
        ));
    }

    private function redirectBack(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('club_anomalies');
    }
}
