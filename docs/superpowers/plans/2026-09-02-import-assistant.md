# Assistant d'import (matériel, invitations) — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un gestionnaire importe son matériel (puis ses membres) depuis un CSV/XLS/XLSX quelconque, en rapprochant les colonnes avec un aperçu des valeurs, fichier stocké sur S3, rien d'écrit avant confirmation.

**Architecture:** Un assistant générique en trois écrans (`Club\ImportController` + templates `club/import/*`) piloté par une définition d'import (`ImportDefinitionInterface`) ; services purs testables : `SpreadsheetReader`, `HeaderDetector`, `ColumnMapper`, `ValueMapper`, `ImportRowResolver` ; état en session (`ImportState`), fichier sur S3 (`ImportFileStorage`). Deux définitions : matériel, invitations.

**Tech Stack:** Symfony 7.4, PHP 8.5, PhpSpreadsheet 5, Flysystem S3 (oneup), `league/flysystem-memory` en test, Twig + Tabler, Stimulus, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-02-import-assistant-design.md`.

## Global Constraints

- Le fichier importé n'est jamais écrit sur le disque du serveur ni gardé en session : S3 via `oneup_flysystem.s3_filesystem` sous `imports/<club id>/<uuid>.<ext>`, adaptateur mémoire en `test`.
- Taille max 2 Mo, 2 000 lignes de données, aperçu de détection sur 200 lignes ; CSV séparateur `;`, `,` ou tabulation, encodage UTF-8 ou Windows-1252.
- Aucune écriture en base avant la confirmation de l'écran 3 ; import dans une transaction.
- `#[IsGranted('MANAGE')]` sur tout l'assistant ; contrôleurs étendent `ExtendedController`.
- Traductions : clés `lowerCamelCase` ajoutées à la main en fin de `<body>` de `translations/messages+intl-icu.fr.xlf` (prochain id : `grep -o 'trans-unit id="[0-9]*"' … | tail -1`), sous un commentaire XML ; jamais `translation:extract`. Aucune chaîne en dur dans les templates de l'app (le guide est en prose).
- Templates camelCase, mobile-first, cibles ≥ 44 px, breadcrumb sur les pages L3 (`home` en premier).
- Sortie de tests pristine (2 notices préexistantes dans `PlanSpreadsheetServiceTest`) ; `createStub()` pour les stubs ; tests fonctionnels sur `FunctionalTestCase` (`createUser`, `createClub`, `entityManager()`, hôte club `https://<sub>.<domain>`), redirections même hôte assertées en chemin relatif, formulaire invalide → 422.
- Commits directement sur `main`, messages en français (conventional commits) terminés par `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`. Ne jamais indexer `.gitignore`, `.claude/`, `.harness-mem/`, `.env.test.local`.
- Guide utilisateur mis à jour dans le même commit que la fonctionnalité.

## Fichiers

Créés : `src/Service/Import/SpreadsheetReader.php`, `ImportSheet.php`, `HeaderDetector.php`, `ImportFileStorage.php`, `ImportField.php`, `ImportChoice.php`, `ImportDefinitionInterface.php`, `ImportDefinitionRegistry.php`, `ImportState.php`, `ImportStateStore.php`, `ColumnMapper.php`, `ValueMapper.php`, `ImportRowResolver.php`, `ImportPreview.php`, `ImportPreviewRow.php`, `Definition/EquipmentImportDefinition.php`, `Definition/InvitationImportDefinition.php` ; `src/Command/PurgeImportsCommand.php` ; `src/Controller/Club/ImportController.php` ; `src/Form/Import/ImportFileType.php` ; `templates/club/import/file.html.twig`, `mapping.html.twig`, `preview.html.twig` ; `assets/controllers/import_mapping_controller.js` ; `public/downloads/modele-import-materiel.csv`, `modele-import-membres.csv` ; `config/packages/oneup_flysystem.yaml` (when@test) ; tests sous `tests/Service/Import/`, `tests/Functional/ImportEquipmentTest.php`, `ImportInvitationTest.php`, fixtures `tests/fixtures/imports/`.

Modifiés : `templates/club/equipment/index.html.twig`, `templates/club/members/invitations/index.html.twig`, `src/Controller/Club/InvitationController.php` (suppression de `import`), `templates/public/guide/index.html.twig`, `translations/messages+intl-icu.fr.xlf`, `composer.json`.

Supprimés (tâche 6) : `src/Service/InvitationImportService.php`, `src/Form/InvitationImportType.php`, `templates/club/members/invitations/import.html.twig`.

---

### Task 1 : lecture du fichier et détection d'en-tête

**Files:**
- Create: `src/Service/Import/SpreadsheetReader.php`, `src/Service/Import/ImportSheet.php`, `src/Service/Import/HeaderDetector.php`
- Test: `tests/Service/Import/SpreadsheetReaderTest.php`, `tests/Service/Import/HeaderDetectorTest.php`, fixtures `tests/fixtures/imports/materiel-semicolon.csv`, `materiel-comma-noheader.csv`, `materiel-cp1252.csv`

**Interfaces:**
- Produces: `SpreadsheetReader::read(string $contents, string $extension): ImportSheet` (extension `csv|xls|xlsx`, exception `ImportReadException` sinon) ; `ImportSheet` (`rows(): array<int, array<int, string>>` rectangulaires, `columnCount(): int`, `rowCount(): int`, `column(int $index): array`, `sample(int $index, int $limit = 3): array` valeurs non vides distinctes, `distinctValues(int $index, int $limit = 20): array`, `columnLabels(bool $hasHeader): array<int, string>` : en-têtes ou « Colonne A » via la clé `importColumnLetter`… non : libellé brut `A`, `B`, la traduction se fait dans le template) ; `HeaderDetector::looksLikeHeader(ImportSheet $sheet): bool`.

- [ ] **Step 1 : fixtures et tests**

`tests/fixtures/imports/materiel-semicolon.csv` (UTF-8) :

```csv
Immatriculation;Type;Modèle;Propriétaire
F-CGAB;Planeur;ASK 21;Club
F-CHXY;Planeur;Pégase;Privé
F-GKLM;Avion;DR 400;Club
Treuil n°2;Treuil;;Club
```

`tests/fixtures/imports/materiel-comma-noheader.csv` :

```csv
F-CGAB,Planeur,ASK 21
F-CHXY,Planeur,Pégase
```

`tests/fixtures/imports/materiel-cp1252.csv` : les deux lignes `Nom;Type` puis `Pégase;Planeur` encodées en Windows-1252 (générer avec `php -r 'file_put_contents("tests/fixtures/imports/materiel-cp1252.csv", mb_convert_encoding("Nom;Type\nPégase;Planeur\n", "Windows-1252", "UTF-8"));'`).

`tests/Service/Import/SpreadsheetReaderTest.php` :

```php
<?php

namespace App\Tests\Service\Import;

use App\Service\Import\ImportReadException;
use App\Service\Import\SpreadsheetReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class SpreadsheetReaderTest extends TestCase
{
    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../../fixtures/imports/' . $name);
    }

    public function testReadsSemicolonCsv(): void
    {
        $sheet = (new SpreadsheetReader())->read($this->fixture('materiel-semicolon.csv'), 'csv');

        $this->assertSame(5, $sheet->rowCount());
        $this->assertSame(4, $sheet->columnCount());
        $this->assertSame(['Immatriculation', 'Type', 'Modèle', 'Propriétaire'], $sheet->rows()[0]);
        $this->assertSame('', $sheet->rows()[4][2]);
    }

    public function testReadsCommaCsvWithoutHeader(): void
    {
        $sheet = (new SpreadsheetReader())->read($this->fixture('materiel-comma-noheader.csv'), 'csv');

        $this->assertSame(2, $sheet->rowCount());
        $this->assertSame(['F-CGAB', 'Planeur', 'ASK 21'], $sheet->rows()[0]);
    }

    public function testConvertsWindows1252(): void
    {
        $sheet = (new SpreadsheetReader())->read($this->fixture('materiel-cp1252.csv'), 'csv');

        $this->assertSame('Pégase', $sheet->rows()[1][0]);
    }

    public function testReadsXlsx(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([['Nom', 'Type'], ['F-CGAB', 'Planeur'], [null, null]]);
        $path = tempnam(sys_get_temp_dir(), 'import') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $sheet = (new SpreadsheetReader())->read((string) file_get_contents($path), 'xlsx');
        unlink($path);

        $this->assertSame(2, $sheet->rowCount(), 'les lignes entièrement vides sont ignorées');
        $this->assertSame('F-CGAB', $sheet->rows()[1][0]);
    }

    public function testSamplesAndDistinctValues(): void
    {
        $sheet = (new SpreadsheetReader())->read($this->fixture('materiel-semicolon.csv'), 'csv');

        $this->assertSame(['Planeur', 'Avion', 'Treuil'], $sheet->distinctValues(1, 20, skipFirstRow: true));
        $this->assertSame(['ASK 21', 'Pégase', 'DR 400'], $sheet->sample(2, 3, skipFirstRow: true));
        $this->assertSame(['Immatriculation', 'Type', 'Modèle', 'Propriétaire'], $sheet->columnLabels(true));
        $this->assertSame(['A', 'B', 'C', 'D'], $sheet->columnLabels(false));
    }

    public function testRejectsUnknownExtension(): void
    {
        $this->expectException(ImportReadException::class);
        (new SpreadsheetReader())->read('x', 'pdf');
    }
}
```

`tests/Service/Import/HeaderDetectorTest.php` :

```php
<?php

namespace App\Tests\Service\Import;

use App\Service\Import\HeaderDetector;
use App\Service\Import\ImportSheet;
use PHPUnit\Framework\TestCase;

class HeaderDetectorTest extends TestCase
{
    public function testDetectsTextualHeader(): void
    {
        $sheet = new ImportSheet([['Immatriculation', 'Type', 'Heures'], ['F-CGAB', 'Planeur', '1200'], ['F-CHXY', 'Planeur', '340']]);

        $this->assertTrue((new HeaderDetector())->looksLikeHeader($sheet));
    }

    public function testRejectsNumericOrDuplicateFirstRow(): void
    {
        $this->assertFalse((new HeaderDetector())->looksLikeHeader(new ImportSheet([['F-CGAB', 'Planeur', '1200'], ['F-CHXY', 'Planeur', '340']])));
        $this->assertFalse((new HeaderDetector())->looksLikeHeader(new ImportSheet([['Nom', 'Nom'], ['a', 'b']])));
        $this->assertFalse((new HeaderDetector())->looksLikeHeader(new ImportSheet([['Nom', ''], ['a', 'b']])));
    }

    public function testSingleRowIsNotAHeader(): void
    {
        $this->assertFalse((new HeaderDetector())->looksLikeHeader(new ImportSheet([['F-CGAB', 'Planeur']])));
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Service/Import
```

Expected : classes introuvables.

- [ ] **Step 3 : implémentation**

`src/Service/Import/ImportReadException.php` : `final class ImportReadException extends \RuntimeException {}`.

`src/Service/Import/ImportSheet.php` :

