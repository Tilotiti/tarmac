<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Form\Import\ImportFileType;
use App\Service\ClubResolver;
use App\Service\Import\ColumnMapper;
use App\Service\Import\HeaderDetector;
use App\Service\Import\ImportContext;
use App\Service\Import\ImportDefinitionInterface;
use App\Service\Import\ImportDefinitionRegistry;
use App\Service\Import\ImportField;
use App\Service\Import\ImportFileStorage;
use App\Service\Import\ImportReadException;
use App\Service\Import\ImportRowResolver;
use App\Service\Import\ImportSheet;
use App\Service\Import\ImportState;
use App\Service\Import\ImportStateStore;
use App\Service\Import\SpreadsheetReader;
use App\Service\Import\ValueMapper;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Assistant d'import en trois écrans (fichier, colonnes [+ valeurs], aperçu), générique :
 * la définition ({definition}) fournit les champs, la validation et l'écriture.
 */
#[Route('/import/{definition}', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*', 'definition' => 'equipments|invitations'])]
#[IsGranted('MANAGE')]
class ImportController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly ImportDefinitionRegistry $registry,
        private readonly ImportFileStorage $storage,
        private readonly ImportStateStore $stateStore,
        private readonly SpreadsheetReader $reader,
        private readonly HeaderDetector $headerDetector,
        private readonly ColumnMapper $columnMapper,
        private readonly ValueMapper $valueMapper,
        private readonly ImportRowResolver $resolver,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_import_file', methods: ['GET', 'POST'])]
    #[Breadcrumb([['label' => 'home', 'route' => 'club_dashboard'], ['label' => 'import']])]
    public function uploadFile(Request $request, string $definition): Response
    {
        $club = $this->clubResolver->resolve();
        $def = $this->definition($definition);
        $form = $this->createForm(ImportFileType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $upload */
            $upload = $form->get('file')->getData();
            $extension = strtolower($upload->getClientOriginalExtension());
            try {
                $sheet = $this->reader->read((string) file_get_contents($upload->getPathname()), $extension);
            } catch (ImportReadException $exception) {
                $form->get('file')->addError(new FormError($this->trans('import' . ucfirst($exception->getMessage()))));

                return $this->render('club/import/file.html.twig', ['club' => $club, 'definition' => $def, 'form' => $form]);
            }
            if ($sheet->rowCount() === 0) {
                $form->get('file')->addError(new FormError($this->trans('importEmptyFile')));

                return $this->render('club/import/file.html.twig', ['club' => $club, 'definition' => $def, 'form' => $form]);
            }

            // Un import précédent abandonné : on efface son fichier
            if ($previous = $this->stateStore->get($club, $definition)) {
                $this->storage->delete($previous->fileKey);
            }
            $key = $this->storage->store($upload, $club);
            $state = new ImportState($definition, $key, $upload->getClientOriginalName(), $extension, $this->headerDetector->looksLikeHeader($sheet));
            $state->columns = $this->columnMapper->suggest($sheet->columnLabels($state->hasHeader), $def->fields());
            $this->stateStore->save($club, $state);

            return $this->redirectToRoute('club_import_mapping', ['definition' => $definition]);
        }

        return $this->render('club/import/file.html.twig', ['club' => $club, 'definition' => $def, 'form' => $form]);
    }

    #[Route('/colonnes', name: 'club_import_mapping', methods: ['GET', 'POST'])]
    #[Breadcrumb([['label' => 'home', 'route' => 'club_dashboard'], ['label' => 'import']])]
    public function mapping(Request $request, string $definition): Response
    {
        $club = $this->clubResolver->resolve();
        $def = $this->definition($definition);
        $state = $this->stateStore->get($club, $definition);
        if ($state === null) {
            return $this->redirectToRoute('club_import_file', ['definition' => $definition]);
        }
        $sheet = $this->sheet($state);

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'import-mapping');
            $hasHeader = $request->request->getBoolean('hasHeader');
            if ($request->request->has('toggleHeader')) {
                // La case a changé : on recalcule les suggestions et on réaffiche
                $state->hasHeader = $hasHeader;
                $state->columns = $this->columnMapper->suggest($sheet->columnLabels($hasHeader), $def->fields());
                $this->stateStore->save($club, $state);

                return $this->redirectToRoute('club_import_mapping', ['definition' => $definition]);
            }

            $state->hasHeader = $hasHeader;
            $columns = $request->request->all('columns');
            $fixed = $request->request->all('fixed');
            $errors = [];
            $used = [];
            foreach ($def->fields() as $field) {
                $raw = $columns[$field->key] ?? '';
                $index = $raw === '' ? null : (int) $raw;
                if ($index !== null && ($index < 0 || $index >= $sheet->columnCount())) {
                    $index = null;
                }
                if ($index !== null && isset($used[$index])) {
                    $errors[] = $this->trans('importColumnUsedTwice', ['column' => $sheet->columnLabels($hasHeader)[$index]]);
                }
                if ($index !== null) {
                    $used[$index] = true;
                }
                $state->columns[$field->key] = $index;
                if ($field->hasChoices()) {
                    $state->fixed[$field->key] = (string) ($fixed[$field->key] ?? ($field->default ?? ''));
                    if ($index === null && $field->required && $state->fixed[$field->key] === '') {
                        $errors[] = $this->trans('importFixedValueRequired', ['field' => $this->trans($field->labelKey)]);
                    }
                } elseif ($index === null && $field->required) {
                    $errors[] = $this->trans('importColumnRequired', ['field' => $this->trans($field->labelKey)]);
                }
            }

            if ($errors === []) {
                $this->stateStore->save($club, $state);
                $needsValues = false;
                foreach ($def->fields() as $field) {
                    if ($field->hasChoices() && $state->columnFor($field->key) !== null) {
                        $needsValues = true;
                        $distinct = $sheet->distinctValues($state->columnFor($field->key), 20, $state->hasHeader);
                        $suggested = $this->valueMapper->suggest($distinct, $field);
                        // Conserver les choix déjà faits pour les valeurs encore présentes
                        $state->values[$field->key] = array_replace($suggested, array_intersect_key($state->values[$field->key] ?? [], $suggested));
                    } else {
                        unset($state->values[$field->key]);
                    }
                }
                $this->stateStore->save($club, $state);

                return $this->redirectToRoute($needsValues ? 'club_import_values' : 'club_import_preview', ['definition' => $definition]);
            }
            foreach ($errors as $error) {
                $this->addFlash('danger', $error);
            }
        }

        return $this->render('club/import/mapping.html.twig', [
            'club' => $club,
            'definition' => $def,
            'state' => $state,
            'labels' => $sheet->columnLabels($state->hasHeader),
            'samples' => $this->samples($sheet, $state->hasHeader),
        ], $request->isMethod('POST') ? new Response(status: 422) : null);
    }

    #[Route('/valeurs', name: 'club_import_values', methods: ['GET', 'POST'])]
    #[Breadcrumb([['label' => 'home', 'route' => 'club_dashboard'], ['label' => 'import']])]
    public function values(Request $request, string $definition): Response
    {
        $club = $this->clubResolver->resolve();
        $def = $this->definition($definition);
        $state = $this->stateStore->get($club, $definition);
        if ($state === null || $state->values === []) {
            return $this->redirectToRoute('club_import_mapping', ['definition' => $definition]);
        }

        if ($request->isMethod('POST')) {
            $this->checkCsrf($request, 'import-values');
            $submitted = $request->request->all('values');
            foreach ($state->values as $fieldKey => $map) {
                $field = $this->field($def, $fieldKey);
                foreach ($map as $raw => $current) {
                    $choice = (string) ($submitted[$fieldKey][$raw] ?? '');
                    $state->values[$fieldKey][$raw] = $choice !== '' && $field?->choice($choice) !== null ? $choice : null;
                }
            }
            $this->stateStore->save($club, $state);

            return $this->redirectToRoute('club_import_preview', ['definition' => $definition]);
        }

        return $this->render('club/import/values.html.twig', ['club' => $club, 'definition' => $def, 'state' => $state]);
    }

    #[Route('/apercu', name: 'club_import_preview', methods: ['GET'])]
    #[Breadcrumb([['label' => 'home', 'route' => 'club_dashboard'], ['label' => 'import']])]
    public function preview(string $definition): Response
    {
        $club = $this->clubResolver->resolve();
        $def = $this->definition($definition);
        $state = $this->stateStore->get($club, $definition);
        if ($state === null) {
            return $this->redirectToRoute('club_import_file', ['definition' => $definition]);
        }
        $preview = $this->resolver->resolve($this->sheet($state), $state, $def, new ImportContext($club, $this->getUser()));

        return $this->render('club/import/preview.html.twig', ['club' => $club, 'definition' => $def, 'state' => $state, 'preview' => $preview]);
    }

    #[Route('/confirmer', name: 'club_import_confirm', methods: ['POST'])]
    public function confirm(Request $request, string $definition): Response
    {
        $club = $this->clubResolver->resolve();
        $def = $this->definition($definition);
        $this->checkCsrf($request, 'import-confirm');
        $state = $this->stateStore->get($club, $definition);
        if ($state === null) {
            return $this->redirectToRoute('club_import_file', ['definition' => $definition]);
        }

        $context = new ImportContext($club, $this->getUser(), $request->request->all('rows'));
        $preview = $this->resolver->resolve($this->sheet($state), $state, $def, $context);

        $this->entityManager->wrapInTransaction(function () use ($preview, $def, $context): void {
            foreach ($preview->importable() as $row) {
                $def->import($row->values, $context);
            }
        });

        $this->storage->delete($state->fileKey);
        $this->stateStore->clear($club, $definition);
        $this->addFlash('success', $this->trans('importDone', ['imported' => $preview->importableCount(), 'ignored' => $preview->ignoredCount()]));

        return $this->redirectToRoute($def->listRoute());
    }

    #[Route('/annuler', name: 'club_import_cancel', methods: ['POST'])]
    public function cancel(Request $request, string $definition): Response
    {
        $club = $this->clubResolver->resolve();
        $def = $this->definition($definition);
        $this->checkCsrf($request, 'import-cancel');
        if ($state = $this->stateStore->get($club, $definition)) {
            $this->storage->delete($state->fileKey);
            $this->stateStore->clear($club, $definition);
        }

        return $this->redirectToRoute($def->listRoute());
    }

    private function definition(string $key): ImportDefinitionInterface
    {
        return $this->registry->get($key) ?? throw $this->createNotFoundException();
    }

    private function field(ImportDefinitionInterface $definition, string $key): ?ImportField
    {
        foreach ($definition->fields() as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    private function sheet(ImportState $state): ImportSheet
    {
        return $this->reader->read($this->storage->read($state->fileKey), $state->extension);
    }

    /** @return array<int, string[]> trois valeurs d'exemple par colonne, pour l'aperçu sous chaque menu */
    private function samples(ImportSheet $sheet, bool $hasHeader): array
    {
        $samples = [];
        for ($i = 0; $i < $sheet->columnCount(); $i++) {
            $samples[$i] = $sheet->sample($i, 3, $hasHeader);
        }

        return $samples;
    }

    private function checkCsrf(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
    }

    private function trans(string $key, array $parameters = []): string
    {
        return $this->translator->trans($key, $parameters);
    }
}