```php
<?php

namespace App\Service\Import;

/**
 * Feuille lue : lignes rectangulaires de chaînes, lignes entièrement vides retirées.
 */
final class ImportSheet
{
    /** @var array<int, array<int, string>> */
    private array $rows;
    private int $columnCount;

    /** @param array<int, array<int, string|null>> $rows */
    public function __construct(array $rows)
    {
        $this->columnCount = 0;
        foreach ($rows as $row) {
            $this->columnCount = max($this->columnCount, count($row));
        }
        $this->rows = [];
        foreach ($rows as $row) {
            $cells = array_map(static fn ($cell): string => trim((string) $cell), array_values($row));
            $cells = array_pad($cells, $this->columnCount, '');
            if (implode('', $cells) === '') {
                continue;
            }
            $this->rows[] = $cells;
        }
    }

    /** @return array<int, array<int, string>> */
    public function rows(): array { return $this->rows; }
    public function rowCount(): int { return count($this->rows); }
    public function columnCount(): int { return $this->columnCount; }

    /** @return array<int, string> */
    public function column(int $index, bool $skipFirstRow = false): array
    {
        $rows = $skipFirstRow ? array_slice($this->rows, 1) : $this->rows;

        return array_map(static fn (array $row): string => $row[$index] ?? '', $rows);
    }

    /** Premières valeurs non vides distinctes, dans l'ordre du fichier. */
    public function sample(int $index, int $limit = 3, bool $skipFirstRow = false): array
    {
        return array_slice($this->distinctValues($index, PHP_INT_MAX, $skipFirstRow), 0, $limit);
    }

    public function distinctValues(int $index, int $limit = 20, bool $skipFirstRow = false): array
    {
        $seen = [];
        foreach ($this->column($index, $skipFirstRow) as $value) {
            if ($value === '' || isset($seen[$value])) {
                continue;
            }
            $seen[$value] = true;
            if (count($seen) >= $limit) {
                break;
            }
        }

        return array_keys($seen);
    }

    /** En-têtes du fichier, ou lettres de colonnes (A, B, … AA). */
    public function columnLabels(bool $hasHeader): array
    {
        $labels = [];
        for ($i = 0; $i < $this->columnCount; $i++) {
            $header = $hasHeader ? ($this->rows[0][$i] ?? '') : '';
            $labels[$i] = $header !== '' ? $header : self::letter($i);
        }

        return $labels;
    }

    public static function letter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }

        return $letter;
    }
}
```

`src/Service/Import/SpreadsheetReader.php` :

```php
<?php

namespace App\Service\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;

/**
 * Lit un CSV, XLS ou XLSX en mémoire. CSV : séparateur et encodage détectés.
 */
final class SpreadsheetReader
{
    public const MAX_ROWS = 2000;
    private const DELIMITERS = [';', ',', "\t"];

    public function read(string $contents, string $extension): ImportSheet
    {
        $extension = strtolower($extension);
        if (!in_array($extension, ['csv', 'xls', 'xlsx'], true)) {
            throw new ImportReadException('unsupportedFormat');
        }

        $path = tempnam(sys_get_temp_dir(), 'tarmac-import');
        try {
            if ($extension === 'csv') {
                $contents = $this->toUtf8($contents);
                file_put_contents($path, $contents);
                $reader = new CsvReader();
                $reader->setDelimiter($this->detectDelimiter($contents));
                $reader->setInputEncoding('UTF-8');
                $spreadsheet = $reader->load($path);
            } else {
                file_put_contents($path, $contents);
                $spreadsheet = IOFactory::load($path, 0, [$extension === 'xls' ? IOFactory::READER_XLS : IOFactory::READER_XLSX]);
            }
        } catch (\Throwable $exception) {
            throw new ImportReadException('unreadableFile', 0, $exception);
        } finally {
            @unlink($path);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        if (count($rows) > self::MAX_ROWS + 1) {
            throw new ImportReadException('tooManyRows');
        }

        return new ImportSheet($rows);
    }

    private function detectDelimiter(string $contents): string
    {
        $head = implode("\n", array_slice(preg_split('/\R/', $contents) ?: [], 0, 20));
        $best = ';';
        $bestCount = -1;
        foreach (self::DELIMITERS as $delimiter) {
            $count = substr_count($head, $delimiter);
            if ($count > $bestCount) {
                $best = $delimiter;
                $bestCount = $count;
            }
        }

        return $best;
    }

    private function toUtf8(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        return mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
    }
}
```

Note : le fichier temporaire de `tempnam` sert uniquement à PhpSpreadsheet, qui ne lit pas depuis une chaîne ; il est supprimé dans le `finally` et ne survit jamais à la requête. Le stockage durable est S3 (tâche 2).

`src/Service/Import/HeaderDetector.php` :

```php
<?php

namespace App\Service\Import;

/**
 * Devine si la première ligne contient des titres : que du texte court, sans vide ni doublon,
 * et au moins une ligne de données dessous.
 */
final class HeaderDetector
{
    public function looksLikeHeader(ImportSheet $sheet): bool
    {
        if ($sheet->rowCount() < 2) {
            return false;
        }
        $first = $sheet->rows()[0];
        $nonEmpty = array_filter($first, static fn (string $cell): bool => $cell !== '');
        if (count($nonEmpty) !== count($first) || count(array_unique($first)) !== count($first)) {
            return false;
        }
        foreach ($first as $cell) {
            if (is_numeric($cell) || mb_strlen($cell) > 60) {
                return false;
            }
        }

        return true;
    }
}
```

- [ ] **Step 4 : lancer**

```bash
vendor/bin/phpunit tests/Service/Import
```

Expected : 9 tests OK. Si PhpSpreadsheet émet une dépréciation sur `toArray` ou `IOFactory::load` avec le troisième argument, remplacer par `IOFactory::createReader($extension === 'xls' ? 'Xls' : 'Xlsx')->load($path)` et le noter.

- [ ] **Step 5 : commit**

```bash
git add src/Service/Import tests/Service/Import tests/fixtures/imports
git commit -m "feat(import): lecture CSV/XLS/XLSX et détection d'en-tête

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2 : stockage S3 des fichiers, purge, adaptateur mémoire en test

**Files:**
- Create: `src/Service/Import/ImportFileStorage.php`, `src/Command/PurgeImportsCommand.php`
- Modify: `config/packages/oneup_flysystem.yaml`, `config/services.yaml`, `composer.json`
- Test: `tests/Functional/ImportFileStorageTest.php`

**Interfaces:**
- Produces: `ImportFileStorage::store(UploadedFile $file, Club $club): string` (clé `imports/<club id>/<uuid>.<ext>`), `read(string $key): string`, `delete(string $key): void` (silencieux si absent), `purgeOlderThan(\DateTimeImmutable $before): int` ; commande `app:imports:purge [--hours=24]`.

- [ ] **Step 1 : adaptateur mémoire en test**

```bash
composer require --dev league/flysystem-memory
```

Dans `config/packages/oneup_flysystem.yaml`, ajouter :

```yaml
when@test:
    oneup_flysystem:
        adapters:
            s3_adapter:
                memory: ~
```

Vérifier avec `symfony console debug:container oneup_flysystem.s3_filesystem --env=test` que le service existe. Dans `config/services.yaml`, ajouter la liaison :

```yaml
    App\Service\Import\ImportFileStorage:
        arguments:
            $s3Filesystem: '@oneup_flysystem.s3_filesystem'
```

- [ ] **Step 2 : test**

`tests/Functional/ImportFileStorageTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Service\Import\ImportFileStorage;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportFileStorageTest extends FunctionalTestCase
{
    public function testStoreReadDeleteAndPurge(): void
    {
        $club = $this->createClub('Club', 'club');
        $path = tempnam(sys_get_temp_dir(), 'import');
        file_put_contents($path, "Nom;Type\nF-CGAB;Planeur\n");
        $upload = new UploadedFile($path, 'materiel.csv', 'text/csv', null, true);

        $storage = static::getContainer()->get(ImportFileStorage::class);
        $key = $storage->store($upload, $club);

        $this->assertMatchesRegularExpression('#^imports/' . $club->getId() . '/[0-9a-f-]{36}\.csv$#', $key);
        $this->assertSame("Nom;Type\nF-CGAB;Planeur\n", $storage->read($key));

        $this->assertSame(0, $storage->purgeOlderThan(new \DateTimeImmutable('-1 day')));
        $this->assertSame(1, $storage->purgeOlderThan(new \DateTimeImmutable('+1 minute')));
        $storage->delete($key); // silencieux après purge
        $this->assertSame(0, $storage->purgeOlderThan(new \DateTimeImmutable('+1 minute')));
    }
}
```

- [ ] **Step 3 : implémentation**

`src/Service/Import/ImportFileStorage.php` :

```php
<?php

namespace App\Service\Import;

use App\Entity\Club;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Le fichier importé vit sur S3 le temps de l'assistant : aucun disque local,
 * un autre serveur peut reprendre la session.
 */
final class ImportFileStorage
{
    private const PREFIX = 'imports/';

    public function __construct(private readonly FilesystemOperator $s3Filesystem)
    {
    }

    public function store(UploadedFile $file, Club $club): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'csv');
        $key = sprintf('%s%d/%s.%s', self::PREFIX, $club->getId(), Uuid::v4()->toRfc4122(), $extension);
        $stream = fopen($file->getPathname(), 'r');
        try {
            $this->s3Filesystem->writeStream($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $key;
    }

    public function read(string $key): string
    {
        return $this->s3Filesystem->read($key);
    }

    public function delete(string $key): void
    {
        if ($this->s3Filesystem->fileExists($key)) {
            $this->s3Filesystem->delete($key);
        }
    }

    public function purgeOlderThan(\DateTimeImmutable $before): int
    {
        $deleted = 0;
        foreach ($this->s3Filesystem->listContents(self::PREFIX, true) as $item) {
            /** @var StorageAttributes $item */
            if (!$item->isFile() || $item->lastModified() === null || $item->lastModified() >= $before->getTimestamp()) {
                continue;
            }
            $this->s3Filesystem->delete($item->path());
            $deleted++;
        }

        return $deleted;
    }
}
```

`symfony/uid` : vérifier qu'il est installé (`composer show symfony/uid`), sinon `composer require symfony/uid`.

`src/Command/PurgeImportsCommand.php` :

```php
<?php

namespace App\Command;

use App\Service\Import\ImportFileStorage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:imports:purge', description: 'Supprime les fichiers d\'import S3 abandonnés')]
final class PurgeImportsCommand extends Command
{
    public function __construct(private readonly ImportFileStorage $storage)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Ancienneté minimale en heures', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deleted = $this->storage->purgeOlderThan(new \DateTimeImmutable(sprintf('-%d hours', (int) $input->getOption('hours'))));
        (new SymfonyStyle($input, $output))->success(sprintf('%d fichier(s) supprimé(s).', $deleted));

        return Command::SUCCESS;
    }
}
```

Ajouter dans `README.md`, sous la ligne Heroku Scheduler existante : « une fois par jour également : `php bin/console app:imports:purge` ».

- [ ] **Step 4 : lancer**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/ImportFileStorageTest.php && symfony console app:imports:purge --hours=48
```

Expected : test OK ; la commande affiche `0 fichier(s) supprimé(s).` en dev (elle parle au vrai S3 : vérifier que les variables AWS sont présentes, sinon noter l'erreur et ne pas insister).

- [ ] **Step 5 : commit**

```bash
git add composer.json composer.lock symfony.lock config src/Service/Import/ImportFileStorage.php src/Command/PurgeImportsCommand.php tests/Functional/ImportFileStorageTest.php README.md
git commit -m "feat(import): stockage S3 des fichiers importés et purge

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3 : définitions, état de session, rapprochement des colonnes et des valeurs, résolution des lignes

**Files:**
- Create: `src/Service/Import/ImportField.php`, `ImportChoice.php`, `ImportDefinitionInterface.php`, `ImportDefinitionRegistry.php`, `ImportContext.php`, `ImportState.php`, `ImportStateStore.php`, `ColumnMapper.php`, `ValueMapper.php`, `ImportPreviewRow.php`, `ImportPreview.php`, `ImportRowResolver.php`
- Test: `tests/Service/Import/ColumnMapperTest.php`, `ValueMapperTest.php`, `ImportRowResolverTest.php`, `ImportStateTest.php`

**Interfaces:**
- Consumes: `ImportSheet` (Task 1).
- Produces: voir le code ci-dessous ; les tâches 4 à 6 implémentent `ImportDefinitionInterface` et le contrôleur consomme `ImportStateStore`, `ColumnMapper::suggest`, `ValueMapper::suggest`, `ImportRowResolver::resolve`, `ImportDefinitionRegistry::get`.

- [ ] **Step 1 : tests**

`tests/Service/Import/ColumnMapperTest.php` :

```php
<?php

namespace App\Tests\Service\Import;

use App\Service\Import\ColumnMapper;
use App\Service\Import\ImportField;
use PHPUnit\Framework\TestCase;

class ColumnMapperTest extends TestCase
{
    public function testSuggestsBySynonymsIgnoringAccentsAndCase(): void
    {
        $fields = [
            new ImportField('name', 'name', true, ['nom', 'immatriculation', 'immat']),
            new ImportField('type', 'type', true, ['type', 'categorie']),
            new ImportField('description', 'description', false, ['description', 'modele']),
        ];

        $suggestion = (new ColumnMapper())->suggest(['IMMATRICULATION', 'Catégorie', 'Modèle', 'Propriétaire'], $fields);

        $this->assertSame(['name' => 0, 'type' => 1, 'description' => 2], $suggestion);
    }

    public function testEachColumnIsUsedOnceAndUnknownStaysNull(): void
    {
        $fields = [
            new ImportField('name', 'name', true, ['nom']),
            new ImportField('lastname', 'lastname', true, ['nom']),
        ];

        $suggestion = (new ColumnMapper())->suggest(['Nom', 'Autre'], $fields);

        $this->assertSame(['name' => 0, 'lastname' => null], $suggestion);
    }

    public function testLettersNeverMatch(): void
    {
        $this->assertSame(['name' => null], (new ColumnMapper())->suggest(['A', 'B'], [new ImportField('name', 'name', true, ['nom'])]));
    }
}
```

`tests/Service/Import/ValueMapperTest.php` :

```php
<?php

namespace App\Tests\Service\Import;

use App\Service\Import\ImportChoice;
use App\Service\Import\ImportField;
use App\Service\Import\ValueMapper;
use PHPUnit\Framework\TestCase;

class ValueMapperTest extends TestCase
{
    public function testSuggestsChoiceBySynonym(): void
    {
        $field = new ImportField('type', 'type', true, ['type'], [
            new ImportChoice('glider', 'glider', ['planeur', 'glider', 'motoplaneur']),
            new ImportChoice('airplane', 'airplane', ['avion', 'ulm', 'remorqueur']),
            new ImportChoice('facility', 'infrastructure', ['treuil', 'remorque', 'hangar', 'infra']),
        ]);

        $suggestion = (new ValueMapper())->suggest(['PLANEUR', 'Avion remorqueur', 'Treuil n°2', 'Cuisine'], $field);

        $this->assertSame(['PLANEUR' => 'glider', 'Avion remorqueur' => 'airplane', 'Treuil n°2' => 'facility', 'Cuisine' => null], $suggestion);
    }
}
```

`tests/Service/Import/ImportStateTest.php` :

```php
<?php

namespace App\Tests\Service\Import;

use App\Service\Import\ImportState;
use PHPUnit\Framework\TestCase;

class ImportStateTest extends TestCase
{
    public function testRoundTripsThroughArray(): void
    {
        $state = new ImportState('equipments', 'imports/1/x.csv', 'materiel.csv', 'csv', true);
        $state->columns = ['name' => 0, 'type' => 1, 'description' => null];
        $state->values = ['type' => ['Planeur' => 'glider']];
        $state->fixed = ['owner' => 'club'];

        $copy = ImportState::fromArray($state->toArray());

        $this->assertEquals($state, $copy);
        $this->assertSame(1, $copy->columnFor('type'));
        $this->assertNull($copy->columnFor('missing'));
    }
}
```

`tests/Service/Import/ImportRowResolverTest.php` (définition factice : deux champs, dont un à choix, validation « nom déjà vu ») :

```php
<?php

namespace App\Tests\Service\Import;

use App\Entity\Club;
use App\Entity\User;
use App\Service\Import\ImportChoice;
use App\Service\Import\ImportContext;
use App\Service\Import\ImportDefinitionInterface;
use App\Service\Import\ImportField;
use App\Service\Import\ImportRowResolver;
use App\Service\Import\ImportSheet;
use App\Service\Import\ImportState;
use PHPUnit\Framework\TestCase;

class ImportRowResolverTest extends TestCase
{
    private function definition(): ImportDefinitionInterface
    {
        return new class implements ImportDefinitionInterface {
            public array $imported = [];
            public function key(): string { return 'fake'; }
            public function titleKey(): string { return 'fake'; }
            public function listRoute(): string { return 'club_dashboard'; }
            public function fields(): array
            {
                return [
                    new ImportField('name', 'name', true, ['nom']),
                    new ImportField('type', 'type', true, ['type'], [new ImportChoice('glider', 'glider', ['planeur']), new ImportChoice('airplane', 'airplane', ['avion'])]),
                    new ImportField('note', 'note', false, ['note']),
                ];
            }
            public function validate(array $values, ImportContext $context): ?string
            {
                return $context->markSeen('name:' . mb_strtolower($values['name'])) ? null : 'importReasonDuplicateInFile';
            }
            public function import(array $values, ImportContext $context): void { $this->imported[] = $values; }
        };
    }

    private function state(bool $hasHeader = true): ImportState
    {
        $state = new ImportState('fake', 'k', 'f.csv', 'csv', $hasHeader);
        $state->columns = ['name' => 0, 'type' => 1, 'note' => null];
        $state->values = ['type' => ['Planeur' => 'glider', 'Avion' => 'airplane', 'Cuisine' => null]];

        return $state;
    }

    private function context(): ImportContext
    {
        return new ImportContext(new Club(), new User());
    }

    public function testResolvesRowsWithReasons(): void
    {
        $sheet = new ImportSheet([
            ['Nom', 'Type'],
            ['F-CGAB', 'Planeur'],
            ['', 'Avion'],
            ['F-CHXY', 'Cuisine'],
            ['f-cgab', 'Avion'],
            ['F-GKLM', ''],
        ]);

        $preview = (new ImportRowResolver())->resolve($sheet, $this->state(), $this->definition(), $this->context());

        $this->assertSame(5, count($preview->rows));
        $this->assertNull($preview->rows[0]->reason);
        $this->assertSame(['name' => 'F-CGAB', 'type' => 'glider', 'note' => ''], $preview->rows[0]->values);
        $this->assertSame('importReasonMissing', $preview->rows[1]->reason);
        $this->assertSame('name', $preview->rows[1]->field);
        $this->assertSame('importReasonValueNotMapped', $preview->rows[2]->reason);
        $this->assertSame('importReasonDuplicateInFile', $preview->rows[3]->reason);
        $this->assertSame('importReasonMissing', $preview->rows[4]->reason);
        $this->assertSame(1, $preview->importableCount());
        $this->assertSame(4, $preview->ignoredCount());
    }

    public function testFixedValueWhenNoColumn(): void
    {
        $state = $this->state(hasHeader: false);
        $state->columns['type'] = null;
        $state->fixed = ['type' => 'airplane'];
        $sheet = new ImportSheet([['F-GKLM', 'x']]);

        $preview = (new ImportRowResolver())->resolve($sheet, $state, $this->definition(), $this->context());

        $this->assertSame('airplane', $preview->rows[0]->values['type']);
        $this->assertSame(1, $preview->rows[0]->line, 'numéro de ligne du fichier, à partir de 1');
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Service/Import
```

- [ ] **Step 3 : implémentation**

`src/Service/Import/ImportChoice.php` :

```php
<?php

namespace App\Service\Import;

/** Une valeur Tarmac possible pour un champ à choix, avec ses synonymes de fichier. */
final readonly class ImportChoice
{
    /** @param string[] $synonyms */
    public function __construct(public string $value, public string $labelKey, public array $synonyms = [])
    {
    }
}
```

`src/Service/Import/ImportField.php` :

```php
<?php

namespace App\Service\Import;

final readonly class ImportField
{
    /**
     * @param string[]       $synonyms en-têtes de fichier reconnus
     * @param ImportChoice[] $choices  vide = texte libre
     * @param string|null    $default  valeur retenue quand la cellule est vide (champ à choix)
     */
    public function __construct(
        public string $key,
        public string $labelKey,
        public bool $required,
        public array $synonyms = [],
        public array $choices = [],
        public ?string $default = null,
    ) {
    }

    public function hasChoices(): bool
    {
        return $this->choices !== [];
    }

    public function choice(string $value): ?ImportChoice
    {
        foreach ($this->choices as $choice) {
            if ($choice->value === $value) {
                return $choice;
            }
        }

        return null;
    }
}
```

`src/Service/Import/ImportContext.php` :

```php
<?php

namespace App\Service\Import;

use App\Entity\Club;
use App\Entity\User;

/** Contexte d'un import : club, importateur, mémoire des clés déjà vues (doublons dans le fichier), options de l'aperçu. */
final class ImportContext
{
    private array $seen = [];

    /** @param array<int, array<string, mixed>> $overrides choix faits sur l'écran d'aperçu, par numéro de ligne */
    public function __construct(public readonly Club $club, public readonly User $user, public array $overrides = [])
    {
    }

    /** Vrai la première fois qu'une clé est vue. */
    public function markSeen(string $key): bool
    {
        if (isset($this->seen[$key])) {
            return false;
        }
        $this->seen[$key] = true;

        return true;
    }
}
```

`src/Service/Import/ImportDefinitionInterface.php` :

```php
<?php

namespace App\Service\Import;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.import_definition')]
interface ImportDefinitionInterface
{
    /** Segment d'URL : equipments, invitations. */
    public function key(): string;

    public function titleKey(): string;

    /** Route de la liste vers laquelle revenir. */
    public function listRoute(): string;

    /** @return ImportField[] */
    public function fields(): array;

    /** @param array<string, string> $values valeurs résolues par clé de champ ; renvoie la clé de raison d'ignorance, ou null */
    public function validate(array $values, ImportContext $context): ?string;

    /** @param array<string, string> $values */
    public function import(array $values, ImportContext $context): void;
}
```

`src/Service/Import/ImportDefinitionRegistry.php` :

```php
<?php

namespace App\Service\Import;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ImportDefinitionRegistry
{
    /** @var array<string, ImportDefinitionInterface> */
    private array $definitions = [];

    /** @param iterable<ImportDefinitionInterface> $definitions */
    public function __construct(#[AutowireIterator('app.import_definition')] iterable $definitions)
    {
        foreach ($definitions as $definition) {
            $this->definitions[$definition->key()] = $definition;
        }
    }

    public function get(string $key): ?ImportDefinitionInterface
    {
        return $this->definitions[$key] ?? null;
    }
}
```

`src/Service/Import/ImportState.php` :

```php
<?php

namespace App\Service\Import;

/** État de l'assistant en session : jamais les lignes du fichier, seulement les choix. */
final class ImportState
{
    /** @var array<string, int|null> colonne choisie par champ */
    public array $columns = [];
    /** @var array<string, array<string, string|null>> valeur Tarmac par valeur brute, par champ à choix */
    public array $values = [];
    /** @var array<string, string> valeur unique par champ à choix sans colonne */
    public array $fixed = [];

    public function __construct(
        public string $definition,
        public string $fileKey,
        public string $originalName,
        public string $extension,
        public bool $hasHeader,
    ) {
    }

    public function columnFor(string $field): ?int
    {
        return $this->columns[$field] ?? null;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }

    public static function fromArray(array $data): self
    {
        $state = new self($data['definition'], $data['fileKey'], $data['originalName'], $data['extension'], (bool) $data['hasHeader']);
        $state->columns = $data['columns'] ?? [];
        $state->values = $data['values'] ?? [];
        $state->fixed = $data['fixed'] ?? [];

        return $state;
    }
}
```

`src/Service/Import/ImportStateStore.php` :

```php
<?php

namespace App\Service\Import;

use App\Entity\Club;
use Symfony\Component\HttpFoundation\RequestStack;

final class ImportStateStore
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function get(Club $club, string $definition): ?ImportState
    {
        $data = $this->requestStack->getSession()->get($this->key($club, $definition));

        return is_array($data) ? ImportState::fromArray($data) : null;
    }

    public function save(Club $club, ImportState $state): void
    {
        $this->requestStack->getSession()->set($this->key($club, $state->definition), $state->toArray());
    }

    public function clear(Club $club, string $definition): void
    {
        $this->requestStack->getSession()->remove($this->key($club, $definition));
    }

    private function key(Club $club, string $definition): string
    {
        return sprintf('import.%d.%s', $club->getId(), $definition);
    }
}
```

`src/Service/Import/ColumnMapper.php` :

```php
<?php

namespace App\Service\Import;

use function Symfony\Component\String\u;

/** Pré-affecte une colonne à chaque champ d'après les en-têtes du fichier. */
final class ColumnMapper
{
    /**
     * @param array<int, string> $labels
     * @param ImportField[]      $fields
     *
     * @return array<string, int|null>
     */
    public function suggest(array $labels, array $fields): array
    {
        $normalized = array_map([self::class, 'normalize'], $labels);
        $used = [];
        $result = [];
        foreach ($fields as $field) {
            $result[$field->key] = null;
            foreach ($field->synonyms as $synonym) {
                $needle = self::normalize($synonym);
                foreach ($normalized as $index => $label) {
                    if (isset($used[$index]) || $label === '' || strlen($label) < 2) {
                        continue;
                    }
                    if ($label === $needle || str_contains($label, $needle)) {
                        $result[$field->key] = $index;
                        $used[$index] = true;
                        continue 3;
                    }
                }
            }
        }

        return $result;
    }

    public static function normalize(string $text): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', u($text)->ascii()->lower()->toString()));
    }
}
```

`src/Service/Import/ValueMapper.php` :

```php
<?php

namespace App\Service\Import;

/** Pré-affecte une valeur Tarmac à chaque valeur distincte d'une colonne à choix. */
final class ValueMapper
{
    /**
     * @param string[] $distinctValues
     *
     * @return array<string, string|null>
     */
    public function suggest(array $distinctValues, ImportField $field): array
    {
        $result = [];
        foreach ($distinctValues as $raw) {
            $result[$raw] = null;
            $normalized = ColumnMapper::normalize($raw);
            foreach ($field->choices as $choice) {
                foreach ($choice->synonyms as $synonym) {
                    $needle = ColumnMapper::normalize($synonym);
                    if ($normalized === $needle || str_contains($normalized, $needle)) {
                        $result[$raw] = $choice->value;
                        continue 3;
                    }
                }
            }
        }

        return $result;
    }
}
```

`src/Service/Import/ImportPreviewRow.php` et `ImportPreview.php` :

```php
<?php

namespace App\Service\Import;

final readonly class ImportPreviewRow
{
    /**
     * @param int                   $line   numéro de ligne dans le fichier (1 = première ligne de données)
     * @param array<string, string> $values valeurs résolues par champ
     * @param string|null           $reason clé de traduction de la raison d'ignorance
     * @param string|null           $field  champ concerné par la raison
     */
    public function __construct(public int $line, public array $raw, public array $values, public ?string $reason, public ?string $field = null)
    {
    }

    public function isImportable(): bool
    {
        return $this->reason === null;
    }
}
```

```php
<?php

namespace App\Service\Import;

final readonly class ImportPreview
{
    /** @param ImportPreviewRow[] $rows */
    public function __construct(public array $rows)
    {
    }

    public function importableCount(): int
    {
        return count(array_filter($this->rows, static fn (ImportPreviewRow $row): bool => $row->isImportable()));
    }

    public function ignoredCount(): int
    {
        return count($this->rows) - $this->importableCount();
    }

    /** @return ImportPreviewRow[] */
    public function importable(): array
    {
        return array_values(array_filter($this->rows, static fn (ImportPreviewRow $row): bool => $row->isImportable()));
    }
}
```

`src/Service/Import/ImportRowResolver.php` :

```php
<?php

namespace App\Service\Import;

/**
 * Applique l'état (colonnes, valeurs, valeurs fixes) à chaque ligne du fichier et
 * demande à la définition de valider la ligne. Ne persiste rien.
 */
final class ImportRowResolver
{
    public function resolve(ImportSheet $sheet, ImportState $state, ImportDefinitionInterface $definition, ImportContext $context): ImportPreview
    {
        $rows = [];
        $line = 0;
        foreach ($sheet->rows() as $index => $raw) {
            if ($state->hasHeader && $index === 0) {
                continue;
            }
            $line++;
            $values = [];
            $reason = null;
            $reasonField = null;

            foreach ($definition->fields() as $field) {
                $column = $state->columnFor($field->key);
                $cell = $column === null ? '' : ($raw[$column] ?? '');

                if ($field->hasChoices()) {
                    if ($column === null) {
                        $value = $state->fixed[$field->key] ?? $field->default ?? '';
                    } elseif ($cell === '') {
                        $value = $field->default ?? '';
                    } else {
                        $value = $state->values[$field->key][$cell] ?? null;
                        if ($value === null && $reason === null) {
                            $reason = 'importReasonValueNotMapped';
                            $reasonField = $field->key;
                        }
                        $value = (string) $value;
                    }
                } else {
                    $value = $cell;
                }

                if ($field->required && $value === '' && $reason === null) {
                    $reason = 'importReasonMissing';
                    $reasonField = $field->key;
                }
                $values[$field->key] = $value;
            }

            if ($reason === null) {
                $reason = $definition->validate($values, $context);
            }
            $rows[] = new ImportPreviewRow($line, $raw, $values, $reason, $reasonField);
        }

        return new ImportPreview($rows);
    }
}
```

- [ ] **Step 4 : lancer**

```bash
vendor/bin/phpunit tests/Service/Import
```

Expected : tout vert (Task 1 inclus), 0 notice.

- [ ] **Step 5 : commit**

```bash
git add src/Service/Import tests/Service/Import
git commit -m "feat(import): définitions, état de session, rapprochement des colonnes et des valeurs

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4 : définition « matériel »

**Files:**
- Create: `src/Service/Import/Definition/EquipmentImportDefinition.php`
- Modify: `src/Repository/EquipmentRepository.php`
- Test: `tests/Service/Import/EquipmentImportDefinitionTest.php`

**Interfaces:**
- Consumes: `ImportField`, `ImportChoice`, `ImportContext` (Task 3) ; `Equipment` (`setName/setType/setDescription/setOwner/setClub/setCreatedBy/setActive`), enums `EquipmentType` (`GLIDER`, `AIRPLANE`, `FACILITY`) et `EquipmentOwner` (`CLUB`, `PRIVATE`).
- Produces: `EquipmentRepository::findActiveNamesByClub(Club): string[]` ; définition `equipments`, raisons `importReasonDuplicateInFile`, `importReasonAlreadyExists`.

- [ ] **Step 1 : test**

`tests/Service/Import/EquipmentImportDefinitionTest.php` :

```php
<?php

namespace App\Tests\Service\Import;

use App\Entity\Club;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Entity\User;
use App\Repository\EquipmentRepository;
use App\Service\Import\Definition\EquipmentImportDefinition;
use App\Service\Import\ImportContext;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class EquipmentImportDefinitionTest extends TestCase
{
    private function definition(array $existingNames, array &$persisted): EquipmentImportDefinition
    {
        $repository = $this->createStub(EquipmentRepository::class);
        $repository->method('findActiveNamesByClub')->willReturn($existingNames);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void { $persisted[] = $entity; });

        return new EquipmentImportDefinition($repository, $entityManager);
    }

    public function testFieldsAndSynonyms(): void
    {
        $persisted = [];
        $keys = array_map(static fn ($field) => $field->key, $this->definition([], $persisted)->fields());

        $this->assertSame(['name', 'type', 'description', 'owner'], $keys);
    }

    public function testValidateRejectsDuplicatesInClubAndInFile(): void
    {
        $persisted = [];
        $definition = $this->definition(['F-CGAB'], $persisted);
        $context = new ImportContext(new Club(), new User());

        $this->assertSame('importReasonAlreadyExists', $definition->validate(['name' => 'f-cgab ', 'type' => 'glider', 'description' => '', 'owner' => 'club'], $context));
        $this->assertNull($definition->validate(['name' => 'F-CHXY', 'type' => 'glider', 'description' => '', 'owner' => 'club'], $context));
        $this->assertSame('importReasonDuplicateInFile', $definition->validate(['name' => 'F-CHXY', 'type' => 'airplane', 'description' => '', 'owner' => 'club'], $context));
    }

    public function testImportCreatesEquipment(): void
    {
        $persisted = [];
        $definition = $this->definition([], $persisted);
        $club = new Club();
        $user = new User();

        $definition->import(['name' => 'Treuil n°2', 'type' => 'facility', 'description' => 'Treuil principal', 'owner' => 'private'], new ImportContext($club, $user));

        $this->assertCount(1, $persisted);
        $equipment = $persisted[0];
        $this->assertInstanceOf(Equipment::class, $equipment);
        $this->assertSame('Treuil n°2', $equipment->getName());
        $this->assertSame(EquipmentType::FACILITY, $equipment->getType());
        $this->assertSame(EquipmentOwner::PRIVATE, $equipment->getOwner());
        $this->assertSame('Treuil principal', $equipment->getDescription());
        $this->assertSame($club, $equipment->getClub());
        $this->assertSame($user, $equipment->getCreatedBy());
        $this->assertTrue($equipment->isActive());
    }
}
```

Vérifier les noms exacts des getters (`getType`, `getOwner`, `isActive`) dans `src/Entity/Equipment.php` et ajuster le test si besoin.

- [ ] **Step 2 : implémentation**

`src/Repository/EquipmentRepository.php` :

```php
    /** @return string[] */
    public function findActiveNamesByClub(Club $club): array
    {
        $queryBuilder = $this->createQueryBuilder('equipment')
            ->select('equipment.name')
            ->andWhere('equipment.club = :club')
            ->setParameter('club', $club);
        $this->filterByActive($queryBuilder, true);

        return array_column($queryBuilder->getQuery()->getScalarResult(), 'name');
    }
```

`src/Service/Import/Definition/EquipmentImportDefinition.php` :

```php
<?php

namespace App\Service\Import\Definition;

use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Repository\EquipmentRepository;
use App\Service\Import\ColumnMapper;
use App\Service\Import\ImportChoice;
use App\Service\Import\ImportContext;
use App\Service\Import\ImportDefinitionInterface;
use App\Service\Import\ImportField;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentImportDefinition implements ImportDefinitionInterface
{
    /** @var array<int, array<string, true>> noms existants normalisés, par club */
    private array $existingNames = [];

    public function __construct(
        private readonly EquipmentRepository $equipmentRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function key(): string { return 'equipments'; }
    public function titleKey(): string { return 'importEquipmentsTitle'; }
    public function listRoute(): string { return 'club_equipments'; }

    public function fields(): array
    {
        return [
            new ImportField('name', 'name', true, ['nom', 'appareil', 'designation', 'immatriculation', 'immat', 'materiel', 'libelle']),
            new ImportField('type', 'type', true, ['type', 'categorie', 'famille', 'genre'], [
                new ImportChoice(EquipmentType::GLIDER->value, 'glider', ['planeur', 'glider', 'motoplaneur']),
                new ImportChoice(EquipmentType::AIRPLANE->value, 'airplane', ['avion', 'ulm', 'remorqueur', 'airplane']),
                new ImportChoice(EquipmentType::FACILITY->value, 'infrastructure', ['treuil', 'remorque', 'hangar', 'vehicule', 'infra', 'batiment']),
            ]),
            new ImportField('description', 'description', false, ['description', 'remarque', 'commentaire', 'notes', 'modele']),
            new ImportField('owner', 'owner', false, ['proprietaire', 'appartenance'], [
                new ImportChoice(EquipmentOwner::CLUB->value, 'club', ['club', 'association', 'aeroclub']),
                new ImportChoice(EquipmentOwner::PRIVATE->value, 'private', ['prive', 'privee', 'particulier', 'proprietaire']),
            ], EquipmentOwner::CLUB->value),
        ];
    }

    public function validate(array $values, ImportContext $context): ?string
    {
        $normalized = ColumnMapper::normalize($values['name']);
        if (isset($this->existingNames($context)[$normalized])) {
            return 'importReasonAlreadyExists';
        }
        if (!$context->markSeen('equipment:' . $normalized)) {
            return 'importReasonDuplicateInFile';
        }

        return null;
    }

    public function import(array $values, ImportContext $context): void
    {
        $equipment = (new Equipment())
            ->setName($values['name'])
            ->setType(EquipmentType::from($values['type']))
            ->setDescription($values['description'] !== '' ? $values['description'] : null)
            ->setOwner(EquipmentOwner::from($values['owner'] !== '' ? $values['owner'] : EquipmentOwner::CLUB->value))
            ->setClub($context->club)
            ->setCreatedBy($context->user)
            ->setActive(true);
        $this->entityManager->persist($equipment);
    }

    /** @return array<string, true> */
    private function existingNames(ImportContext $context): array
    {
        $id = spl_object_id($context);
        if (!isset($this->existingNames[$id])) {
            $this->existingNames[$id] = [];
            foreach ($this->equipmentRepository->findActiveNamesByClub($context->club) as $name) {
                $this->existingNames[$id][ColumnMapper::normalize($name)] = true;
            }
        }

        return $this->existingNames[$id];
    }
}
```

Si les setters de `Equipment` ne renvoient pas `static` (chaînage impossible), écrire les appels ligne par ligne.

- [ ] **Step 3 : lancer et commit**

```bash
vendor/bin/phpunit tests/Service/Import/EquipmentImportDefinitionTest.php
git add src/Service/Import/Definition src/Repository/EquipmentRepository.php tests/Service/Import/EquipmentImportDefinitionTest.php
git commit -m "feat(import): définition d'import du matériel

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5 : l'assistant (contrôleur, écrans, Stimulus, entrée sur la liste du matériel)

**Files:**
- Create: `src/Controller/Club/ImportController.php`, `src/Form/Import/ImportFileType.php`, `templates/club/import/file.html.twig`, `mapping.html.twig`, `values.html.twig`, `preview.html.twig`, `templates/club/import/_steps.html.twig`, `assets/controllers/import_mapping_controller.js`
- Modify: `templates/club/equipment/index.html.twig`, `translations/messages+intl-icu.fr.xlf`
- Test: `tests/Functional/ImportEquipmentTest.php`

**Interfaces:**
- Consumes: tout Task 1-4.
- Produces: routes `club_import_file` (`GET|POST /import/{definition}`), `club_import_mapping` (`GET|POST /import/{definition}/colonnes`), `club_import_values` (`GET|POST /import/{definition}/valeurs`), `club_import_preview` (`GET /import/{definition}/apercu`), `club_import_confirm` (`POST /import/{definition}/confirmer`), `club_import_cancel` (`POST /import/{definition}/annuler`), `definition` ∈ `equipments|invitations`.

Décision : le rapprochement des valeurs (spec §3.2, second bloc) est un écran intermédiaire `valeurs`, affiché seulement si un champ à choix a reçu une colonne ; il évite tout aller-retour JavaScript avec le serveur.

- [ ] **Step 1 : test fonctionnel**

`tests/Functional/ImportEquipmentTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\Club;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Entity\Membership;
use App\Entity\User;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportEquipmentTest extends FunctionalTestCase
{
    private function clubWithManager(): array
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();

        return [$club, $manager];
    }

    private function host(Club $club): string
    {
        return 'https://' . $club->getSubdomain() . '.' . static::getContainer()->getParameter('domain');
    }

    private function upload(string $fixture): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import');
        copy(__DIR__ . '/../fixtures/imports/' . $fixture, $path);

        return new UploadedFile($path, $fixture, 'text/csv', null, true);
    }

    public function testFullEquipmentImport(): void
    {
        [$club, $manager] = $this->clubWithManager();
        $existing = (new Equipment())->setName('F-CGAB')->setType(EquipmentType::GLIDER)->setOwner(EquipmentOwner::CLUB)->setClub($club)->setCreatedBy($manager);
        $this->entityManager()->persist($existing);
        $this->entityManager()->flush();
        $this->client->loginUser($manager);
        $host = $this->host($club);

        // Écran 1 : fichier
        $crawler = $this->client->request('GET', $host . '/import/equipments');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Continuer')->form();
        $form['import_file[file]']->upload($this->upload('materiel-semicolon.csv')->getPathname());
        $this->client->submit($form);
        $this->assertResponseRedirects('/import/equipments/colonnes');

        // Écran 2 : colonnes pré-affectées par synonymes, en-tête détecté
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSame('0', $crawler->filter('select[name="columns[name]"] option[selected]')->attr('value'));
        $this->assertSame('1', $crawler->filter('select[name="columns[type]"] option[selected]')->attr('value'));
        $this->assertSame('2', $crawler->filter('select[name="columns[description]"] option[selected]')->attr('value'));
        $this->assertTrue($crawler->filter('input[name="hasHeader"]')->attr('checked') !== null);
        $this->assertSelectorTextContains('body', 'ASK 21');
        $this->client->submit($crawler->selectButton('Continuer')->form());
        $this->assertResponseRedirects('/import/equipments/valeurs');

        // Écran 2b : valeurs du type et du propriétaire
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSame('glider', $crawler->filter('select[name="values[type][Planeur]"] option[selected]')->attr('value'));
        $this->assertSame('facility', $crawler->filter('select[name="values[type][Treuil]"] option[selected]')->attr('value'));
        $this->assertSame('private', $crawler->filter('select[name="values[owner][Privé]"] option[selected]')->attr('value'));
        $this->client->submit($crawler->selectButton('Vérifier l\'aperçu')->form());
        $this->assertResponseRedirects('/import/equipments/apercu');

        // Écran 3 : aperçu
        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', '3 lignes seront importées');
        $this->assertSelectorTextContains('body', 'existe déjà');
        $this->client->submit($crawler->selectButton('Importer 3 lignes')->form());
        $this->assertResponseRedirects('/equipments');

        $names = static::getContainer()->get(\App\Repository\EquipmentRepository::class)->findActiveNamesByClub($club);
        sort($names);
        $this->assertSame(['F-CGAB', 'F-CHXY', 'F-GKLM', 'Treuil n°2'], $names);
        $treuil = $this->entityManager()->getRepository(Equipment::class)->findOneBy(['name' => 'Treuil n°2']);
        $this->assertSame(EquipmentType::FACILITY, $treuil->getType());
        $chxy = $this->entityManager()->getRepository(Equipment::class)->findOneBy(['name' => 'F-CHXY']);
        $this->assertSame(EquipmentOwner::PRIVATE, $chxy->getOwner());

        /** @var FilesystemOperator $s3 */
        $s3 = static::getContainer()->get('oneup_flysystem.s3_filesystem');
        $this->assertCount(0, iterator_to_array($s3->listContents('imports', true)), 'le fichier est supprimé après import');
    }

    public function testHeaderlessFileAndFixedType(): void
    {
        [$club, $manager] = $this->clubWithManager();
        $this->client->loginUser($manager);
        $host = $this->host($club);

        $crawler = $this->client->request('GET', $host . '/import/equipments');
        $form = $crawler->selectButton('Continuer')->form();
        $form['import_file[file]']->upload($this->upload('materiel-comma-noheader.csv')->getPathname());
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();

        $this->assertNull($crawler->filter('input[name="hasHeader"]')->attr('checked'));
        $this->assertSelectorTextContains('select[name="columns[name]"]', 'A');
        $form = $crawler->selectButton('Continuer')->form();
        $form['columns[name]'] = '0';
        $form['columns[type]'] = '';
        $form['fixed[type]'] = 'glider';
        $this->client->submit($form);
        $this->assertResponseRedirects('/import/equipments/apercu');
        $crawler = $this->client->followRedirect();
        $this->assertSelectorTextContains('body', '2 lignes seront importées');
        $this->client->submit($crawler->selectButton('Importer 2 lignes')->form());

        $this->assertSame(2, static::getContainer()->get(\App\Repository\EquipmentRepository::class)->countActiveByClub($club));
    }

    public function testMemberIsForbidden(): void
    {
        $member = $this->createUser('membre@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($member)->setClub($club));
        $this->entityManager()->flush();
        $this->client->loginUser($member);

        $this->client->request('GET', $this->host($club) . '/import/equipments');

        $this->assertResponseStatusCodeSame(403);
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Functional/ImportEquipmentTest.php
```

Expected : 404.

- [ ] **Step 3 : formulaire de fichier**

`src/Form/Import/ImportFileType.php` :

```php
<?php

namespace App\Form\Import;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

class ImportFileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'importFile',
            'help' => 'importFileHelp',
            'attr' => ['accept' => '.csv,.xls,.xlsx'],
            'constraints' => [
                new NotNull(message: 'valueRequired'),
                new File(
                    maxSize: '2M',
                    maxSizeMessage: 'fileTooLarge',
                    extensions: ['csv', 'xls', 'xlsx'],
                    extensionsMessage: 'importUnsupportedFormat',
                ),
            ],
        ]);
    }
}
```

- [ ] **Step 4 : contrôleur**

`src/Controller/Club/ImportController.php` :

```php
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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

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
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('', name: 'club_import_file', methods: ['GET', 'POST'])]
    #[Breadcrumb([['label' => 'home', 'route' => 'club_dashboard'], ['label' => 'import']])]
    public function file(Request $request, string $definition): Response
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
                $form->get('file')->addError(new \Symfony\Component\Form\FormError($this->trans('import' . ucfirst($exception->getMessage()))));
                return $this->render('club/import/file.html.twig', ['club' => $club, 'definition' => $def, 'form' => $form]);
            }
            if ($sheet->rowCount() === 0) {
                $form->get('file')->addError(new \Symfony\Component\Form\FormError($this->trans('importEmptyFile')));
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

    private function field(ImportDefinitionInterface $definition, string $key): ?\App\Service\Import\ImportField
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
        return $this->container->get('translator')->trans($key, $parameters);
    }
}
```

`translator` n'est pas dans les services par défaut de `AbstractController` : injecter `TranslatorInterface $translator` dans le constructeur et remplacer `$this->container->get('translator')` par `$this->translator`.

- [ ] **Step 5 : templates**

`templates/club/import/_steps.html.twig` (indicateur d'étapes, `current` ∈ 1..3) :

```twig
<div class="d-flex gap-2 justify-content-center mb-4" style="font-size:0.85rem;color:var(--td-ink-3);">
	{% for step, key in {1: 'importStepFile', 2: 'importStepMapping', 3: 'importStepPreview'} %}
		<span {% if step == current %}style="color:var(--td-ink);font-weight:600;"{% endif %}>{{ step }}. {{ key|trans }}</span>{% if not loop.last %}<span>·</span>{% endif %}
	{% endfor %}
</div>
```

`templates/club/import/file.html.twig` :

```twig
{% extends 'club/base.html.twig' %}

{% block page_title %}{{ definition.titleKey|trans }}{% endblock %}

{% block page_content %}
	<div class="tarmac-panel" style="max-width:720px;">
		{% include 'club/import/_steps.html.twig' with {current: 1} %}
		<p class="text-secondary">{{ 'importFileLead'|trans }}</p>
		<ul class="text-secondary" style="font-size:0.9rem;">
			<li>{{ 'importFileFormats'|trans }}</li>
			<li>{{ 'importFileAnySource'|trans }}</li>
			<li>{{ 'importFileNoHeaderOk'|trans }}</li>
		</ul>
		<p><a href="{{ asset('downloads/modele-import-' ~ (definition.key == 'equipments' ? 'materiel' : 'membres') ~ '.csv') }}" class="form-hint d-inline-flex align-items-center gap-1"><i class="ti ti-download"></i>{{ 'importDownloadTemplate'|trans }}</a></p>
		{{ form_start(form) }}
		{{ form_row(form.file) }}
		<div class="d-flex justify-content-end gap-2 mt-3">
			<a href="{{ path(definition.listRoute) }}" class="btn btn-outline-secondary" style="min-height:44px;">{{ 'cancel'|trans }}</a>
			<button type="submit" class="btn btn-primary" style="min-height:44px;"><i class="ti ti-arrow-right me-2"></i>{{ 'continue'|trans }}</button>
		</div>
		{{ form_end(form) }}
	</div>
{% endblock %}
```

`templates/club/import/mapping.html.twig` :

```twig
{% extends 'club/base.html.twig' %}

{% block page_title %}{{ definition.titleKey|trans }}{% endblock %}

{% block page_content %}
	<div class="tarmac-panel" style="max-width:820px;" data-controller="import-mapping" data-import-mapping-samples-value="{{ samples|json_encode }}">
		{% include 'club/import/_steps.html.twig' with {current: 2} %}
		<p class="text-secondary">{{ 'importMappingLead'|trans({file: state.originalName}) }}</p>

		<form method="post">
			<input type="hidden" name="_token" value="{{ csrf_token('import-mapping') }}">
			<label class="form-check mb-4" style="min-height:44px;">
				<input type="checkbox" class="form-check-input" name="hasHeader" value="1" {% if state.hasHeader %}checked{% endif %} onchange="this.form.querySelector('[name=toggleHeader]').click()">
				<span class="form-check-label">{{ 'importHasHeader'|trans }}</span>
			</label>
			<button type="submit" name="toggleHeader" value="1" class="visually-hidden" tabindex="-1" aria-hidden="true"></button>

			{% for field in definition.fields %}
				{% set chosen = state.columnFor(field.key) %}
				<div class="mb-4">
					<label class="form-label {% if field.required %}required{% endif %}" for="column-{{ field.key }}">{{ field.labelKey|trans }}</label>
					<select id="column-{{ field.key }}" name="columns[{{ field.key }}]" class="form-select" style="min-height:44px;"
							data-action="import-mapping#update" data-import-mapping-target="select" data-field="{{ field.key }}">
						<option value="">{{ 'importIgnoreColumn'|trans }}</option>
						{% for index, label in labels %}
							<option value="{{ index }}" {% if chosen is same as(index) %}selected{% endif %}>{{ label }}</option>
						{% endfor %}
					</select>
					<div class="form-hint" data-import-mapping-target="sample" data-field="{{ field.key }}">
						{% if chosen is not null and samples[chosen] is not empty %}{{ 'importSample'|trans }} {{ samples[chosen]|join(' · ') }}{% endif %}
					</div>
					{% if field.hasChoices %}
						<div class="mt-2" data-import-mapping-target="fixed" data-field="{{ field.key }}" {% if chosen is not null %}hidden{% endif %}>
							<label class="form-label" for="fixed-{{ field.key }}">{{ 'importFixedValue'|trans }}</label>
							<select id="fixed-{{ field.key }}" name="fixed[{{ field.key }}]" class="form-select" style="min-height:44px;">
								{% if not field.required %}<option value="">{{ 'importNoValue'|trans }}</option>{% endif %}
								{% for choice in field.choices %}
									<option value="{{ choice.value }}" {% if (state.fixed[field.key] ?? field.default) == choice.value %}selected{% endif %}>{{ choice.labelKey|trans }}</option>
								{% endfor %}
							</select>
						</div>
					{% endif %}
				</div>
			{% endfor %}

			<div class="d-flex justify-content-between gap-2 mt-3 flex-wrap">
				<a href="{{ path('club_import_file', {definition: definition.key}) }}" class="btn btn-outline-secondary" style="min-height:44px;"><i class="ti ti-arrow-left me-2"></i>{{ 'importChangeFile'|trans }}</a>
				<button type="submit" class="btn btn-primary" style="min-height:44px;"><i class="ti ti-arrow-right me-2"></i>{{ 'continue'|trans }}</button>
			</div>
		</form>
	</div>
{% endblock %}
```

`templates/club/import/values.html.twig` :

```twig
{% extends 'club/base.html.twig' %}

{% block page_title %}{{ definition.titleKey|trans }}{% endblock %}

{% block page_content %}
	<div class="tarmac-panel" style="max-width:820px;">
		{% include 'club/import/_steps.html.twig' with {current: 2} %}
		<p class="text-secondary">{{ 'importValuesLead'|trans }}</p>
		<form method="post">
			<input type="hidden" name="_token" value="{{ csrf_token('import-values') }}">
			{% for field in definition.fields %}
				{% if state.values[field.key] is defined %}
					<h3 style="font-size:1rem;font-weight:700;margin:1rem 0 0.5rem;">{{ field.labelKey|trans }}</h3>
					{% for raw, current in state.values[field.key] %}
						<div class="row g-2 align-items-center mb-2">
							<div class="col-12 col-sm-5"><code>{{ raw }}</code></div>
							<div class="col-12 col-sm-7">
								<select name="values[{{ field.key }}][{{ raw }}]" class="form-select" style="min-height:44px;">
									<option value="">{{ 'importIgnoreValue'|trans }}</option>
									{% for choice in field.choices %}
										<option value="{{ choice.value }}" {% if current == choice.value %}selected{% endif %}>{{ choice.labelKey|trans }}</option>
									{% endfor %}
								</select>
							</div>
						</div>
					{% endfor %}
				{% endif %}
			{% endfor %}
			<div class="d-flex justify-content-between gap-2 mt-3 flex-wrap">
				<a href="{{ path('club_import_mapping', {definition: definition.key}) }}" class="btn btn-outline-secondary" style="min-height:44px;"><i class="ti ti-arrow-left me-2"></i>{{ 'back'|trans }}</a>
				<button type="submit" class="btn btn-primary" style="min-height:44px;"><i class="ti ti-arrow-right me-2"></i>{{ 'importCheckPreview'|trans }}</button>
			</div>
		</form>
	</div>
{% endblock %}
```

`templates/club/import/preview.html.twig` :

```twig
{% extends 'club/base.html.twig' %}

{% block page_title %}{{ definition.titleKey|trans }}{% endblock %}

{% block page_content %}
	<div class="tarmac-panel">
		{% include 'club/import/_steps.html.twig' with {current: 3} %}
		<p class="fw-bold">{{ 'importPreviewSummary'|trans({imported: preview.importableCount, ignored: preview.ignoredCount}) }}</p>
		<form method="post" action="{{ path('club_import_confirm', {definition: definition.key}) }}">
			<input type="hidden" name="_token" value="{{ csrf_token('import-confirm') }}">
			<div class="table-responsive">
				<table class="table table-vcenter">
					<thead>
						<tr>
							<th>#</th>
							{% for field in definition.fields %}<th>{{ field.labelKey|trans }}</th>{% endfor %}
							{% block preview_extra_headers %}{% endblock %}
							<th>{{ 'status'|trans }}</th>
						</tr>
					</thead>
					<tbody>
						{% for row in preview.rows %}
							<tr class="{% if not row.isImportable %}text-secondary{% endif %}">
								<td>{{ row.line }}</td>
								{% for field in definition.fields %}
									<td>{% if field.hasChoices and row.values[field.key] != '' and field.choice(row.values[field.key]) %}{{ field.choice(row.values[field.key]).labelKey|trans }}{% else %}{{ row.values[field.key] }}{% endif %}</td>
								{% endfor %}
								{% block preview_extra_cells %}{% endblock %}
								<td>
									{% if row.isImportable %}
										<span class="tarmac-badge tarmac-badge--success"><i class="ti ti-check"></i>{{ 'importWillImport'|trans }}</span>
									{% else %}
										<span class="tarmac-badge tarmac-badge--secondary" title="{{ row.reason|trans }}"><i class="ti ti-ban"></i>{{ row.reason|trans }}</span>
									{% endif %}
								</td>
							</tr>
						{% endfor %}
					</tbody>
				</table>
			</div>
			<div class="d-flex justify-content-between gap-2 mt-3 flex-wrap">
				<a href="{{ path('club_import_mapping', {definition: definition.key}) }}" class="btn btn-outline-secondary" style="min-height:44px;"><i class="ti ti-arrow-left me-2"></i>{{ 'importEditMapping'|trans }}</a>
				<button type="submit" class="btn btn-primary" style="min-height:44px;" {% if preview.importableCount == 0 %}disabled{% endif %}>
					<i class="ti ti-file-import me-2"></i>{{ 'importConfirm'|trans({count: preview.importableCount}) }}
				</button>
			</div>
		</form>
	</div>
{% endblock %}
```

Note pour la tâche 6 : le template invitations étendra ce fichier via les blocs `preview_extra_headers` / `preview_extra_cells` (utiliser `{% extends 'club/import/preview.html.twig' %}` dans un template dédié).

`assets/controllers/import_mapping_controller.js` :

```js
import { Controller } from '@hotwired/stimulus';

/**
 * Écran de rapprochement des colonnes : sous chaque menu, l'aperçu des valeurs de la colonne choisie ;
 * pour un champ à choix sans colonne, le menu de valeur unique apparaît.
 *
 * Attendu : data-controller="import-mapping", data-import-mapping-samples-value (JSON {index: [valeurs]}),
 * targets select / sample / fixed portant data-field.
 */
export default class extends Controller {
    static targets = ['select', 'sample', 'fixed'];
    static values = { samples: Object, sampleLabel: String };

    update(event) {
        const field = event.currentTarget.dataset.field;
        const index = event.currentTarget.value;
        const sample = this.sampleTargets.find((element) => element.dataset.field === field);
        if (sample) {
            const values = index === '' ? [] : (this.samplesValue[index] || []);
            sample.textContent = values.length ? `${this.element.dataset.importMappingSampleLabelValue || ''} ${values.join(' · ')}`.trim() : '';
        }
        const fixed = this.fixedTargets.find((element) => element.dataset.field === field);
        if (fixed) {
            fixed.hidden = index !== '';
        }
    }
}
```

Dans `mapping.html.twig`, ajouter `data-import-mapping-sample-label-value="{{ 'importSample'|trans }}"` sur le conteneur.

- [ ] **Step 6 : entrée sur la liste du matériel**

`templates/club/equipment/index.html.twig`, bloc `page_actions`, avant le bouton « Ajouter » :

```twig
		<a href="{{ path('club_import_file', {definition: 'equipments'}) }}" class="btn btn-outline-secondary">
			<i class="ti ti-file-upload me-2"></i>
			<span class="d-none d-sm-inline">{{ 'import'|trans }}</span>
			<span class="d-inline d-sm-none">{{ 'import'|trans }}</span>
		</a>
```

- [ ] **Step 7 : traductions**

Vérifier l'existence de `import`, `cancel`, `back`, `continue`, `status`, `name`, `type`, `description`, `owner`, `glider`, `airplane`, `infrastructure`, `club`, `private` (réutiliser). Ajouter sous `<!-- Assistant d'import -->` :

| resname | target |
|---|---|
| `importEquipmentsTitle` | Importer du matériel |
| `importStepFile` | Le fichier |
| `importStepMapping` | Les colonnes |
| `importStepPreview` | L'aperçu |
| `importFile` | Fichier à importer |
| `importFileHelp` | CSV, XLS ou XLSX, 2 Mo maximum |
| `importFileLead` | Choisissez le fichier qui contient votre liste. À l'étape suivante, vous indiquerez quelle colonne correspond à quoi. |
| `importFileFormats` | Formats acceptés : CSV, Excel (XLS, XLSX), jusqu'à 2 000 lignes. |
| `importFileAnySource` | Le fichier peut venir de n'importe quel logiciel : les colonnes n'ont pas besoin d'avoir un nom précis. |
| `importFileNoHeaderOk` | Un fichier sans ligne de titres fonctionne aussi. |
| `importDownloadTemplate` | Télécharger un fichier modèle |
| `importUnsupportedFormat` | Ce format n'est pas accepté : CSV, XLS ou XLSX seulement |
| `importUnreadableFile` | Le fichier n'a pas pu être lu |
| `importTooManyRows` | Le fichier dépasse 2 000 lignes |
| `importEmptyFile` | Le fichier est vide |
| `importMappingLead` | Pour chaque donnée, choisissez la colonne de « {file} » qui la contient. Les premières valeurs s'affichent sous le menu pour vérifier. |
| `importHasHeader` | La première ligne contient les titres des colonnes |
| `importIgnoreColumn` | — ne pas importer — |
| `importSample` | Exemples : |
| `importFixedValue` | Valeur pour tout le fichier |
| `importNoValue` | — aucune — |
| `importChangeFile` | Changer de fichier |
| `importColumnUsedTwice` | La colonne « {column} » est utilisée deux fois |
| `importColumnRequired` | Choisissez une colonne pour « {field} » |
| `importFixedValueRequired` | Choisissez une valeur pour « {field} » |
| `importValuesLead` | Le fichier utilise ses propres mots. Faites correspondre chaque valeur trouvée à celle de Tarmac, ou ignorez les lignes concernées. |
| `importIgnoreValue` | — ignorer ces lignes — |
| `importCheckPreview` | Vérifier l'aperçu |
| `importPreviewSummary` | {imported, plural, =0 {Aucune ligne ne sera importée} one {# ligne sera importée} other {# lignes seront importées}}, {ignored, plural, =0 {aucune ignorée} one {# ignorée} other {# ignorées}}. |
| `importWillImport` | Sera importé |
| `importEditMapping` | Modifier le rapprochement |
| `importConfirm` | {count, plural, =0 {Rien à importer} one {Importer # ligne} other {Importer # lignes}} |
| `importDone` | {imported, plural, =0 {Aucune ligne importée} one {# ligne importée} other {# lignes importées}}, {ignored, plural, =0 {aucune ignorée} one {# ignorée} other {# ignorées}}. |
| `importReasonMissing` | Valeur obligatoire manquante |
| `importReasonValueNotMapped` | Valeur non rapprochée |
| `importReasonDuplicateInFile` | En double dans le fichier |
| `importReasonAlreadyExists` | Existe déjà dans le club |

Les tests fonctionnels asserttent « 3 lignes seront importées » et « existe déjà » : la casse du badge est « Existe déjà dans le club », `assertSelectorTextContains` est sensible à la casse — utiliser « xiste déjà » ou adapter l'assertion au texte exact.

- [ ] **Step 8 : lancer**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/ImportEquipmentTest.php && vendor/bin/phpunit && symfony console lint:twig templates && symfony console lint:xliff translations/ && node --input-type=module --check < assets/controllers/import_mapping_controller.js
```

Expected : tout vert. Vérifier à la main dans le navigateur (Chrome de l'utilisateur, 320 px) l'aperçu sous les menus et l'affichage du menu de valeur unique.

- [ ] **Step 9 : commit**

```bash
git add src templates assets translations tests
git commit -m "feat(import): assistant d'import du matériel en trois écrans

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 6 : définition « invitations », rôles à l'aperçu, retrait de l'ancien import Givav

**Files:**
- Create: `src/Service/Import/Definition/InvitationImportDefinition.php`, `templates/club/import/previewInvitations.html.twig`
- Modify: `src/Controller/Club/ImportController.php` (choix du template d'aperçu), `src/Controller/Club/InvitationController.php`, `templates/club/members/invitations/index.html.twig`, `src/Repository/MembershipRepository.php`, `src/Repository/InvitationRepository.php`
- Delete: `src/Service/InvitationImportService.php`, `src/Form/InvitationImportType.php`, `templates/club/members/invitations/import.html.twig`
- Test: `tests/Service/Import/InvitationImportDefinitionTest.php`, `tests/Functional/ImportInvitationTest.php`, fixture `tests/fixtures/imports/membres-givav.csv`

**Interfaces:**
- Consumes: `InvitationService::createInvitation(Club, array)` + `sendInvitation(Invitation)`, `MembershipRepository::findByEmailAndClub(string, Club)`, `InvitationRepository::findPendingByEmailAndClub(string, Club)`, `ImportContext::$overrides` (cases cochées à l'aperçu, indexées par numéro de ligne).
- Produces: définition `invitations`, raisons `importReasonInvalidEmail`, `importReasonAlreadyMember`, `importReasonPendingInvitation`.

- [ ] **Step 1 : tests**

`tests/fixtures/imports/membres-givav.csv` :

```csv
Nom;Prénom;Courriel;Qualifié
Dupont;Jean;jean@example.test;oui
Martin;Claire;claire@example.test;
Invalide;Paul;pas-un-mail;
Dupont;Jean;JEAN@example.test;
```

`tests/Service/Import/InvitationImportDefinitionTest.php` :

```php
<?php

namespace App\Tests\Service\Import;

use App\Entity\Club;
use App\Entity\User;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Service\Import\Definition\InvitationImportDefinition;
use App\Service\Import\ImportContext;
use App\Service\InvitationService;
use PHPUnit\Framework\TestCase;

class InvitationImportDefinitionTest extends TestCase
{
    public function testValidateReasons(): void
    {
        $memberships = $this->createStub(MembershipRepository::class);
        $memberships->method('findByEmailAndClub')->willReturnCallback(static fn (string $email) => $email === 'membre@example.test' ? new \App\Entity\Membership() : null);
        $invitations = $this->createStub(InvitationRepository::class);
        $invitations->method('findPendingByEmailAndClub')->willReturnCallback(static fn (string $email) => $email === 'invite@example.test' ? new \App\Entity\Invitation() : null);
        $definition = new InvitationImportDefinition($memberships, $invitations, $this->createStub(InvitationService::class));
        $context = new ImportContext(new Club(), new User());
        $base = ['firstname' => 'A', 'lastname' => 'B', 'isManager' => '', 'isInspector' => '', 'isPilote' => ''];

        $this->assertSame('importReasonInvalidEmail', $definition->validate(['email' => 'pas-un-mail'] + $base, $context));
        $this->assertSame('importReasonAlreadyMember', $definition->validate(['email' => 'membre@example.test'] + $base, $context));
        $this->assertSame('importReasonPendingInvitation', $definition->validate(['email' => 'invite@example.test'] + $base, $context));
        $this->assertNull($definition->validate(['email' => 'jean@example.test'] + $base, $context));
        $this->assertSame('importReasonDuplicateInFile', $definition->validate(['email' => 'JEAN@example.test'] + $base, $context));
    }

    public function testImportUsesOverridesForRoles(): void
    {
        $service = $this->createMock(InvitationService::class);
        $service->expects($this->once())->method('createInvitation')->with($this->anything(), $this->callback(static fn (array $data): bool => $data['email'] === 'jean@example.test' && $data['isInspector'] === true && $data['isManager'] === false))->willReturn(new \App\Entity\Invitation());
        $service->expects($this->once())->method('sendInvitation');
        $definition = new InvitationImportDefinition($this->createStub(MembershipRepository::class), $this->createStub(InvitationRepository::class), $service);
        $context = new ImportContext(new Club(), new User(), [1 => ['isInspector' => '1']]);

        $definition->import(['email' => 'jean@example.test', 'firstname' => 'Jean', 'lastname' => 'Dupont', 'isManager' => 'yes', 'isInspector' => '', 'isPilote' => '', '_line' => '1'], $context);
    }
}
```

Le second test impose une convention : `ImportRowResolver` ajoute `'_line' => (string) $line` aux valeurs passées à `validate()` et `import()` (modifier `ImportRowResolver::resolve` : `$values['_line'] = (string) $line;` avant l'appel à `validate`). Mettre à jour `ImportRowResolverTest::testResolvesRowsWithReasons` (`assertSame([... , '_line' => '1'], …)`).

`tests/Functional/ImportInvitationTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\Invitation;
use App\Entity\Membership;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImportInvitationTest extends FunctionalTestCase
{
    public function testGivavFileWithRolesAtPreview(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();
        $this->client->loginUser($manager);
        $host = 'https://beauce.' . static::getContainer()->getParameter('domain');

        $crawler = $this->client->request('GET', $host . '/import/invitations');
        $form = $crawler->selectButton('Continuer')->form();
        $path = tempnam(sys_get_temp_dir(), 'import');
        copy(__DIR__ . '/../fixtures/imports/membres-givav.csv', $path);
        $form['import_file[file]']->upload($path);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        $this->assertSame('2', $crawler->filter('select[name="columns[email]"] option[selected]')->attr('value'));
        $this->assertSame('1', $crawler->filter('select[name="columns[firstname]"] option[selected]')->attr('value'));
        $this->assertSame('0', $crawler->filter('select[name="columns[lastname]"] option[selected]')->attr('value'));
        $this->assertSame('3', $crawler->filter('select[name="columns[isInspector]"] option[selected]')->attr('value'));
        $this->client->submit($crawler->selectButton('Continuer')->form());
        $crawler = $this->client->followRedirect(); // valeurs : « oui » → yes
        $this->assertSame('yes', $crawler->filter('select[name="values[isInspector][oui]"] option[selected]')->attr('value'));
        $this->client->submit($crawler->selectButton('Vérifier l\'aperçu')->form());
        $crawler = $this->client->followRedirect();

        $this->assertSelectorTextContains('body', '2 lignes seront importées');
        $this->assertTrue($crawler->filter('input[name="rows[1][isInspector]"]')->attr('checked') !== null, 'rôle pré-coché depuis le fichier');
        $form = $crawler->selectButton('Importer 2 lignes')->form();
        $form['rows[2][isManager]']->tick();
        $this->client->submit($form);
        $this->assertResponseRedirects('/invitations');

        $this->assertEmailCount(2);
        $invitations = $this->entityManager()->getRepository(Invitation::class)->findBy(['club' => $club], ['email' => 'ASC']);
        $this->assertCount(2, $invitations);
        $this->assertSame('claire@example.test', $invitations[0]->getEmail());
        $this->assertTrue($invitations[0]->isManager());
        $this->assertSame('jean@example.test', $invitations[1]->getEmail());
        $this->assertTrue($invitations[1]->isInspector());
        $this->assertFalse($invitations[1]->isManager());
    }

    public function testOldImportRouteIsGone(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();
        $this->client->loginUser($manager);

        $this->client->request('GET', 'https://club.' . static::getContainer()->getParameter('domain') . '/invitations/import');

        $this->assertResponseStatusCodeSame(404);
    }
}
```

- [ ] **Step 2 : définition**

`src/Service/Import/Definition/InvitationImportDefinition.php` :

```php
<?php

namespace App\Service\Import\Definition;

use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Service\Import\ImportChoice;
use App\Service\Import\ImportContext;
use App\Service\Import\ImportDefinitionInterface;
use App\Service\Import\ImportField;
use App\Service\InvitationService;

final class InvitationImportDefinition implements ImportDefinitionInterface
{
    public function __construct(
        private readonly MembershipRepository $membershipRepository,
        private readonly InvitationRepository $invitationRepository,
        private readonly InvitationService $invitationService,
    ) {
    }

    public function key(): string { return 'invitations'; }
    public function titleKey(): string { return 'importInvitationsTitle'; }
    public function listRoute(): string { return 'club_invitations'; }

    public function fields(): array
    {
        $yesNo = [new ImportChoice('yes', 'yes', ['oui', 'yes', 'x', '1', 'vrai', 'true']), new ImportChoice('no', 'no', ['non', 'no', '0', 'faux', 'false'])];

        return [
            new ImportField('email', 'email', true, ['courriel', 'e-mail', 'email', 'mail', 'adresse']),
            new ImportField('firstname', 'firstname', true, ['prenom', 'prénom']),
            new ImportField('lastname', 'lastname', true, ['nom de famille', 'nom']),
            new ImportField('isManager', 'manager', false, ['gestionnaire', 'manager'], $yesNo, 'no'),
            new ImportField('isInspector', 'inspector', false, ['qualifie', 'inspecteur', 'part 66'], $yesNo, 'no'),
            new ImportField('isPilote', 'pilot', false, ['pilote', 'proprietaire'], $yesNo, 'no'),
        ];
    }

    public function validate(array $values, ImportContext $context): ?string
    {
        $email = mb_strtolower(trim($values['email']));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return 'importReasonInvalidEmail';
        }
        if ($this->membershipRepository->findByEmailAndClub($email, $context->club) !== null) {
            return 'importReasonAlreadyMember';
        }
        if ($this->invitationRepository->findPendingByEmailAndClub($email, $context->club) !== null) {
            return 'importReasonPendingInvitation';
        }
        if (!$context->markSeen('email:' . $email)) {
            return 'importReasonDuplicateInFile';
        }

        return null;
    }

    public function import(array $values, ImportContext $context): void
    {
        $line = (int) ($values['_line'] ?? 0);
        $overrides = $context->overrides[$line] ?? null;
        // Les cases de l'écran d'aperçu priment sur le fichier ; sans formulaire (import direct), le fichier fait foi
        $role = static fn (string $key): bool => $overrides !== null ? !empty($overrides[$key]) : ($values[$key] ?? '') === 'yes';

        $invitation = $this->invitationService->createInvitation($context->club, [
            'email' => mb_strtolower(trim($values['email'])),
            'firstname' => $values['firstname'],
            'lastname' => $values['lastname'],
            'isManager' => $role('isManager'),
            'isInspector' => $role('isInspector'),
            'isPilote' => $role('isPilote'),
        ]);
        $this->invitationService->sendInvitation($invitation);
    }
}
```

Attention : `ColumnMapper::normalize` retire les accents, donc les synonymes s'écrivent sans accent (`prenom`, `qualifie`) ; `prénom` en doublon ne nuit pas. `lastname` liste `nom de famille` avant `nom` et `ColumnMapper` traite les champs dans l'ordre : `firstname` (« Prénom ») est affecté avant `lastname` (« Nom »), et « Prénom » contient « nom » — l'ordre des champs (prénom avant nom) garantit le bon résultat ; garder cet ordre.

Ajouter les clés `yes` → « Oui » et `no` → « Non » si elles n'existent pas.

- [ ] **Step 3 : aperçu avec rôles**

`templates/club/import/previewInvitations.html.twig` :

```twig
{% extends 'club/import/preview.html.twig' %}

{% block preview_extra_headers %}
	<th>{{ 'manager'|trans }}</th><th>{{ 'inspector'|trans }}</th><th>{{ 'pilot'|trans }}</th>
{% endblock %}

{% block preview_extra_cells %}
	{% for role in ['isManager', 'isInspector', 'isPilote'] %}
		<td>
			{% if row.isImportable %}
				<input type="checkbox" class="form-check-input" name="rows[{{ row.line }}][{{ role }}]" value="1" {% if row.values[role] == 'yes' %}checked{% endif %} aria-label="{{ role == 'isManager' ? 'manager'|trans : (role == 'isInspector' ? 'inspector'|trans : 'pilot'|trans) }}">
			{% endif %}
		</td>
	{% endfor %}
{% endblock %}
```

Dans `preview.html.twig`, les blocs `preview_extra_cells` sont dans la boucle `row` : Twig permet d'utiliser `row` dans un bloc surchargé tant qu'il est rendu dans la boucle (les blocs héritent du contexte). Vérifier avec `lint:twig` et le test.

Dans `ImportController::preview()`, choisir le template : `$def->key() === 'invitations' ? 'club/import/previewInvitations.html.twig' : 'club/import/preview.html.twig'`. Pour rester générique, ajouter à `ImportDefinitionInterface` une méthode `previewTemplate(): string` (matériel → `club/import/preview.html.twig`, invitations → `club/import/previewInvitations.html.twig`) et l'utiliser.

- [ ] **Step 4 : retirer l'ancien import**

- Supprimer `src/Service/InvitationImportService.php`, `src/Form/InvitationImportType.php`, `templates/club/members/invitations/import.html.twig`.
- Dans `InvitationController` : retirer l'action `import`, l'injection `InvitationImportService`, les `use` associés.
- Dans `templates/club/members/invitations/index.html.twig`, le bouton « Importer » pointe vers `path('club_import_file', {definition: 'invitations'})`.
- `grep -rn "InvitationImportService\|InvitationImportType\|club_invitation_import" src templates tests` doit ne rien renvoyer (adapter les tests qui y feraient référence).
- Les clés `selectGivavFile`, `givavFileDescription`, `fileFormatHelp`, `requiredColumns`, `importProcessed`, `skipReason*` peuvent rester dans le catalogue.

- [ ] **Step 5 : traductions**

| resname | target |
|---|---|
| `importInvitationsTitle` | Importer des membres |
| `importReasonInvalidEmail` | Adresse e-mail invalide |
| `importReasonAlreadyMember` | Déjà membre du club |
| `importReasonPendingInvitation` | Invitation déjà en attente |
| `importInvitationsPreviewHint` | Cochez les rôles de chaque personne avant d'envoyer : les invitations partent à la confirmation. |

Afficher `importInvitationsPreviewHint` en tête de `previewInvitations.html.twig` (bloc à ajouter dans `preview.html.twig` : `{% block preview_lead %}{% endblock %}` juste après le résumé).

- [ ] **Step 6 : lancer et commit**

```bash
symfony console cache:clear && vendor/bin/phpunit && symfony console lint:twig templates && symfony console lint:xliff translations/ && symfony console debug:translation fr --domain=messages --only-missing
git add -A src templates translations tests
git commit -m "feat(import): import des membres par l'assistant, rôles à l'aperçu, retrait de l'import Givav

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 7 : fichiers modèles, guide, vérification finale

**Files:**
- Create: `public/downloads/modele-import-materiel.csv`, `public/downloads/modele-import-membres.csv`
- Modify: `templates/public/guide/index.html.twig`

- [ ] **Step 1 : fichiers modèles**

`public/downloads/modele-import-materiel.csv` (UTF-8 avec BOM, séparateur `;`) :

```csv
Nom;Type;Description;Propriétaire
F-CGAB · ASK 21;Planeur;Biplace école;Club
F-CHXY · Pégase;Planeur;;Privé
F-GKLM · DR 400;Avion;Remorqueur;Club
Treuil n°2;Infrastructure;Treuil principal;Club
```

`public/downloads/modele-import-membres.csv` :

```csv
Nom;Prénom;Courriel;Gestionnaire;Qualifié;Pilote
Dupont;Jean;jean.dupont@example.org;non;oui;non
Martin;Claire;claire.martin@example.org;oui;non;non
```

Vérifier que `public/downloads/` est servi tel quel (pas via AssetMapper) : `curl --proxy http://127.0.0.1:7080 -k https://www.tarmac.wip/downloads/modele-import-materiel.csv` → 200. Si le lien `asset('downloads/…')` réécrit le chemin, utiliser `/downloads/…` en dur dans `file.html.twig` (chemin, pas texte).

- [ ] **Step 2 : guide**

Section « Les matériels » : ajouter un paragraphe « Importer une liste » : le bouton Importer, les trois écrans (fichier ; colonnes avec exemples et case « titres » ; aperçu avec les lignes ignorées et pourquoi), rien n'est créé avant le bouton final, fichier modèle téléchargeable.

Section « Membres & invitations » : remplacer la mention de l'import Givav par le même assistant : les colonnes e-mail, prénom, nom, et les rôles cochés sur l'aperçu avant l'envoi ; le fichier Givav est reconnu tel quel.

Section « Premiers pas », liste de la checklist : les items matériel et membres mentionnent l'import (déjà le cas dans la carte).

- [ ] **Step 3 : vérification finale**

```bash
symfony console cache:clear && vendor/bin/phpunit && symfony console lint:twig templates && symfony console lint:xliff translations/ && symfony console debug:translation fr --domain=messages --only-missing && symfony console doctrine:schema:validate
```

Parcours manuel dans le Chrome de l'utilisateur (le navigateur intégré bloque les assets) : import matériel avec `public/downloads/modele-import-materiel.csv`, puis avec un fichier sans titres, puis import membres avec le modèle.

- [ ] **Step 4 : commit**

```bash
git add public/downloads templates/public/guide/index.html.twig
git commit -m "docs(import): fichiers modèles et guide de l'assistant d'import

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

## Auto-revue du plan

- Couverture de la spec : §2 (S3, session sans lignes, limites, transaction, MANAGE) → T2, T3, T5 ; §3.1 → T5 `file` ; §3.2 → T5 `mapping` + `values` (décision : écran intermédiaire) ; §3.3 → T5 `preview`/`confirm` ; §4 → T4 ; §5 → T6 ; §6 → T5 (bouton matériel), T6 (bouton invitations), T7 (guide, modèles) ; §8 → tests de chaque tâche ; §9 → T2.
- Cohérence : `ImportDefinitionInterface` (T3) implémentée par T4 et T6, `previewTemplate()` ajoutée en T6 (mettre à jour l'interface, `EquipmentImportDefinition` et le contrôleur en T6) ; `ImportContext::$overrides` indexé par `line` (T3, T5 `confirm`, T6) ; `_line` injecté par le résolveur (T6 modifie T3 : le faire dans T6 avec son test) ; routes `club_import_*` (T5) utilisées par T5, T6 et le guide.
