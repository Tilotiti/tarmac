# Inscription autonome des clubs — plan d'implémentation (partie 1)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Un visiteur crée son compte, confirme son e-mail, crée son club (aérodrome obligatoire, détection de doublon, demande d'adhésion) et arrive sur son tableau de bord ; l'admin est notifié.

**Architecture:** Un contrôleur `Public\OnboardingController` sur `www` enchaîne quatre écrans à route propre. La logique vit dans des services testables sans noyau : `SubdomainSlugger`, `ClubNameNormalizer`, `GeoDistance`, `ClubDuplicateFinder`, `ClubRegistrationService`, `EmailVerificationService`, `JoinRequestService`. Deux nouvelles entités : `Aerodrome` (chargée depuis OurAirports) et `JoinRequest`.

**Tech Stack:** Symfony 7.4, PHP 8.5, Doctrine ORM 3 sur PostgreSQL 16, Twig + Tabler, Stimulus via AssetMapper, PHPUnit (unitaires + `WebTestCase`), `symfonycasts/verify-email-bundle`, `symfony/rate-limiter`, `dama/doctrine-test-bundle`.

**Spec:** `docs/superpowers/specs/2026-09-02-onboarding-club-design.md` (sections 3, 5, 6, 8, 10, 11, 13).

## Global Constraints

- Traductions : clés `lowerCamelCase`, ajoutées à la main en fin de `<body>` de `translations/messages+intl-icu.fr.xlf`, id numérique suivant le dernier (`grep -o 'trans-unit id="[0-9]*"' translations/messages+intl-icu.fr.xlf | tail -1`), groupées sous un commentaire XML. Jamais de YAML, jamais `translation:extract`. Vérifier avec `symfony console lint:xliff translations/`.
- Aucune chaîne en dur côté utilisateur dans les templates ; les messages de contraintes sont des clés (comme `RegistrationFormType`).
- Templates en camelCase, mobile-first (320 px), cibles tactiles ≥ 44 px, composants Tabler.
- Aliases Doctrine descriptifs (`createQueryBuilder('aerodrome')`, jamais `a`).
- Tout contrôle d'accès passe par un Voter.
- Contrôleurs hors `AbstractController` : étendre `App\Controller\ExtendedController`.
- Commits directement sur `main`, messages en français au format conventional commits, terminés par `Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>`.
- Cache : `symfony console cache:clear`. Migrations : `symfony console make:migration` puis relecture du SQL généré, puis `symfony console doctrine:migrations:migrate -n`.
- Sous-domaine : regex `^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])?$`, 30 caractères max, mots réservés `www app admin api mail smtp imap ftp staging test demo tarmac`.
- Doublon : nom proche = inclusion ou Levenshtein / longueur < 0,25 ; proche = même aérodrome ou < 25 km.
- E-mails envoyés depuis `new Address('contact@tarmac.center', 'Tarmac')`.
- Le guide utilisateur (`templates/public/guide/index.html.twig`) se met à jour dans le même commit que la fonctionnalité qu'il décrit.

## Fichiers

Créés :

- `src/Entity/Aerodrome.php`, `src/Repository/AerodromeRepository.php`
- `src/Entity/JoinRequest.php`, `src/Repository/JoinRequestRepository.php`
- `src/Service/Aerodrome/AerodromeCsvParser.php`, `src/Command/ImportAerodromesCommand.php`
- `src/Service/Onboarding/SubdomainSlugger.php`, `ClubNameNormalizer.php`, `GeoDistance.php`, `DuplicateReport.php`, `ClubDuplicateFinder.php`, `ClubRegistrationService.php`, `EmailVerificationService.php`, `JoinRequestService.php`
- `src/Form/Onboarding/OnboardingAccountType.php`, `OnboardingClubType.php`, `JoinRequestType.php`
- `src/Form/Type/AerodromeSelectorType.php`, `src/Form/DataTransformer/AerodromeToIdTransformer.php`
- `src/Controller/Public/OnboardingController.php`
- `src/Command/PurgeUnverifiedUsersCommand.php`
- `assets/controllers/aerodrome_search_controller.js`, `assets/controllers/subdomain_preview_controller.js`
- `templates/public/onboarding/account.html.twig`, `verify.html.twig`, `club.html.twig`, `duplicate.html.twig`, `joinRequest.html.twig`, `joinRequestSent.html.twig`, `done.html.twig`
- `templates/component/aerodromeSelector.html.twig`
- `templates/email/verifyEmail.html.twig`, `clubCreated.html.twig`, `welcomePresident.html.twig`, `joinRequest.html.twig`
- `config/packages/rate_limiter.yaml`, `config/packages/symfonycasts_verify_email.yaml`
- `tests/Functional/FunctionalTestCase.php`, `tests/Functional/SmokeTest.php`, `tests/Functional/OnboardingAccountTest.php`, `tests/Functional/OnboardingClubTest.php`, `tests/Functional/JoinRequestTest.php`
- `tests/Service/Aerodrome/AerodromeCsvParserTest.php`, `tests/fixtures/airports-sample.csv`
- `tests/Service/Onboarding/SubdomainSluggerTest.php`, `ClubNameNormalizerTest.php`, `GeoDistanceTest.php`, `ClubDuplicateFinderTest.php`, `ClubRegistrationServiceTest.php`
- `tests/Service/SubdomainServiceTest.php`, `tests/Command/PurgeUnverifiedUsersCommandTest.php`

Modifiés :

- `src/Entity/Club.php` (aérodrome, coordonnées, créateur), `src/Service/SubdomainService.php` (réservés, regex)
- `src/Security/Voter/ClubVoter.php` (sujet `Club` explicite)
- `src/Repository/UserRepository.php` (`findAdmins`, `findUnverifiedOlderThan`), `src/Repository/ClubRepository.php` (`findCreatedSince`, `findByAerodrome`, filtre aérodrome)
- `src/Service/InvitationService.php` (clôture de la demande d'adhésion), `src/Controller/Club/InvitationController.php` (préremplissage, demandes d'adhésion)
- `src/Form/ClubType.php`, `src/Form/Filter/ClubFilterType.php`, `src/Controller/Admin/DashboardController.php`
- `templates/public/landing.html.twig`, `templates/layout/base.html.twig`, `templates/public/clubs.html.twig`, `templates/club/base.html.twig`
- `templates/club/members/invitations/index.html.twig`, `templates/admin/club/show.html.twig`, `new.html.twig`, `edit.html.twig`, `index.html.twig`, `templates/admin/dashboard.html.twig`
- `templates/public/guide/index.html.twig`, `translations/messages+intl-icu.fr.xlf`
- `config/packages/security.yaml`, `config/packages/mailer.yaml`, `phpunit.dist.xml`, `.env.test`

---

### Task 1 : socle de tests fonctionnels

Le projet n'a que des tests unitaires. Les tâches suivantes ajoutent des tests `WebTestCase` ; il faut une base de test, une transaction annulée entre tests et un hôte `www.<domaine>` reconnu par le routeur.

**Files:**
- Modify: `phpunit.dist.xml`, `.env.test`, `config/packages/mailer.yaml`
- Create: `tests/Functional/FunctionalTestCase.php`, `tests/Functional/SmokeTest.php`
- Local (non commité) : `.env.test.local`

**Interfaces:**
- Produces: `FunctionalTestCase::createUser(string $email, bool $verified = true, bool $admin = false): User`, `createClub(string $name, string $subdomain): Club`, `www(string $path): string`, `entityManager(): EntityManagerInterface`.

- [ ] **Step 1 : installer DAMA et vérifier l'environnement de test**

```bash
composer require --dev dama/doctrine-test-bundle
```

Le recipe Flex crée `config/packages/dama_doctrine_test_bundle.yaml` et enregistre le bundle dans `config/bundles.php` pour `test`. Vérifier que `phpunit.dist.xml` contient :

```xml
<extensions>
    <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
</extensions>
```

Sinon, remplacer la balise `<extensions>` vide par ce bloc.

- [ ] **Step 2 : variables d'environnement de test**

Ajouter à `.env.test` (commité) :

```dotenv
DOMAIN=tarmac.test
MAILER_DSN=null://null
```

Créer `.env.test.local` (ignoré par git, cf. `.gitignore` ligne 5) avec la même `DATABASE_URL` PostgreSQL que `.env.local` : Doctrine y ajoute le suffixe `_test` (`dbname_suffix` dans `config/packages/doctrine.yaml`).

Dans `config/packages/mailer.yaml`, ajouter :

```yaml
when@test:
    framework:
        mailer:
            dsn: 'null://null'
```

- [ ] **Step 3 : créer la base de test**

```bash
symfony console doctrine:database:create --env=test --if-not-exists && symfony console doctrine:migrations:migrate --env=test -n
```

Expected : `[OK] Successfully migrated`.

- [ ] **Step 4 : classe de base**

`tests/Functional/FunctionalTestCase.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\Club;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function www(string $path): string
    {
        return 'https://www.' . static::getContainer()->getParameter('domain') . $path;
    }

    protected function createUser(string $email, bool $verified = true, bool $admin = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstname('Test');
        $user->setLastname(ucfirst(explode('@', $email)[0]));
        $user->setPassword('$2y$04$abcdefghijklmnopqrstuu'); // hash factice, jamais vérifié dans ces tests
        $user->setVerified($verified);
        if ($admin) {
            $user->setRoles(['ROLE_ADMIN']);
        }
        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    protected function createClub(string $name, string $subdomain): Club
    {
        $club = new Club();
        $club->setName($name);
        $club->setSubdomain($subdomain);
        $this->entityManager()->persist($club);
        $this->entityManager()->flush();

        return $club;
    }
}
```

- [ ] **Step 5 : test de fumée**

`tests/Functional/SmokeTest.php` :

```php
<?php

namespace App\Tests\Functional;

class SmokeTest extends FunctionalTestCase
{
    public function testLandingRenders(): void
    {
        $this->client->request('GET', $this->www('/'));

        $this->assertResponseIsSuccessful();
    }

    public function testCreatedUserIsRolledBackBetweenTests(): void
    {
        $this->createUser('smoke@example.test');

        $this->assertSame(1, $this->entityManager()->getRepository(\App\Entity\User::class)->count(['email' => 'smoke@example.test']));
    }
}
```

- [ ] **Step 6 : lancer**

```bash
vendor/bin/phpunit tests/Functional/SmokeTest.php
```

Expected : 2 tests OK. Si le noyau refuse de démarrer pour une variable d'environnement absente, l'ajouter à `.env.test` avec une valeur factice (`AWS_S3_URL=https://example.test` par exemple) et relancer.

- [ ] **Step 7 : commit**

```bash
git add phpunit.dist.xml .env.test config/packages/mailer.yaml config/packages/dama_doctrine_test_bundle.yaml config/bundles.php composer.json composer.lock symfony.lock tests/Functional
git commit -m "test: socle de tests fonctionnels (base de test, DAMA, WebTestCase)

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2 : dépendances, configuration et voter

**Files:**
- Create: `config/packages/rate_limiter.yaml`, `config/packages/symfonycasts_verify_email.yaml`
- Modify: `config/packages/security.yaml`, `src/Security/Voter/ClubVoter.php`
- Test: `tests/Security/ClubVoterTest.php`

**Interfaces:**
- Produces: services `limiter.onboarding_account` et `limiter.onboarding_resend` (`RateLimiterFactory`), `VerifyEmailHelperInterface` autowirable ; `is_granted('MANAGE', $club)` honore le sujet `Club` quel que soit l'hôte.

- [ ] **Step 1 : installer**

```bash
composer require symfonycasts/verify-email-bundle symfony/rate-limiter
```

- [ ] **Step 2 : configuration**

`config/packages/symfonycasts_verify_email.yaml` :

```yaml
symfonycasts_verify_email:
    lifetime: 86400
```

`config/packages/rate_limiter.yaml` :

```yaml
framework:
    rate_limiter:
        onboarding_account:
            policy: 'fixed_window'
            limit: 5
            interval: '1 hour'
            lock_factory: null
        onboarding_resend:
            policy: 'fixed_window'
            limit: 3
            interval: '1 hour'
            lock_factory: null

when@test:
    framework:
        rate_limiter:
            onboarding_account:
                policy: 'no_limit'
            onboarding_resend:
                policy: 'no_limit'
```

Dans `config/packages/security.yaml`, sous `access_control`, après la ligne `- { path: ^/guide, roles: [] }` :

```yaml
        - { path: ^/creer-mon-club, roles: [] }
```

Les écrans du tunnel font leurs propres redirections selon l'état de connexion.

- [ ] **Step 3 : test du voter avec sujet explicite**

Aujourd'hui `ClubVoter` ignore le sujet et résout le club par le sous-domaine : sur `www`, `is_granted('MANAGE', $club)` répond toujours faux pour un non-admin. L'écran « Bienvenue » et la page admin en ont besoin.

`tests/Security/ClubVoterTest.php` :

```php
<?php

namespace App\Tests\Security;

use App\Entity\Club;
use App\Entity\Membership;
use App\Entity\User;
use App\Security\Voter\ClubVoter;
use App\Service\ClubResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class ClubVoterTest extends TestCase
{
    private function voterWithoutResolvedClub(): ClubVoter
    {
        $resolver = $this->createMock(ClubResolver::class);
        $resolver->method('resolve')->willReturn(null);

        return new ClubVoter($resolver);
    }

    public function testManagerIsGrantedOnExplicitClubSubject(): void
    {
        $user = new User();
        $club = new Club();
        $membership = (new Membership())->setUser($user)->setClub($club)->setIsManager(true);
        $club->addMembership($membership);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $this->voterWithoutResolvedClub()->vote($token, $club, ['MANAGE']));
    }

    public function testMemberIsDeniedManageOnExplicitClubSubject(): void
    {
        $user = new User();
        $club = new Club();
        $club->addMembership((new Membership())->setUser($user)->setClub($club));

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $this->assertSame(VoterInterface::ACCESS_DENIED, $this->voterWithoutResolvedClub()->vote($token, $club, ['MANAGE']));
    }
}
```

- [ ] **Step 4 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Security/ClubVoterTest.php
```

Expected : `testManagerIsGrantedOnExplicitClubSubject` échoue (ACCESS_DENIED).

- [ ] **Step 5 : modifier le voter**

Dans `src/Security/Voter/ClubVoter.php`, remplacer :

```php
        // Resolve club from subdomain
        $club = $this->clubResolver->resolve();
```

par :

```php
        // Sujet explicite (écrans www, admin) sinon résolution par le sous-domaine
        $club = $subject instanceof Club ? $subject : $this->clubResolver->resolve();
```

- [ ] **Step 6 : lancer**

```bash
vendor/bin/phpunit tests/Security/ClubVoterTest.php && symfony console cache:clear && symfony console debug:container limiter.onboarding_account --env=dev | head -5
```

Expected : 2 tests OK, le service `limiter.onboarding_account` listé.

- [ ] **Step 7 : commit**

```bash
git add composer.json composer.lock symfony.lock config src/Security/Voter/ClubVoter.php tests/Security
git commit -m "feat(onboarding): dépendances, limiteurs de débit et voter club à sujet explicite

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3 : entité `Aerodrome`, parseur CSV et commande d'import

**Files:**
- Create: `src/Entity/Aerodrome.php`, `src/Repository/AerodromeRepository.php`, `src/Service/Aerodrome/AerodromeCsvParser.php`, `src/Command/ImportAerodromesCommand.php`, `migrations/VersionXXXX.php` (générée)
- Test: `tests/Service/Aerodrome/AerodromeCsvParserTest.php`, `tests/fixtures/airports-sample.csv`

**Interfaces:**
- Produces: `Aerodrome` (`getId`, `getName`, `getMunicipality`, `getIcaoCode`, `getLatitude`, `getLongitude`, `getLabel(): string`, `Aerodrome::normalizeForSearch(string): string`), `AerodromeRepository::search(string $query, int $limit = 10): Aerodrome[]`, `findOneBySourceId(int): ?Aerodrome`, commande `app:aerodromes:import [path]`.

- [ ] **Step 1 : fixture et test du parseur**

`tests/fixtures/airports-sample.csv` (format OurAirports, 5 lignes de données : deux terrains FR retenus, un héliport FR, un terrain DE, un terrain FR fermé) :

```csv
"id","ident","type","name","latitude_deg","longitude_deg","elevation_ft","continent","iso_country","iso_region","municipality","scheduled_service","gps_code","icao_code","iata_code","local_code","home_link","wikipedia_link","keywords"
4145,"LFHM","small_airport","Megève Altiport",45.8208,6.65222,4823,"EU","FR","FR-ARA","Megève","no","LFHM","LFHM","MVV","","","",""
4104,"LFPG","large_airport","Charles de Gaulle International Airport",49.012798,2.55,392,"EU","FR","FR-IDF","","yes","LFPG","LFPG","CDG","","","",""
99999,"FR-0001","heliport","Héliport de test",45.0,5.0,0,"EU","FR","FR-ARA","Test","no","","","","","","",""
2212,"EDDB","large_airport","Berlin Brandenburg Airport",52.351389,13.493889,157,"EU","DE","DE-BR","Berlin","yes","EDDB","EDDB","BER","","","",""
99998,"FR-0002","closed_airport","Ancien terrain",46.0,1.0,0,"EU","FR","FR-NAQ","","no","","","","","","",""
```

`tests/Service/Aerodrome/AerodromeCsvParserTest.php` :

```php
<?php

namespace App\Tests\Service\Aerodrome;

use App\Service\Aerodrome\AerodromeCsvParser;
use PHPUnit\Framework\TestCase;

class AerodromeCsvParserTest extends TestCase
{
    public function testKeepsOnlyFrenchAirportsOfAcceptedTypes(): void
    {
        $rows = iterator_to_array((new AerodromeCsvParser())->parse(__DIR__ . '/../../fixtures/airports-sample.csv'), false);

        $this->assertCount(2, $rows);
        $this->assertSame([4145, 4104], array_column($rows, 'sourceId'));
    }

    public function testMapsColumnsAndNullsEmptyValues(): void
    {
        $rows = iterator_to_array((new AerodromeCsvParser())->parse(__DIR__ . '/../../fixtures/airports-sample.csv'), false);

        $this->assertSame('Megève Altiport', $rows[0]['name']);
        $this->assertSame('Megève', $rows[0]['municipality']);
        $this->assertSame('LFHM', $rows[0]['icaoCode']);
        $this->assertEqualsWithDelta(45.8208, $rows[0]['latitude'], 0.0001);
        $this->assertNull($rows[1]['municipality']);
    }

    public function testFailsOnMissingColumn(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'airports');
        file_put_contents($path, "\"id\",\"name\"\n1,\"X\"\n");

        $this->expectException(\RuntimeException::class);
        iterator_to_array((new AerodromeCsvParser())->parse($path), false);
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Service/Aerodrome/AerodromeCsvParserTest.php
```

Expected : erreur `Class "App\Service\Aerodrome\AerodromeCsvParser" not found`.

- [ ] **Step 3 : parseur**

`src/Service/Aerodrome/AerodromeCsvParser.php` :

```php
<?php

namespace App\Service\Aerodrome;

/**
 * Lit le fichier airports.csv d'OurAirports (domaine public) et ne garde
 * que les terrains d'un pays, des types susceptibles d'accueillir un club.
 */
final class AerodromeCsvParser
{
    private const TYPES = ['small_airport', 'medium_airport', 'large_airport'];
    private const COLUMNS = ['id', 'type', 'name', 'latitude_deg', 'longitude_deg', 'iso_country', 'municipality', 'icao_code'];

    /**
     * @return iterable<array{sourceId: int, icaoCode: ?string, name: string, municipality: ?string, latitude: float, longitude: float}>
     */
    public function parse(string $path, string $country = 'FR'): iterable
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Impossible d\'ouvrir %s', $path));
        }

        try {
            $headers = fgetcsv($handle, escape: '');
            if ($headers === false) {
                throw new \RuntimeException('Fichier vide');
            }
            $index = array_flip($headers);
            foreach (self::COLUMNS as $column) {
                if (!isset($index[$column])) {
                    throw new \RuntimeException(sprintf('Colonne manquante : %s', $column));
                }
            }

            while (($row = fgetcsv($handle, escape: '')) !== false) {
                if (($row[$index['iso_country']] ?? '') !== $country) {
                    continue;
                }
                if (!in_array($row[$index['type']] ?? '', self::TYPES, true)) {
                    continue;
                }

                $icao = trim((string) ($row[$index['icao_code']] ?? ''));
                $municipality = trim((string) ($row[$index['municipality']] ?? ''));

                yield [
                    'sourceId' => (int) $row[$index['id']],
                    'icaoCode' => $icao === '' ? null : $icao,
                    'name' => trim((string) $row[$index['name']]),
                    'municipality' => $municipality === '' ? null : $municipality,
                    'latitude' => (float) $row[$index['latitude_deg']],
                    'longitude' => (float) $row[$index['longitude_deg']],
                ];
            }
        } finally {
            fclose($handle);
        }
    }
}
```

- [ ] **Step 4 : lancer**

```bash
vendor/bin/phpunit tests/Service/Aerodrome/AerodromeCsvParserTest.php
```

Expected : 3 tests OK.

- [ ] **Step 5 : entité et dépôt**

`src/Entity/Aerodrome.php` :

```php
<?php

namespace App\Entity;

use App\Repository\AerodromeRepository;
use Doctrine\ORM\Mapping as ORM;
use function Symfony\Component\String\u;

#[ORM\Entity(repositoryClass: AerodromeRepository::class)]
#[ORM\Table(name: 'aerodrome')]
#[ORM\UniqueConstraint(name: 'UNIQ_AERODROME_SOURCE', fields: ['sourceId'])]
#[ORM\Index(name: 'IDX_AERODROME_SEARCH', fields: ['searchIndex'])]
class Aerodrome
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $sourceId = 0;

    #[ORM\Column(length: 4, nullable: true)]
    private ?string $icaoCode = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $municipality = null;

    #[ORM\Column]
    private float $latitude = 0.0;

    #[ORM\Column]
    private float $longitude = 0.0;

    /** Nom + commune + OACI en ASCII minuscule, pour une recherche insensible aux accents. */
    #[ORM\Column(length: 512)]
    private string $searchIndex = '';

    public static function normalizeForSearch(string $text): string
    {
        return u($text)->ascii()->lower()->collapseWhitespace()->toString();
    }

    public function getId(): ?int { return $this->id; }
    public function getSourceId(): int { return $this->sourceId; }
    public function setSourceId(int $sourceId): static { $this->sourceId = $sourceId; return $this; }
    public function getIcaoCode(): ?string { return $this->icaoCode; }
    public function setIcaoCode(?string $icaoCode): static { $this->icaoCode = $icaoCode; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getMunicipality(): ?string { return $this->municipality; }
    public function setMunicipality(?string $municipality): static { $this->municipality = $municipality; return $this; }
    public function getLatitude(): float { return $this->latitude; }
    public function setLatitude(float $latitude): static { $this->latitude = $latitude; return $this; }
    public function getLongitude(): float { return $this->longitude; }
    public function setLongitude(float $longitude): static { $this->longitude = $longitude; return $this; }
    public function getSearchIndex(): string { return $this->searchIndex; }

    public function refreshSearchIndex(): static
    {
        $this->searchIndex = self::normalizeForSearch(implode(' ', array_filter([$this->name, $this->municipality, $this->icaoCode])));

        return $this;
    }

    /** « Megève Altiport (Megève) · LFHM » ; la commune n'est pas répétée si le nom la contient. */
    public function getLabel(): string
    {
        $label = $this->name;
        if ($this->municipality !== null && mb_stripos($this->name, $this->municipality) === false) {
            $label .= ' (' . $this->municipality . ')';
        }
        if ($this->icaoCode !== null) {
            $label .= ' · ' . $this->icaoCode;
        }

        return $label;
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
```

`src/Repository/AerodromeRepository.php` :

```php
<?php

namespace App\Repository;

use App\Entity\Aerodrome;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Aerodrome>
 */
class AerodromeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Aerodrome::class);
    }

    /**
     * @return Aerodrome[]
     */
    public function search(string $query, int $limit = 10): array
    {
        $needle = Aerodrome::normalizeForSearch($query);
        if ($needle === '') {
            return [];
        }

        return $this->createQueryBuilder('aerodrome')
            ->andWhere('aerodrome.searchIndex LIKE :needle')
            ->setParameter('needle', '%' . addcslashes($needle, '%_') . '%')
            ->orderBy('aerodrome.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOneBySourceId(int $sourceId): ?Aerodrome
    {
        return $this->findOneBy(['sourceId' => $sourceId]);
    }
}
```

- [ ] **Step 6 : migration**

```bash
symfony console make:migration
```

Ouvrir le fichier généré : `up()` doit créer `aerodrome` (colonnes `id`, `source_id`, `icao_code`, `name`, `municipality`, `latitude`, `longitude`, `search_index`) avec l'index unique `UNIQ_AERODROME_SOURCE` et l'index `IDX_AERODROME_SEARCH`, rien d'autre. Renseigner `getDescription()` : `'Table aerodrome, chargée depuis OurAirports'`. Puis :

```bash
symfony console doctrine:migrations:migrate -n && symfony console doctrine:migrations:migrate --env=test -n
```

- [ ] **Step 7 : commande d'import**

`src/Command/ImportAerodromesCommand.php` :

```php
<?php

namespace App\Command;

use App\Entity\Aerodrome;
use App\Repository\AerodromeRepository;
use App\Service\Aerodrome\AerodromeCsvParser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:aerodromes:import', description: 'Charge les aérodromes français depuis le CSV OurAirports (domaine public)')]
final class ImportAerodromesCommand extends Command
{
    private const SOURCE_URL = 'https://davidmegginson.github.io/ourairports-data/airports.csv';

    public function __construct(
        private readonly AerodromeCsvParser $parser,
        private readonly AerodromeRepository $aerodromeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('path', InputArgument::OPTIONAL, 'Chemin local du CSV ; téléchargé depuis OurAirports si absent');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $path = $input->getArgument('path');
        $temporary = null;

        if ($path === null) {
            $temporary = tempnam(sys_get_temp_dir(), 'airports');
            file_put_contents($temporary, $this->httpClient->request('GET', self::SOURCE_URL)->getContent());
            $path = $temporary;
            $io->text('CSV téléchargé depuis ' . self::SOURCE_URL);
        }

        $created = 0;
        $updated = 0;

        foreach ($this->parser->parse($path) as $row) {
            $aerodrome = $this->aerodromeRepository->findOneBySourceId($row['sourceId']);
            if ($aerodrome === null) {
                $aerodrome = (new Aerodrome())->setSourceId($row['sourceId']);
                $this->entityManager->persist($aerodrome);
                $created++;
            } else {
                $updated++;
            }

            $aerodrome
                ->setIcaoCode($row['icaoCode'])
                ->setName($row['name'])
                ->setMunicipality($row['municipality'])
                ->setLatitude($row['latitude'])
                ->setLongitude($row['longitude'])
                ->refreshSearchIndex();
        }

        $this->entityManager->flush();

        if ($temporary !== null) {
            unlink($temporary);
        }

        $io->success(sprintf('%d aérodromes créés, %d mis à jour.', $created, $updated));

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 8 : lancer l'import en local**

```bash
symfony console app:aerodromes:import && symfony console dbal:run-sql "SELECT count(*) FROM aerodrome"
```

Expected : plusieurs centaines de lignes. Relancer la commande : `0 créés, N mis à jour`.

- [ ] **Step 9 : commit**

```bash
git add src/Entity/Aerodrome.php src/Repository/AerodromeRepository.php src/Service/Aerodrome src/Command/ImportAerodromesCommand.php migrations tests/Service/Aerodrome tests/fixtures/airports-sample.csv
git commit -m "feat(aerodromes): table des aérodromes chargée depuis OurAirports

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4 : champs du club, sous-domaine (slug, regex, réservés)

**Files:**
- Modify: `src/Entity/Club.php`, `src/Service/SubdomainService.php`
- Create: `src/Service/Onboarding/SubdomainSlugger.php`, migration générée
- Test: `tests/Service/Onboarding/SubdomainSluggerTest.php`, `tests/Service/SubdomainServiceTest.php`

**Interfaces:**
- Produces: `Club::getAerodrome/setAerodrome(?Aerodrome)`, `getLocationLabel/setLocationLabel(?string)`, `getLatitude/getLongitude(): ?float`, `getCreatedBy/setCreatedBy(?User)`, `syncCoordinatesFromAerodrome(): static`, `getLocationText(): string` ; `SubdomainService::RESERVED`, `SubdomainService::PATTERN`, `isReserved(string): bool`, `isWellFormed(string): bool` ; `SubdomainSlugger::fromName(string): string`.

- [ ] **Step 1 : tests**

`tests/Service/Onboarding/SubdomainSluggerTest.php` :

```php
<?php

namespace App\Tests\Service\Onboarding;

use App\Service\Onboarding\SubdomainSlugger;
use PHPUnit\Framework\TestCase;

class SubdomainSluggerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function names(): iterable
    {
        yield 'accents et tirets' => ['Aéro-Club du Pays Basque', 'aero-club-du-pays-basque'];
        yield 'apostrophe' => ["L'Élan Vélivole", 'l-elan-velivole'];
        yield 'espaces multiples' => ['CVV   Saint-Rémy ', 'cvv-saint-remy'];
        yield 'troncature sans tiret final' => ['Association Sportive de Vol à Voile de Bourgogne', 'association-sportive-de-vol-a'];
    }

    /**
     * @dataProvider names
     */
    public function testFromName(string $name, string $expected): void
    {
        $this->assertSame($expected, (new SubdomainSlugger())->fromName($name));
    }
}
```

`tests/Service/SubdomainServiceTest.php` :

```php
<?php

namespace App\Tests\Service;

use App\Service\SubdomainService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;

class SubdomainServiceTest extends TestCase
{
    private function service(): SubdomainService
    {
        return new SubdomainService(new RequestStack(), 'tarmac.test');
    }

    public function testReservedWords(): void
    {
        $this->assertTrue($this->service()->isReserved('www'));
        $this->assertTrue($this->service()->isReserved('ADMIN'));
        $this->assertFalse($this->service()->isReserved('acpb'));
    }

    public function testWellFormed(): void
    {
        $this->assertTrue($this->service()->isWellFormed('acpb'));
        $this->assertTrue($this->service()->isWellFormed('aero-club-2'));
        $this->assertFalse($this->service()->isWellFormed('a'));
        $this->assertFalse($this->service()->isWellFormed('-acpb'));
        $this->assertFalse($this->service()->isWellFormed('acpb-'));
        $this->assertFalse($this->service()->isWellFormed('Aéro'));
        $this->assertFalse($this->service()->isWellFormed(str_repeat('a', 31)));
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Service/Onboarding/SubdomainSluggerTest.php tests/Service/SubdomainServiceTest.php
```

Expected : classe et méthodes introuvables.

- [ ] **Step 3 : slugger et service**

`src/Service/Onboarding/SubdomainSlugger.php` :

```php
<?php

namespace App\Service\Onboarding;

use Symfony\Component\String\Slugger\AsciiSlugger;

final class SubdomainSlugger
{
    public const MAX_LENGTH = 30;

    public function fromName(string $name): string
    {
        $slug = (new AsciiSlugger('fr'))->slug($name, '-')->lower()->toString();

        return trim(substr($slug, 0, self::MAX_LENGTH), '-');
    }
}
```

Dans `src/Service/SubdomainService.php`, sous `private const WWW_SUBDOMAIN = 'www';` :

```php
    /** Sous-domaines qu'un club ne peut pas prendre. */
    public const RESERVED = ['www', 'app', 'admin', 'api', 'mail', 'smtp', 'imap', 'ftp', 'staging', 'test', 'demo', 'tarmac'];

    /** Minuscules, chiffres, tirets internes, 2 à 30 caractères. */
    public const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,28}[a-z0-9])?$/';

    public function isReserved(string $subdomain): bool
    {
        return in_array(mb_strtolower($subdomain), self::RESERVED, true);
    }

    public function isWellFormed(string $subdomain): bool
    {
        return preg_match(self::PATTERN, $subdomain) === 1 && strlen($subdomain) >= 2;
    }
```

- [ ] **Step 4 : lancer**

```bash
vendor/bin/phpunit tests/Service/Onboarding/SubdomainSluggerTest.php tests/Service/SubdomainServiceTest.php
```

Expected : 6 tests OK.

- [ ] **Step 5 : champs de `Club`**

Dans `src/Entity/Club.php`, après la propriété `$welcomeMessageUpdatedBy` :

```php
    #[ORM\ManyToOne(targetEntity: Aerodrome::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Aerodrome $aerodrome = null;

    /** Commune saisie quand le terrain n'est pas dans la liste. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $locationLabel = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;
```

Et les méthodes, après `setWelcomeMessageUpdatedBy` :

```php
    public function getAerodrome(): ?Aerodrome { return $this->aerodrome; }

    public function setAerodrome(?Aerodrome $aerodrome): static
    {
        $this->aerodrome = $aerodrome;
        $this->syncCoordinatesFromAerodrome();

        return $this;
    }

    public function getLocationLabel(): ?string { return $this->locationLabel; }
    public function setLocationLabel(?string $locationLabel): static { $this->locationLabel = $locationLabel; return $this; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $createdBy): static { $this->createdBy = $createdBy; return $this; }

    public function syncCoordinatesFromAerodrome(): static
    {
        if ($this->aerodrome !== null) {
            $this->latitude = $this->aerodrome->getLatitude();
            $this->longitude = $this->aerodrome->getLongitude();
        }

        return $this;
    }

    /** Libellé du terrain, ou la commune saisie, ou vide. */
    public function getLocationText(): string
    {
        return $this->aerodrome?->getLabel() ?? ($this->locationLabel ?? '');
    }
```

- [ ] **Step 6 : migration**

```bash
symfony console make:migration
```

Vérifier que `up()` ajoute à `club` les colonnes `aerodrome_id`, `location_label`, `latitude`, `longitude`, `created_by_id` avec deux clés étrangères `ON DELETE SET NULL`. Description : `'Club : aérodrome, coordonnées, commune libre et créateur'`. Puis :

```bash
symfony console doctrine:migrations:migrate -n && symfony console doctrine:migrations:migrate --env=test -n && vendor/bin/phpunit
```

Expected : suite verte.

- [ ] **Step 7 : commit**

```bash
git add src/Entity/Club.php src/Service/SubdomainService.php src/Service/Onboarding/SubdomainSlugger.php migrations tests/Service
git commit -m "feat(club): aérodrome, coordonnées, créateur et règles de sous-domaine

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5 : normalisation des noms, distance et détection de doublon

**Files:**
- Create: `src/Service/Onboarding/ClubNameNormalizer.php`, `GeoDistance.php`, `DuplicateReport.php`, `ClubDuplicateFinder.php`
- Test: `tests/Service/Onboarding/ClubNameNormalizerTest.php`, `GeoDistanceTest.php`, `ClubDuplicateFinderTest.php`

**Interfaces:**
- Consumes: `Club::getAerodrome/getLatitude/getLongitude` (Task 4), `ClubRepository::findAllActive()`.
- Produces: `ClubNameNormalizer::normalize(string): string`, `isSimilar(string, string): bool` ; `GeoDistance::kilometers(float, float, float, float): float` ; `DuplicateReport` (`public array $duplicates`, `public array $neighbours`, `isEmpty()`) ; `ClubDuplicateFinder::find(string $name, ?Aerodrome $aerodrome): DuplicateReport`.

- [ ] **Step 1 : tests**

`tests/Service/Onboarding/ClubNameNormalizerTest.php` :

```php
<?php

namespace App\Tests\Service\Onboarding;

use App\Service\Onboarding\ClubNameNormalizer;
use PHPUnit\Framework\TestCase;

class ClubNameNormalizerTest extends TestCase
{
    public function testNormalizeUnifiesAeroclubForms(): void
    {
        $normalizer = new ClubNameNormalizer();

        $this->assertSame('ac pays basque', $normalizer->normalize('Aéro-Club du Pays Basque'));
        $this->assertSame('ac pays basque', $normalizer->normalize('AEROCLUB PAYS BASQUE'));
        $this->assertSame('cvv saint remy', $normalizer->normalize('Centre de Vol à Voile de Saint-Rémy'));
        $this->assertSame('cvv saint remy', $normalizer->normalize('C.V.V. Saint Rémy'));
    }

    public function testSimilarNames(): void
    {
        $normalizer = new ClubNameNormalizer();

        $this->assertTrue($normalizer->isSimilar('Aéro-Club du Pays Basque', 'Aeroclub Pays Basque'));
        $this->assertTrue($normalizer->isSimilar('CVV Saint-Rémy', 'Centre de Vol à Voile de Saint Rémy'));
        $this->assertTrue($normalizer->isSimilar('Planeurs de Bourgogne', 'Les Planeurs de Bourgogne Sud'));
        $this->assertFalse($normalizer->isSimilar('Planeurs de Bourgogne', 'Aéroclub de Bretagne'));
        $this->assertFalse($normalizer->isSimilar('', 'Aéroclub de Bretagne'));
    }
}
```

`tests/Service/Onboarding/GeoDistanceTest.php` :

```php
<?php

namespace App\Tests\Service\Onboarding;

use App\Service\Onboarding\GeoDistance;
use PHPUnit\Framework\TestCase;

class GeoDistanceTest extends TestCase
{
    public function testParisLyon(): void
    {
        $this->assertEqualsWithDelta(392.0, GeoDistance::kilometers(48.8566, 2.3522, 45.7640, 4.8357), 3.0);
    }

    public function testSamePointIsZero(): void
    {
        $this->assertSame(0.0, GeoDistance::kilometers(45.0, 5.0, 45.0, 5.0));
    }
}
```

`tests/Service/Onboarding/ClubDuplicateFinderTest.php` :

```php
<?php

namespace App\Tests\Service\Onboarding;

use App\Entity\Aerodrome;
use App\Entity\Club;
use App\Repository\ClubRepository;
use App\Service\Onboarding\ClubDuplicateFinder;
use App\Service\Onboarding\ClubNameNormalizer;
use PHPUnit\Framework\TestCase;

class ClubDuplicateFinderTest extends TestCase
{
    private function aerodrome(string $name, float $lat, float $lng): Aerodrome
    {
        return (new Aerodrome())->setName($name)->setLatitude($lat)->setLongitude($lng);
    }

    private function club(string $name, ?Aerodrome $aerodrome): Club
    {
        return (new Club())->setName($name)->setSubdomain(strtolower(substr(md5($name), 0, 8)))->setAerodrome($aerodrome);
    }

    /**
     * @param Club[] $existing
     */
    private function finder(array $existing): ClubDuplicateFinder
    {
        $repository = $this->createMock(ClubRepository::class);
        $repository->method('findAllActive')->willReturn($existing);

        return new ClubDuplicateFinder($repository, new ClubNameNormalizer());
    }

    public function testSameAerodromeAndCloseNameIsDuplicate(): void
    {
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);
        $existing = $this->club('Centre de Vol à Voile de Bailleau', $field);

        $report = $this->finder([$existing])->find('CVV Bailleau', $field);

        $this->assertSame([$existing], $report->duplicates);
        $this->assertSame([], $report->neighbours);
    }

    public function testNearbyAerodromeAndCloseNameIsDuplicate(): void
    {
        $existing = $this->club('Aéroclub de Chartres', $this->aerodrome('Chartres', 48.45, 1.52));

        $report = $this->finder([$existing])->find('Aéro-Club de Chartres', $this->aerodrome('Bailleau', 48.5, 1.6));

        $this->assertSame([$existing], $report->duplicates);
    }

    public function testFarAwayHomonymIsIgnored(): void
    {
        $existing = $this->club('Aéroclub du Centre', $this->aerodrome('Vichy', 46.17, 3.40));

        $report = $this->finder([$existing])->find('Aéroclub du Centre', $this->aerodrome('Bailleau', 48.5, 1.6));

        $this->assertTrue($report->isEmpty());
    }

    public function testSameAerodromeDifferentNameIsNeighbour(): void
    {
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);
        $existing = $this->club('Aéroclub de Bailleau (avions)', $field);

        $report = $this->finder([$existing])->find('Planeurs de Beauce', $field);

        $this->assertSame([], $report->duplicates);
        $this->assertSame([$existing], $report->neighbours);
    }

    public function testCloseNameWithoutAnyCoordinatesIsDuplicate(): void
    {
        $existing = $this->club('Planeurs de Beauce', null);

        $report = $this->finder([$existing])->find('Les Planeurs de Beauce', null);

        $this->assertSame([$existing], $report->duplicates);
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Service/Onboarding
```

Expected : classes introuvables.

- [ ] **Step 3 : implémentation**

`src/Service/Onboarding/ClubNameNormalizer.php` :

```php
<?php

namespace App\Service\Onboarding;

use function Symfony\Component\String\u;

/**
 * Ramène deux graphies d'un même club à une forme comparable :
 * « Aéro-Club du Pays Basque » et « AEROCLUB PAYS BASQUE » donnent « ac pays basque ».
 */
final class ClubNameNormalizer
{
    private const PHRASES = [
        'centre de vol a voile' => 'cvv',
        'club de vol a voile' => 'cvv',
        'c v v' => 'cvv',
        'aero club' => 'ac',
        'aeroclub' => 'ac',
    ];

    private const STOP_WORDS = ['de', 'du', 'des', 'le', 'la', 'les', 'd', 'l', 'et'];

    private const SIMILARITY_THRESHOLD = 0.25;

    public function normalize(string $name): string
    {
        $text = u($name)->ascii()->lower()->toString();
        $text = preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '';
        $text = ' ' . trim($text) . ' ';

        foreach (self::PHRASES as $phrase => $token) {
            $text = str_replace(' ' . $phrase . ' ', ' ' . $token . ' ', $text);
        }

        $words = array_filter(
            explode(' ', trim($text)),
            static fn (string $word): bool => $word !== '' && !in_array($word, self::STOP_WORDS, true)
        );

        return implode(' ', $words);
    }

    public function isSimilar(string $first, string $second): bool
    {
        $first = $this->normalize($first);
        $second = $this->normalize($second);

        if ($first === '' || $second === '') {
            return false;
        }
        if (str_contains($first, $second) || str_contains($second, $first)) {
            return true;
        }

        return levenshtein($first, $second) / max(strlen($first), strlen($second)) < self::SIMILARITY_THRESHOLD;
    }
}
```

`src/Service/Onboarding/GeoDistance.php` :

```php
<?php

namespace App\Service\Onboarding;

final class GeoDistance
{
    private const EARTH_RADIUS_KM = 6371.0;

    /** Formule de Haversine, résultat en kilomètres. */
    public static function kilometers(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_KM * asin(sqrt($a));
    }
}
```

`src/Service/Onboarding/DuplicateReport.php` :

```php
<?php

namespace App\Service\Onboarding;

use App\Entity\Club;

final readonly class DuplicateReport
{
    /**
     * @param Club[] $duplicates clubs au nom proche et géographiquement proches (ou sans information de distance)
     * @param Club[] $neighbours clubs sur le même aérodrome avec un nom différent : jamais des doublons
     */
    public function __construct(
        public array $duplicates = [],
        public array $neighbours = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->duplicates === [] && $this->neighbours === [];
    }
}
```

`src/Service/Onboarding/ClubDuplicateFinder.php` :

```php
<?php

namespace App\Service\Onboarding;

use App\Entity\Aerodrome;
use App\Repository\ClubRepository;

final class ClubDuplicateFinder
{
    public const NEAR_KM = 25.0;

    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly ClubNameNormalizer $normalizer,
    ) {
    }

    /**
     * Parcourt tous les clubs actifs (quelques dizaines) : pas d'index, tout se fait en PHP.
     */
    public function find(string $name, ?Aerodrome $aerodrome): DuplicateReport
    {
        $duplicates = [];
        $neighbours = [];

        foreach ($this->clubRepository->findAllActive() as $club) {
            $nameClose = $this->normalizer->isSimilar($name, (string) $club->getName());
            $sameAerodrome = $aerodrome !== null && $club->getAerodrome() === $aerodrome;

            $distance = null;
            if ($aerodrome !== null && $club->getLatitude() !== null && $club->getLongitude() !== null) {
                $distance = GeoDistance::kilometers(
                    $aerodrome->getLatitude(),
                    $aerodrome->getLongitude(),
                    $club->getLatitude(),
                    $club->getLongitude(),
                );
            }

            if ($nameClose) {
                // Sans information de distance, le nom seul suffit à signaler.
                if ($sameAerodrome || $distance === null || $distance < self::NEAR_KM) {
                    $duplicates[] = $club;
                }
                continue;
            }

            if ($sameAerodrome) {
                $neighbours[] = $club;
            }
        }

        return new DuplicateReport($duplicates, $neighbours);
    }
}
```

- [ ] **Step 4 : lancer**

```bash
vendor/bin/phpunit tests/Service/Onboarding
```

Expected : tout vert (slugger inclus).

- [ ] **Step 5 : commit**

```bash
git add src/Service/Onboarding tests/Service/Onboarding
git commit -m "feat(onboarding): détection de doublon par nom normalisé et distance

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 6 : entité `JoinRequest`

**Files:**
- Create: `src/Entity/JoinRequest.php`, `src/Repository/JoinRequestRepository.php`, migration générée

**Interfaces:**
- Produces: `JoinRequest` (`getClub/setClub`, `getUser/setUser`, `getMessage/setMessage(?string)`, `getCreatedAt`, `getHandledAt`, `markHandled(?User $by): static`, `isOpen(): bool`), `JoinRequestRepository::findOpenByClub(Club): JoinRequest[]`, `findOpenByUserAndClub(User, Club): ?JoinRequest`.

- [ ] **Step 1 : entité**

`src/Entity/JoinRequest.php` :

```php
<?php

namespace App\Entity;

use App\Repository\JoinRequestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande d'un utilisateur vérifié à rejoindre un club existant, émise depuis
 * l'écran « Ce club existe déjà ? ». Les gestionnaires du club la reçoivent par e-mail.
 */
#[ORM\Entity(repositoryClass: JoinRequestRepository::class)]
#[ORM\Table(name: 'join_request')]
class JoinRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Club $club = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $handledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'handled_by_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $handledBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getClub(): ?Club { return $this->club; }
    public function setClub(?Club $club): static { $this->club = $club; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(?string $message): static { $this->message = $message; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getHandledAt(): ?\DateTimeImmutable { return $this->handledAt; }
    public function getHandledBy(): ?User { return $this->handledBy; }

    public function markHandled(?User $by): static
    {
        $this->handledAt = new \DateTimeImmutable();
        $this->handledBy = $by;

        return $this;
    }

    public function isOpen(): bool
    {
        return $this->handledAt === null;
    }
}
```

`src/Repository/JoinRequestRepository.php` :

```php
<?php

namespace App\Repository;

use App\Entity\Club;
use App\Entity\JoinRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JoinRequest>
 */
class JoinRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JoinRequest::class);
    }

    /**
     * @return JoinRequest[]
     */
    public function findOpenByClub(Club $club): array
    {
        return $this->createQueryBuilder('join_request')
            ->andWhere('join_request.club = :club')
            ->andWhere('join_request.handledAt IS NULL')
            ->setParameter('club', $club)
            ->orderBy('join_request.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOpenByUserAndClub(User $user, Club $club): ?JoinRequest
    {
        return $this->createQueryBuilder('join_request')
            ->andWhere('join_request.club = :club')
            ->andWhere('join_request.user = :user')
            ->andWhere('join_request.handledAt IS NULL')
            ->setParameter('club', $club)
            ->setParameter('user', $user)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
```

- [ ] **Step 2 : migration**

```bash
symfony console make:migration
```

Vérifier : `CREATE TABLE join_request` avec `club_id`, `user_id`, `message`, `created_at`, `handled_at`, `handled_by_id`, deux clés étrangères `CASCADE` et une `SET NULL`. Description : `'Demandes d\'adhésion émises depuis le tunnel d\'inscription'`. Puis :

```bash
symfony console doctrine:migrations:migrate -n && symfony console doctrine:migrations:migrate --env=test -n && symfony console doctrine:schema:validate
```

Expected : `[OK] The mapping files are correct` et `The database schema is in sync`.

- [ ] **Step 3 : commit**

```bash
git add src/Entity/JoinRequest.php src/Repository/JoinRequestRepository.php migrations
git commit -m "feat(onboarding): entité JoinRequest

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 7 : `ClubRegistrationService` et e-mails de création

**Files:**
- Create: `src/Service/Onboarding/ClubRegistrationService.php`, `templates/email/clubCreated.html.twig`, `templates/email/welcomePresident.html.twig`
- Modify: `src/Repository/UserRepository.php`
- Test: `tests/Service/Onboarding/ClubRegistrationServiceTest.php`

**Interfaces:**
- Consumes: `DuplicateReport` (Task 5), `Club::setCreatedBy`, `syncCoordinatesFromAerodrome` (Task 4), `SubdomainService::generateClubUrl`.
- Produces: `ClubRegistrationService::register(User $user, Club $club, DuplicateReport $report): Club`, `UserRepository::findAdmins(): User[]`.

- [ ] **Step 1 : test**

`tests/Service/Onboarding/ClubRegistrationServiceTest.php` :

```php
<?php

namespace App\Tests\Service\Onboarding;

use App\Entity\Club;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Onboarding\ClubRegistrationService;
use App\Service\Onboarding\DuplicateReport;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ClubRegistrationServiceTest extends TestCase
{
    public function testRegisterCreatesManagerMembershipAndSendsTwoEmails(): void
    {
        $admin = (new User())->setEmail('admin@example.test')->setRoles(['ROLE_ADMIN']);
        $president = (new User())->setEmail('president@example.test')->setFirstname('Jean')->setLastname('Dupont');
        $club = (new Club())->setName('Planeurs de Beauce')->setSubdomain('beauce');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($club);
        $entityManager->expects($this->once())->method('flush');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findAdmins')->willReturn([$admin]);

        $sent = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->exactly(2))->method('send')->willReturnCallback(static function (TemplatedEmail $email) use (&$sent): void {
            $sent[] = $email;
        });

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://www.tarmac.test/admin/clubs/1');

        $service = new ClubRegistrationService(
            $entityManager,
            $userRepository,
            $mailer,
            new SubdomainService(new RequestStack(), 'tarmac.test'),
            $urlGenerator,
        );

        $service->register($president, $club, new DuplicateReport());

        $this->assertSame($president, $club->getCreatedBy());
        $this->assertTrue($club->isActive());
        $membership = $club->getMembership($president);
        $this->assertNotNull($membership);
        $this->assertTrue($membership->isManager());

        $this->assertSame('admin@example.test', $sent[0]->getTo()[0]->getAddress());
        $this->assertSame('email/clubCreated.html.twig', $sent[0]->getHtmlTemplate());
        $this->assertSame('president@example.test', $sent[1]->getTo()[0]->getAddress());
        $this->assertSame('email/welcomePresident.html.twig', $sent[1]->getHtmlTemplate());
        $this->assertSame('https://beauce.tarmac.test/', $sent[1]->getContext()['clubUrl']);
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Service/Onboarding/ClubRegistrationServiceTest.php
```

Expected : classe introuvable.

- [ ] **Step 3 : `findAdmins`**

Dans `src/Repository/UserRepository.php`, après `findOneByInvitationToken` :

```php
    /**
     * La colonne roles est un JSON : on filtre en PHP sur les utilisateurs actifs,
     * ce qui reste bon marché à l'échelle de Tarmac (quelques centaines de comptes).
     *
     * @return User[]
     */
    public function findAdmins(): array
    {
        return array_values(array_filter(
            $this->findBy(['active' => true]),
            static fn (User $user): bool => $user->isAdmin()
        ));
    }
```

- [ ] **Step 4 : service**

`src/Service/Onboarding/ClubRegistrationService.php` :

```php
<?php

namespace App\Service\Onboarding;

use App\Entity\Club;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ClubRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly SubdomainService $subdomainService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Crée le club, en fait le demandeur gestionnaire, prévient les admins et
     * envoie l'e-mail de bienvenue. Le rapport de doublon sert à l'e-mail admin.
     */
    public function register(User $user, Club $club, DuplicateReport $report): Club
    {
        $club->setCreatedBy($user);
        $club->setActive(true);
        $club->syncCoordinatesFromAerodrome();

        $membership = (new Membership())->setUser($user)->setClub($club)->setIsManager(true);
        $club->addMembership($membership);
        $user->addMembership($membership);

        $this->entityManager->persist($club);
        $this->entityManager->flush();

        $clubUrl = $this->subdomainService->generateClubUrl((string) $club->getSubdomain());

        $this->notifyAdmins($club, $report, $clubUrl);
        $this->welcomePresident($user, $club, $clubUrl);

        return $club;
    }

    private function notifyAdmins(Club $club, DuplicateReport $report, string $clubUrl): void
    {
        $adminUrl = $this->urlGenerator->generate('admin_club_show', ['id' => $club->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        foreach ($this->userRepository->findAdmins() as $admin) {
            $this->mailer->send((new TemplatedEmail())
                ->from(new Address('contact@tarmac.center', 'Tarmac'))
                ->to((string) $admin->getEmail())
                ->subject(sprintf('Nouveau club : %s', $club->getName()))
                ->htmlTemplate('email/clubCreated.html.twig')
                ->context([
                    'club' => $club,
                    'report' => $report,
                    'clubUrl' => $clubUrl,
                    'adminUrl' => $adminUrl,
                ]));
        }
    }

    private function welcomePresident(User $user, Club $club, string $clubUrl): void
    {
        $this->mailer->send((new TemplatedEmail())
            ->from(new Address('contact@tarmac.center', 'Tarmac'))
            ->to((string) $user->getEmail())
            ->subject(sprintf('%s est sur Tarmac', $club->getName()))
            ->htmlTemplate('email/welcomePresident.html.twig')
            ->context([
                'user' => $user,
                'club' => $club,
                'clubUrl' => $clubUrl,
                'guideUrl' => $this->urlGenerator->generate('public_guide', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]));
    }
}
```

- [ ] **Step 5 : gabarits d'e-mail**

`templates/email/clubCreated.html.twig` :

```twig
{% extends 'email/base.html.twig' %}

{% block title %}Nouveau club - Tarmac{% endblock %}
{% block preheader %}{{ club.name }} vient d'être créé sur Tarmac{% endblock %}
{% block email_title %}Nouveau club{% endblock %}

{% block email_body %}
	<h2 style="margin-top: 0; font-size: 20px; color: #111827; font-weight: 600;">{{ club.name }}</h2>

	<table cellpadding="0" cellspacing="0" style="width: 100%; margin: 16px 0; color: #4b5563;">
		<tr><td style="padding: 4px 0;"><strong>Adresse</strong></td><td style="padding: 4px 0;"><a href="{{ clubUrl }}" style="color: #345B7C;">{{ clubUrl }}</a></td></tr>
		<tr><td style="padding: 4px 0;"><strong>Terrain</strong></td><td style="padding: 4px 0;">{{ club.locationText ?: '—' }}</td></tr>
		<tr><td style="padding: 4px 0;"><strong>Créé par</strong></td><td style="padding: 4px 0;">{{ club.createdBy.fullName }} ({{ club.createdBy.email }})</td></tr>
		<tr><td style="padding: 4px 0;"><strong>Le</strong></td><td style="padding: 4px 0;">{{ club.createdAt|date('d/m/Y à H:i') }}</td></tr>
	</table>

	{% if report.duplicates is not empty %}
		<table cellpadding="0" cellspacing="0" style="width: 100%; margin: 24px 0;">
			<tr>
				<td style="background-color: #fff3cd; border-left: 4px solid #f59f00; border-radius: 6px; padding: 16px;">
					<p style="margin: 0; color: #856404;"><strong>Doublon probable, création confirmée malgré l'alerte :</strong></p>
					<ul style="margin: 8px 0 0; padding-left: 20px; color: #856404;">
						{% for other in report.duplicates %}<li>{{ other.name }} ({{ other.subdomain }})</li>{% endfor %}
					</ul>
				</td>
			</tr>
		</table>
	{% endif %}

	{% if report.neighbours is not empty %}
		<p style="margin: 16px 0; color: #4b5563;"><strong>Sur le même terrain :</strong>
			{% for other in report.neighbours %}{{ other.name }}{% if not loop.last %}, {% endif %}{% endfor %}</p>
	{% endif %}

	<table cellpadding="0" cellspacing="0" style="width: 100%; margin: 30px 0;">
		<tr>
			<td align="center">
				<a href="{{ adminUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #345B7C; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">Voir la fiche du club</a>
			</td>
		</tr>
	</table>
{% endblock %}
```

`templates/email/welcomePresident.html.twig` :

```twig
{% extends 'email/base.html.twig' %}

{% block title %}Bienvenue sur Tarmac{% endblock %}
{% block preheader %}{{ club.name }} est prêt sur Tarmac{% endblock %}
{% block email_title %}Bienvenue{% endblock %}

{% block email_body %}
	<h2 style="margin-top: 0; font-size: 20px; color: #111827; font-weight: 600;">{{ club.name }} est sur Tarmac</h2>

	<p style="margin: 16px 0; color: #4b5563;">Bonjour {{ user.firstname }},</p>

	<p style="margin: 16px 0; color: #4b5563;">Votre club est créé. Vos membres se connecteront à cette adresse, gardez-la précieusement :</p>

	<table cellpadding="0" cellspacing="0" style="width: 100%; margin: 24px 0;">
		<tr>
			<td style="background-color: #e3f2fd; border-left: 4px solid #345B7C; border-radius: 6px; padding: 16px;">
				<p style="margin: 0; color: #1565c0; font-size: 18px;"><a href="{{ clubUrl }}" style="color: #1565c0;">{{ clubUrl }}</a></p>
			</td>
		</tr>
	</table>

	<p style="margin: 16px 0; color: #4b5563;">Sur votre tableau de bord, une liste vous indique ce qu'il reste à configurer : votre matériel, vos membres, vos plans d'entretien. Prenez les points dans l'ordre, chacun prend quelques minutes.</p>

	<p style="margin: 16px 0; color: #4b5563;">Tarmac est gratuit, sans publicité, construit par un pilote bénévole pour les clubs associatifs. Une question, un blocage ? Répondez à cet e-mail ou écrivez à <a href="mailto:contact@tarmac.center" style="color: #345B7C;">contact@tarmac.center</a>.</p>

	<table cellpadding="0" cellspacing="0" style="width: 100%; margin: 30px 0;">
		<tr>
			<td align="center">
				<a href="{{ clubUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #345B7C; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">Ouvrir mon club</a>
			</td>
		</tr>
	</table>

	<p style="font-size: 14px; color: #667382; margin: 16px 0;">Le <a href="{{ guideUrl }}" style="color: #345B7C;">guide utilisateur</a> explique chaque écran, du matériel au carnet mécanicien.</p>
{% endblock %}
```

- [ ] **Step 6 : lancer**

```bash
vendor/bin/phpunit tests/Service/Onboarding/ClubRegistrationServiceTest.php && symfony console lint:twig templates/email
```

Expected : test OK, templates valides.

- [ ] **Step 7 : commit**

```bash
git add src/Service/Onboarding/ClubRegistrationService.php src/Repository/UserRepository.php templates/email/clubCreated.html.twig templates/email/welcomePresident.html.twig tests/Service/Onboarding/ClubRegistrationServiceTest.php
git commit -m "feat(onboarding): service de création de club avec notification admin et bienvenue

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 8 : formulaires, sélecteur d'aérodrome, contrôleurs Stimulus et endpoints JSON

**Files:**
- Create: `src/Form/DataTransformer/AerodromeToIdTransformer.php`, `src/Form/Type/AerodromeSelectorType.php`, `src/Form/Onboarding/OnboardingAccountType.php`, `src/Form/Onboarding/OnboardingClubType.php`, `templates/component/aerodromeSelector.html.twig`, `assets/controllers/aerodrome_search_controller.js`, `assets/controllers/subdomain_preview_controller.js`, `src/Controller/Public/OnboardingController.php` (squelette + deux endpoints)
- Test: `tests/Functional/OnboardingApiTest.php`

**Interfaces:**
- Consumes: `AerodromeRepository::search` (Task 3), `SubdomainService::isWellFormed/isReserved` (Task 4), `ClubRepository::findOneBy`.
- Produces: routes `public_onboarding_aerodromes` (`GET /creer-mon-club/aerodromes?q=` → `[{id, label}]`), `public_onboarding_subdomain_check` (`GET /creer-mon-club/sous-domaine?value=` → `{available, message}`) ; `AerodromeSelectorType` (champ caché portant l'id, transformé en `Aerodrome`) ; partial `component/aerodromeSelector.html.twig` attendant `aerodromeField`, `locationField`, `searchUrl`.

- [ ] **Step 1 : test des endpoints**

`tests/Functional/OnboardingApiTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\Aerodrome;

class OnboardingApiTest extends FunctionalTestCase
{
    public function testAerodromeSearchIsAccentInsensitive(): void
    {
        $aerodrome = (new Aerodrome())->setSourceId(1)->setName('Megève Altiport')->setMunicipality('Megève')->setIcaoCode('LFHM')->setLatitude(45.8)->setLongitude(6.6)->refreshSearchIndex();
        $this->entityManager()->persist($aerodrome);
        $this->entityManager()->flush();

        $this->client->request('GET', $this->www('/creer-mon-club/aerodromes?q=megeve'));

        $this->assertResponseIsSuccessful();
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data);
        $this->assertSame('Megève Altiport · LFHM', $data[0]['label']);
    }

    public function testSubdomainCheck(): void
    {
        $this->createClub('Existant', 'existant');

        $this->client->request('GET', $this->www('/creer-mon-club/sous-domaine?value=existant'));
        $this->assertFalse(json_decode((string) $this->client->getResponse()->getContent(), true)['available']);

        $this->client->request('GET', $this->www('/creer-mon-club/sous-domaine?value=admin'));
        $this->assertFalse(json_decode((string) $this->client->getResponse()->getContent(), true)['available']);

        $this->client->request('GET', $this->www('/creer-mon-club/sous-domaine?value=nouveau-club'));
        $this->assertTrue(json_decode((string) $this->client->getResponse()->getContent(), true)['available']);
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Functional/OnboardingApiTest.php
```

Expected : 404.

- [ ] **Step 3 : transformer et type de champ**

`src/Form/DataTransformer/AerodromeToIdTransformer.php` :

```php
<?php

namespace App\Form\DataTransformer;

use App\Entity\Aerodrome;
use App\Repository\AerodromeRepository;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<Aerodrome|null, string>
 */
final class AerodromeToIdTransformer implements DataTransformerInterface
{
    public function __construct(private readonly AerodromeRepository $aerodromeRepository)
    {
    }

    public function transform(mixed $value): string
    {
        return $value instanceof Aerodrome ? (string) $value->getId() : '';
    }

    public function reverseTransform(mixed $value): ?Aerodrome
    {
        if ($value === null || $value === '') {
            return null;
        }

        $aerodrome = $this->aerodromeRepository->find((int) $value);
        if ($aerodrome === null) {
            throw new TransformationFailedException(sprintf('Aérodrome %s introuvable', $value));
        }

        return $aerodrome;
    }
}
```

`src/Form/Type/AerodromeSelectorType.php` :

```php
<?php

namespace App\Form\Type;

use App\Form\DataTransformer\AerodromeToIdTransformer;
use App\Repository\AerodromeRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Champ caché portant l'id d'un aérodrome ; l'autocomplétion est assurée par
 * le contrôleur Stimulus `aerodrome-search` via le partial component/aerodromeSelector.
 */
final class AerodromeSelectorType extends AbstractType
{
    public function __construct(private readonly AerodromeRepository $aerodromeRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new AerodromeToIdTransformer($this->aerodromeRepository));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'required' => false,
            'invalid_message' => 'aerodromeUnknown',
        ]);
    }

    public function getParent(): string
    {
        return HiddenType::class;
    }
}
```

- [ ] **Step 4 : formulaires**

`src/Form/Onboarding/OnboardingAccountType.php` :

```php
<?php

namespace App\Form\Onboarding;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class OnboardingAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname', TextType::class, [
                'label' => 'firstname',
                'attr' => ['placeholder' => 'firstnamePlaceholder', 'autocomplete' => 'given-name'],
                'constraints' => [new NotBlank(message: 'firstnameRequired')],
            ])
            ->add('lastname', TextType::class, [
                'label' => 'lastname',
                'attr' => ['placeholder' => 'lastnamePlaceholder', 'autocomplete' => 'family-name'],
                'constraints' => [new NotBlank(message: 'lastnameRequired')],
            ])
            ->add('email', EmailType::class, [
                'label' => 'email',
                'attr' => ['placeholder' => 'emailPlaceholder', 'autocomplete' => 'email'],
                'constraints' => [new NotBlank(message: 'emailRequired'), new Email(message: 'emailInvalid')],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'first_options' => [
                    'label' => 'password',
                    'constraints' => [
                        new NotBlank(message: 'passwordRequired'),
                        new Length(min: 6, minMessage: 'passwordMinLength', max: 4096),
                        new PasswordStrength(minScore: PasswordStrength::STRENGTH_WEAK, message: 'passwordTooWeak'),
                        new NotCompromisedPassword(message: 'passwordCompromised'),
                    ],
                ],
                'second_options' => ['label' => 'confirmPassword'],
                'invalid_message' => 'passwordMatch',
            ])
            // Honeypot : invisible pour un humain, rempli par les robots.
            ->add('website', TextType::class, [
                'mapped' => false,
                'required' => false,
                'label' => false,
                'attr' => ['tabindex' => '-1', 'autocomplete' => 'off', 'aria-hidden' => 'true'],
                'row_attr' => ['class' => 'onboarding-honeypot'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
```

`src/Form/Onboarding/OnboardingClubType.php` :

```php
<?php

namespace App\Form\Onboarding;

use App\Entity\Club;
use App\Form\Type\AerodromeSelectorType;
use App\Service\SubdomainService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class OnboardingClubType extends AbstractType
{
    public function __construct(private readonly SubdomainService $subdomainService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'clubName',
                'attr' => ['placeholder' => 'clubNamePlaceholder', 'autocomplete' => 'organization'],
                'constraints' => [new NotBlank(message: 'clubNameRequired'), new Length(max: 255)],
            ])
            ->add('subdomain', TextType::class, [
                'label' => 'clubAddress',
                'attr' => ['autocomplete' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false'],
                'constraints' => [
                    new NotBlank(message: 'subdomainRequired'),
                    new Regex(pattern: SubdomainService::PATTERN, message: 'subdomainInvalid'),
                    new Callback(function (?string $value, ExecutionContextInterface $context): void {
                        if ($value !== null && $this->subdomainService->isReserved($value)) {
                            $context->buildViolation('subdomainReserved')->addViolation();
                        }
                    }),
                ],
            ])
            ->add('aerodrome', AerodromeSelectorType::class)
            ->add('locationLabel', TextType::class, [
                'label' => 'municipality',
                'required' => false,
                'attr' => ['placeholder' => 'municipalityPlaceholder'],
            ])
            ->add('confirmed', HiddenType::class, ['mapped' => false, 'required' => false])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Club::class,
            'constraints' => [
                new Callback(static function (Club $club, ExecutionContextInterface $context): void {
                    if ($club->getAerodrome() === null && trim((string) $club->getLocationLabel()) === '') {
                        $context->buildViolation('aerodromeOrMunicipalityRequired')->atPath('locationLabel')->addViolation();
                    }
                }),
            ],
        ]);
    }
}
```

Note : `Club` porte `#[UniqueEntity(fields: ['subdomain'])]`, validé automatiquement avec `data_class`.

- [ ] **Step 5 : partial et contrôleurs Stimulus**

`templates/component/aerodromeSelector.html.twig` (attend `aerodromeField`, `locationField`, `searchUrl`) :

```twig
{# Sélecteur d'aérodrome : recherche + champ caché + repli « commune » #}
<div data-controller="aerodrome-search"
	 data-aerodrome-search-url-value="{{ searchUrl }}"
	 data-aerodrome-search-empty-text-value="{{ 'aerodromeNoResult'|trans }}">
	<div class="mb-3">
		<label class="form-label required" for="aerodrome-query">{{ 'aerodrome'|trans }}</label>
		<input type="text" id="aerodrome-query" class="form-control" style="min-height:44px;"
			   placeholder="{{ 'aerodromeSearchPlaceholder'|trans }}" autocomplete="off"
			   value="{{ aerodromeField.vars.data ? aerodromeField.vars.data.label : '' }}"
			   data-aerodrome-search-target="query"
			   data-action="input->aerodrome-search#search">
		{{ form_widget(aerodromeField, {attr: {'data-aerodrome-search-target': 'hidden'}}) }}
		{{ form_errors(aerodromeField) }}
		<div class="list-group mt-1" data-aerodrome-search-target="results" hidden></div>
		<div class="form-hint">{{ 'aerodromeHelp'|trans }}</div>
		<a href="#" class="d-inline-block mt-2" style="min-height:44px;line-height:44px;"
		   data-action="aerodrome-search#toggleManual">{{ 'aerodromeNotListed'|trans }}</a>
	</div>
	<div data-aerodrome-search-target="manual" {% if not locationField.vars.value %}hidden{% endif %}>
		{{ form_row(locationField, {attr: {'data-aerodrome-search-target': 'manualField'}}) }}
	</div>
</div>
```

`assets/controllers/aerodrome_search_controller.js` :

```js
import { Controller } from '@hotwired/stimulus';

/**
 * Autocomplétion d'aérodrome.
 *
 * Attendu dans le template (voir component/aerodromeSelector.html.twig) :
 * - data-controller="aerodrome-search" avec data-aerodrome-search-url-value (endpoint JSON ?q=)
 *   et data-aerodrome-search-empty-text-value
 * - targets : query (texte saisi), hidden (id), results (liste), manual (bloc commune), manualField
 */
export default class extends Controller {
    static targets = ['query', 'hidden', 'results', 'manual', 'manualField'];
    static values = { url: String, emptyText: String };

    connect() {
        this.timer = null;
    }

    search() {
        clearTimeout(this.timer);
        this.hiddenTarget.value = '';
        const query = this.queryTarget.value.trim();
        if (query.length < 2) {
            this.clearResults();
            return;
        }
        this.timer = setTimeout(() => this.fetchResults(query), 250);
    }

    async fetchResults(query) {
        const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(query)}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            return;
        }
        this.render(await response.json());
    }

    render(items) {
        this.resultsTarget.innerHTML = '';
        if (items.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'list-group-item text-secondary';
            empty.textContent = this.emptyTextValue;
            this.resultsTarget.appendChild(empty);
        }
        items.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'list-group-item list-group-item-action';
            button.style.minHeight = '44px';
            button.textContent = item.label;
            button.dataset.id = item.id;
            button.dataset.action = 'aerodrome-search#pick';
            this.resultsTarget.appendChild(button);
        });
        this.resultsTarget.hidden = false;
    }

    pick(event) {
        const button = event.currentTarget;
        this.hiddenTarget.value = button.dataset.id;
        this.queryTarget.value = button.textContent;
        this.clearResults();
        if (this.hasManualTarget) {
            this.manualTarget.hidden = true;
            this.manualFieldTarget.value = '';
        }
    }

    toggleManual(event) {
        event.preventDefault();
        this.manualTarget.hidden = !this.manualTarget.hidden;
        if (!this.manualTarget.hidden) {
            this.hiddenTarget.value = '';
            this.queryTarget.value = '';
            this.clearResults();
            this.manualFieldTarget.focus();
        }
    }

    clearResults() {
        this.resultsTarget.innerHTML = '';
        this.resultsTarget.hidden = true;
    }
}
```

`assets/controllers/subdomain_preview_controller.js` :

```js
import { Controller } from '@hotwired/stimulus';

/**
 * Propose un sous-domaine à partir du nom du club, affiche l'adresse complète
 * et vérifie la disponibilité en direct.
 *
 * Attendu : data-controller="subdomain-preview", values checkUrl et domain,
 * targets name, subdomain, preview, status.
 */
export default class extends Controller {
    static targets = ['name', 'subdomain', 'preview', 'status'];
    static values = { checkUrl: String, domain: String };

    connect() {
        this.edited = this.subdomainTarget.value !== '';
        this.timer = null;
        this.update();
    }

    fromName() {
        if (this.edited) {
            return;
        }
        this.subdomainTarget.value = this.slugify(this.nameTarget.value);
        this.update();
    }

    fromSubdomain() {
        this.edited = this.subdomainTarget.value !== '';
        this.update();
    }

    update() {
        const value = this.subdomainTarget.value.trim().toLowerCase();
        this.previewTarget.textContent = `https://${value || '…'}.${this.domainValue}`;
        clearTimeout(this.timer);
        if (value === '') {
            this.statusTarget.textContent = '';
            return;
        }
        this.timer = setTimeout(() => this.check(value), 300);
    }

    async check(value) {
        const response = await fetch(`${this.checkUrlValue}?value=${encodeURIComponent(value)}`, { headers: { Accept: 'application/json' } });
        if (!response.ok) {
            return;
        }
        const data = await response.json();
        this.statusTarget.textContent = data.message;
        this.statusTarget.className = data.available ? 'form-hint text-success' : 'form-hint text-danger';
    }

    slugify(text) {
        return text
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 30)
            .replace(/-+$/g, '');
    }
}
```

- [ ] **Step 6 : squelette du contrôleur et endpoints**

`src/Controller/Public/OnboardingController.php` (les autres actions arrivent aux tâches 9 à 11 ; toutes les dépendances sont déclarées ici) :

```php
<?php

namespace App\Controller\Public;

use App\Controller\ExtendedController;
use App\Entity\Aerodrome;
use App\Repository\AerodromeRepository;
use App\Repository\ClubRepository;
use App\Repository\JoinRequestRepository;
use App\Repository\UserRepository;
use App\Service\Onboarding\ClubDuplicateFinder;
use App\Service\Onboarding\ClubRegistrationService;
use App\Service\Onboarding\EmailVerificationService;
use App\Service\Onboarding\JoinRequestService;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/creer-mon-club', host: 'www.%domain%')]
class OnboardingController extends ExtendedController
{
    use TargetPathTrait;

    public function __construct(
        SubdomainService $subdomainService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly ClubRepository $clubRepository,
        private readonly AerodromeRepository $aerodromeRepository,
        private readonly JoinRequestRepository $joinRequestRepository,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EmailVerificationService $emailVerification,
        private readonly ClubDuplicateFinder $duplicateFinder,
        private readonly ClubRegistrationService $registration,
        private readonly JoinRequestService $joinRequestService,
        #[Autowire(service: 'limiter.onboarding_account')] private readonly RateLimiterFactory $accountLimiter,
        #[Autowire(service: 'limiter.onboarding_resend')] private readonly RateLimiterFactory $resendLimiter,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('/aerodromes', name: 'public_onboarding_aerodromes', methods: ['GET'])]
    public function aerodromes(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        return $this->json(array_map(
            static fn (Aerodrome $aerodrome): array => ['id' => $aerodrome->getId(), 'label' => $aerodrome->getLabel()],
            $this->aerodromeRepository->search($query),
        ));
    }

    #[Route('/sous-domaine', name: 'public_onboarding_subdomain_check', methods: ['GET'])]
    public function subdomainCheck(Request $request): JsonResponse
    {
        $value = mb_strtolower(trim((string) $request->query->get('value', '')));

        if (!$this->subdomainService->isWellFormed($value)) {
            return $this->json(['available' => false, 'message' => $this->translator->trans('subdomainInvalid')]);
        }
        if ($this->subdomainService->isReserved($value)) {
            return $this->json(['available' => false, 'message' => $this->translator->trans('subdomainReserved')]);
        }
        // findOneBy sans filtre actif : un club désactivé garde son sous-domaine (contrainte unique)
        if ($this->clubRepository->findOneBy(['subdomain' => $value]) !== null) {
            return $this->json(['available' => false, 'message' => $this->translator->trans('subdomainTaken')]);
        }

        return $this->json(['available' => true, 'message' => $this->translator->trans('subdomainAvailable')]);
    }
}
```

`EmailVerificationService` et `JoinRequestService` n'existent pas encore : créer pour l'instant deux classes vides dans `src/Service/Onboarding/` (`final class EmailVerificationService {}` et `final class JoinRequestService {}`), remplies aux tâches 9 et 11.

- [ ] **Step 7 : traductions**

Ajouter en fin de `<body>` du catalogue, sous `<!-- Onboarding : sélecteur d'aérodrome et sous-domaine -->`, en numérotant à partir du prochain id libre (vérifier d'abord avec `grep -c 'resname="firstnameRequired"'` etc. quelles clés existent déjà et ne pas les dupliquer) :

| resname | target |
|---|---|
| `aerodrome` | Votre aérodrome |
| `aerodromeSearchPlaceholder` | Nom du terrain, commune ou code OACI |
| `aerodromeHelp` | Tapez les premières lettres et choisissez dans la liste. |
| `aerodromeNoResult` | Aucun terrain trouvé |
| `aerodromeNotListed` | Mon terrain n'est pas dans la liste |
| `aerodromeUnknown` | Ce terrain n'existe pas dans la liste |
| `aerodromeOrMunicipalityRequired` | Indiquez votre aérodrome, ou à défaut votre commune |
| `municipality` | Commune |
| `municipalityPlaceholder` | Ex. : Saint-Rémy-de-Provence |
| `clubAddress` | Adresse de votre club |
| `clubNameRequired` | Indiquez le nom de votre club |
| `subdomainRequired` | Indiquez l'adresse de votre club |
| `subdomainInvalid` | Lettres minuscules, chiffres et tirets seulement, 2 à 30 caractères |
| `subdomainReserved` | Cette adresse est réservée |
| `subdomainTaken` | Cette adresse est déjà prise |
| `subdomainAvailable` | Adresse disponible |
| `firstnameRequired` | Indiquez votre prénom |
| `lastnameRequired` | Indiquez votre nom |
| `emailRequired` | Indiquez votre adresse e-mail |
| `emailInvalid` | Cette adresse e-mail n'est pas valide |
| `passwordTooWeak` | Ce mot de passe est trop simple, allongez-le ou mélangez lettres et chiffres |
| `passwordCompromised` | Ce mot de passe apparaît dans des fuites de données connues, choisissez-en un autre |

Format d'une entrée :

```xml
      <trans-unit id="NNNN" resname="aerodrome">
        <source>aerodrome</source>
        <target>Votre aérodrome</target>
      </trans-unit>
```

Vérifier `config/packages/validator.yaml` : les messages de contraintes sont traduits dans le domaine `validators` par défaut. Si le fichier ne contient pas `translation_domain: messages`, l'ajouter :

```yaml
framework:
    validation:
        translation_domain: messages
```

(`RegistrationFormType` utilise déjà des clés du domaine `messages`, ce réglage les fait aussi fonctionner.)

- [ ] **Step 8 : lancer**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/OnboardingApiTest.php && symfony console lint:xliff translations/ && symfony console lint:twig templates/component
```

Expected : 2 tests OK, lint OK.

- [ ] **Step 9 : commit**

```bash
git add src/Form src/Controller/Public/OnboardingController.php src/Service/Onboarding templates/component/aerodromeSelector.html.twig assets/controllers translations config/packages/validator.yaml tests/Functional/OnboardingApiTest.php
git commit -m "feat(onboarding): formulaires, sélecteur d'aérodrome et vérification de sous-domaine

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 9 : écrans « Compte » et « Confirmez votre e-mail »

**Files:**
- Create: `src/Service/Onboarding/EmailVerificationService.php` (remplace la classe vide), `templates/public/onboarding/account.html.twig`, `verify.html.twig`, `templates/email/verifyEmail.html.twig`
- Modify: `src/Controller/Public/OnboardingController.php`
- Test: `tests/Functional/OnboardingAccountTest.php`

**Interfaces:**
- Consumes: `OnboardingAccountType` (Task 8), `limiter.onboarding_account`, `limiter.onboarding_resend`, `VerifyEmailHelperInterface` (Task 2).
- Produces: routes `public_onboarding_account` (`GET|POST /creer-mon-club`), `public_onboarding_verify` (`GET /creer-mon-club/verification`), `public_onboarding_verify_resend` (`POST /creer-mon-club/verification/renvoyer`), `public_onboarding_verify_confirm` (`GET /creer-mon-club/verification/confirmer?id=&signature…`) ; `EmailVerificationService::send(User): void`, `confirm(Request, User): void`.

- [ ] **Step 1 : tests**

`tests/Functional/OnboardingAccountTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\User;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class OnboardingAccountTest extends FunctionalTestCase
{
    public function testAccountCreationSendsVerificationAndRedirects(): void
    {
        $this->client->request('GET', $this->www('/creer-mon-club'));
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('Créer mon compte', [
            'onboarding_account[firstname]' => 'Jean',
            'onboarding_account[lastname]' => 'Dupont',
            'onboarding_account[email]' => 'jean@example.test',
            'onboarding_account[plainPassword][first]' => 'Planeur-Beauce-2026',
            'onboarding_account[plainPassword][second]' => 'Planeur-Beauce-2026',
        ]);

        $this->assertResponseRedirects($this->www('/creer-mon-club/verification'));
        $this->assertEmailCount(1);
        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'jean@example.test']);
        $this->assertNotNull($user);
        $this->assertFalse($user->isVerified());

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'jean@example.test');
    }

    public function testHoneypotSilentlyDropsSubmission(): void
    {
        $this->client->request('GET', $this->www('/creer-mon-club'));
        $this->client->submitForm('Créer mon compte', [
            'onboarding_account[firstname]' => 'Bot',
            'onboarding_account[lastname]' => 'Bot',
            'onboarding_account[email]' => 'bot@example.test',
            'onboarding_account[plainPassword][first]' => 'Planeur-Beauce-2026',
            'onboarding_account[plainPassword][second]' => 'Planeur-Beauce-2026',
            'onboarding_account[website]' => 'http://spam.example',
        ]);

        $this->assertResponseRedirects($this->www('/'));
        $this->assertEmailCount(0);
        $this->assertNull($this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'bot@example.test']));
    }

    public function testExistingEmailShowsLoginHint(): void
    {
        $this->createUser('deja@example.test');

        $this->client->request('GET', $this->www('/creer-mon-club'));
        $this->client->submitForm('Créer mon compte', [
            'onboarding_account[firstname]' => 'Jean',
            'onboarding_account[lastname]' => 'Dupont',
            'onboarding_account[email]' => 'deja@example.test',
            'onboarding_account[plainPassword][first]' => 'Planeur-Beauce-2026',
            'onboarding_account[plainPassword][second]' => 'Planeur-Beauce-2026',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Un compte existe déjà avec cette adresse');
    }

    public function testVerifiedLoggedInUserSkipsToClubStep(): void
    {
        $this->client->loginUser($this->createUser('verifie@example.test'));

        $this->client->request('GET', $this->www('/creer-mon-club'));

        $this->assertResponseRedirects($this->www('/creer-mon-club/club'));
    }

    public function testConfirmationLinkVerifiesAndRedirects(): void
    {
        $user = $this->createUser('averifier@example.test', verified: false);
        $signature = static::getContainer()->get(VerifyEmailHelperInterface::class)
            ->generateSignature('public_onboarding_verify_confirm', (string) $user->getId(), (string) $user->getEmail(), ['id' => $user->getId()]);

        $this->client->request('GET', $signature->getSignedUrl());

        $this->assertResponseRedirects($this->www('/creer-mon-club/club'));
        $this->entityManager()->refresh($user);
        $this->assertTrue($user->isVerified());
    }

    public function testTamperedConfirmationLinkIsRejected(): void
    {
        $user = $this->createUser('averifier2@example.test', verified: false);

        $this->client->request('GET', $this->www('/creer-mon-club/verification/confirmer?id=' . $user->getId() . '&expires=1&signature=xyz&token=abc'));

        $this->assertResponseRedirects($this->www('/creer-mon-club'));
        $this->entityManager()->refresh($user);
        $this->assertFalse($user->isVerified());
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Functional/OnboardingAccountTest.php
```

Expected : 404 sur `/creer-mon-club`.

- [ ] **Step 3 : service de vérification**

`src/Service/Onboarding/EmailVerificationService.php` :

```php
<?php

namespace App\Service\Onboarding;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class EmailVerificationService
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function send(User $user): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            'public_onboarding_verify_confirm',
            (string) $user->getId(),
            (string) $user->getEmail(),
            ['id' => $user->getId()],
        );

        $this->mailer->send((new TemplatedEmail())
            ->from(new Address('contact@tarmac.center', 'Tarmac'))
            ->to((string) $user->getEmail())
            ->subject('Confirmez votre adresse e-mail')
            ->htmlTemplate('email/verifyEmail.html.twig')
            ->context([
                'user' => $user,
                'signedUrl' => $signature->getSignedUrl(),
                'expiresAt' => $signature->getExpiresAt(),
            ]));
    }

    /**
     * @throws \SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface
     */
    public function confirm(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $user->getId(), (string) $user->getEmail());
        $user->setVerified(true);
        $this->entityManager->flush();
    }
}
```

`templates/email/verifyEmail.html.twig` :

```twig
{% extends 'email/base.html.twig' %}

{% block title %}Confirmez votre e-mail - Tarmac{% endblock %}
{% block preheader %}Un clic pour confirmer votre adresse et créer votre club{% endblock %}
{% block email_title %}Confirmez votre e-mail{% endblock %}

{% block email_body %}
	<h2 style="margin-top: 0; font-size: 20px; color: #111827; font-weight: 600;">Bonjour {{ user.firstname }},</h2>

	<p style="margin: 16px 0; color: #4b5563;">Cliquez sur le bouton ci-dessous pour confirmer votre adresse e-mail. Vous reprendrez ensuite la création de votre club là où vous l'avez laissée.</p>

	<table cellpadding="0" cellspacing="0" style="width: 100%; margin: 30px 0;">
		<tr>
			<td align="center">
				<a href="{{ signedUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #345B7C; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">Confirmer mon adresse</a>
			</td>
		</tr>
	</table>

	<p style="font-size: 14px; color: #667382; margin: 16px 0;">Ce lien est valable jusqu'au {{ expiresAt|date('d/m/Y à H:i') }}. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.</p>

	<hr style="border: 0; border-top: 1px solid #e8ebee; margin: 30px 0;">

	<p style="font-size: 13px; color: #667382; margin: 16px 0;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
		<a href="{{ signedUrl }}" style="color: #345B7C; word-break: break-all; text-decoration: none;">{{ signedUrl }}</a></p>
{% endblock %}
```

- [ ] **Step 4 : actions du contrôleur**

Ajouter dans `OnboardingController` les `use` suivants :

```php
use App\Entity\User;
use App\Form\Onboarding\OnboardingAccountType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Response;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
```

et les méthodes :

```php
    #[Route('', name: 'public_onboarding_account', methods: ['GET', 'POST'])]
    public function account(Request $request): Response
    {
        $current = $this->getUser();
        if ($current instanceof User) {
            return $this->redirectToRoute($current->isVerified() ? 'public_onboarding_club' : 'public_onboarding_verify');
        }

        $user = new User();
        $form = $this->createForm(OnboardingAccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Honeypot rempli : un robot. On ne crée rien et on ne le dit pas.
            if ((string) $form->get('website')->getData() !== '') {
                return $this->redirectToRoute('public_landing');
            }

            if (!$this->accountLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('danger', 'tooManyAttempts');

                return $this->redirectToRoute('public_onboarding_account');
            }

            if ($form->isValid()) {
                if ($this->userRepository->findOneBy(['email' => $user->getEmail()]) !== null) {
                    $form->get('email')->addError(new FormError($this->translator->trans('emailAlreadyRegistered')));
                    // Après connexion, l'utilisateur reprend à l'étape « Club ».
                    $this->saveTargetPath($request->getSession(), 'main', $this->generateUrl('public_onboarding_club'));
                } else {
                    $user->setPassword($this->passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
                    $user->setVerified(false);
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    $this->emailVerification->send($user);
                    $this->security->login($user);

                    return $this->redirectToRoute('public_onboarding_verify');
                }
            }
        }

        return $this->render('public/onboarding/account.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/verification', name: 'public_onboarding_verify', methods: ['GET'])]
    public function verify(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('public_onboarding_account');
        }
        if ($user->isVerified()) {
            return $this->redirectToRoute('public_onboarding_club');
        }

        return $this->render('public/onboarding/verify.html.twig', [
            'email' => $user->getEmail(),
        ]);
    }

    #[Route('/verification/renvoyer', name: 'public_onboarding_verify_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->isVerified()) {
            return $this->redirectToRoute('public_onboarding_account');
        }
        if (!$this->isCsrfTokenValid('resend-verification', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->resendLimiter->create((string) $user->getId())->consume()->isAccepted()) {
            $this->addFlash('danger', 'tooManyAttempts');
        } else {
            $this->emailVerification->send($user);
            $this->addFlash('success', 'verificationEmailSent');
        }

        return $this->redirectToRoute('public_onboarding_verify');
    }

    #[Route('/verification/confirmer', name: 'public_onboarding_verify_confirm', methods: ['GET'])]
    public function confirm(Request $request): Response
    {
        $user = $this->userRepository->find($request->query->getInt('id'));
        if ($user === null) {
            $this->addFlash('danger', 'verificationLinkInvalid');

            return $this->redirectToRoute('public_onboarding_account');
        }

        try {
            $this->emailVerification->confirm($request, $user);
        } catch (VerifyEmailExceptionInterface) {
            $this->addFlash('danger', 'verificationLinkExpired');

            return $this->redirectToRoute($this->getUser() instanceof User ? 'public_onboarding_verify' : 'public_onboarding_account');
        }

        if ($this->getUser() !== $user) {
            $this->security->login($user);
        }
        $this->addFlash('success', 'emailVerified');

        return $this->redirectToRoute('public_onboarding_club');
    }
```

- [ ] **Step 5 : templates**

`templates/public/onboarding/account.html.twig` :

```twig
{% extends 'public/base.html.twig' %}

{% block title %}{{ 'onboardingAccountTitle'|trans }}{% endblock %}

{% block stylesheets %}
	{{ parent() }}
	<style>
		.onboarding-honeypot { position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden; }
		.onboarding-steps { display: flex; gap: 0.5rem; justify-content: center; margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--td-ink-3); }
		.onboarding-steps .is-current { color: var(--td-ink-1); font-weight: 600; }
	</style>
{% endblock %}

{% block body %}
	<div class="page page-center">
		<div class="container container-tight py-4">
			<div class="text-center mb-4">
				<a href="{{ path('public_landing') }}" class="navbar-brand navbar-brand-autodark">
					<img src="{{ asset('logo.svg') }}" height="48" alt="Tarmac">
				</a>
			</div>
			<div class="tarmac-panel">
				<div class="onboarding-steps">
					<span class="is-current">{{ 'onboardingStepAccount'|trans }}</span><span>·</span>
					<span>{{ 'onboardingStepEmail'|trans }}</span><span>·</span>
					<span>{{ 'onboardingStepClub'|trans }}</span>
				</div>
				<h2 style="font-size:1.4rem;font-weight:800;text-align:center;margin:0 0 0.5rem;letter-spacing:-0.015em;">{{ 'onboardingAccountTitle'|trans }}</h2>
				<p class="text-center text-secondary mb-4">{{ 'onboardingAccountLead'|trans }}</p>

				{% for message in app.flashes('danger') %}
					<div class="alert alert-danger">{{ message|trans }}</div>
				{% endfor %}

				{{ form_start(form) }}
				{{ form_errors(form) }}
				<div class="row">
					<div class="col-12 col-sm-6">{{ form_row(form.firstname) }}</div>
					<div class="col-12 col-sm-6">{{ form_row(form.lastname) }}</div>
				</div>
				{{ form_row(form.email) }}
				{% if form.email.vars.errors|length > 0 %}
					<p class="mb-3" style="font-size:0.9rem;">
						<a href="{{ path('public_login') }}"><i class="ti ti-login me-1"></i>{{ 'loginToContinue'|trans }}</a>
					</p>
				{% endif %}
				{{ form_row(form.plainPassword.first) }}
				{{ form_row(form.plainPassword.second) }}
				{{ form_row(form.website) }}

				<button type="submit" class="btn btn-primary w-100 mt-3" style="min-height: 48px;">
					<i class="ti ti-check me-2"></i>{{ 'createMyAccount'|trans }}
				</button>
				{{ form_end(form) }}

				<p class="text-center text-secondary mt-4 mb-0" style="font-size:0.85rem;">{{ 'onboardingFreeNote'|trans }}</p>
			</div>
			<div class="text-center mt-3">
				<a href="{{ path('public_login') }}" style="color:var(--td-ink-2);font-size:0.85rem;">
					<i class="ti ti-login me-1"></i>{{ 'alreadyHaveAccount'|trans }}
				</a>
			</div>
		</div>
	</div>
{% endblock %}
```

`templates/public/onboarding/verify.html.twig` :

```twig
{% extends 'public/base.html.twig' %}

{% block title %}{{ 'onboardingVerifyTitle'|trans }}{% endblock %}

{% block body %}
	<div class="page page-center">
		<div class="container container-tight py-4">
			<div class="text-center mb-4">
				<a href="{{ path('public_landing') }}" class="navbar-brand navbar-brand-autodark">
					<img src="{{ asset('logo.svg') }}" height="48" alt="Tarmac">
				</a>
			</div>
			<div class="tarmac-panel text-center">
				<div style="font-size:2.5rem;color:var(--td-glow);margin-bottom:0.75rem;"><i class="ti ti-mail-fast"></i></div>
				<h2 style="font-size:1.4rem;font-weight:800;margin:0 0 0.75rem;letter-spacing:-0.015em;">{{ 'onboardingVerifyTitle'|trans }}</h2>
				<p class="text-secondary">{{ 'onboardingVerifyBody'|trans({email: email}) }}</p>

				{% for type, messages in app.flashes(['success', 'danger']) %}
					{% for message in messages %}
						<div class="alert alert-{{ type }}">{{ message|trans }}</div>
					{% endfor %}
				{% endfor %}

				<form method="post" action="{{ path('public_onboarding_verify_resend') }}" class="mt-4">
					<input type="hidden" name="_token" value="{{ csrf_token('resend-verification') }}">
					<button type="submit" class="btn btn-outline-primary w-100" style="min-height:48px;">
						<i class="ti ti-refresh me-2"></i>{{ 'resendVerificationEmail'|trans }}
					</button>
				</form>
				<p class="text-secondary mt-4 mb-0" style="font-size:0.85rem;">{{ 'onboardingVerifyHint'|trans }}</p>
			</div>
		</div>
	</div>
{% endblock %}
```

- [ ] **Step 6 : traductions**

Sous `<!-- Onboarding : compte et vérification d'e-mail -->` :

| resname | target |
|---|---|
| `onboardingStepAccount` | 1. Votre compte |
| `onboardingStepEmail` | 2. Votre e-mail |
| `onboardingStepClub` | 3. Votre club |
| `onboardingAccountTitle` | Créez votre compte |
| `onboardingAccountLead` | Tarmac est gratuit, sans publicité, fait par un pilote bénévole pour les clubs associatifs. Quelques minutes suffisent. |
| `onboardingFreeNote` | Aucune carte bancaire, aucun engagement. Vos données restent celles de votre club. |
| `createMyAccount` | Créer mon compte |
| `alreadyHaveAccount` | Vous avez déjà un compte ? Connectez-vous |
| `emailAlreadyRegistered` | Un compte existe déjà avec cette adresse |
| `loginToContinue` | Connectez-vous pour continuer la création de votre club |
| `tooManyAttempts` | Trop de tentatives, réessayez dans une heure |
| `onboardingVerifyTitle` | Confirmez votre e-mail |
| `onboardingVerifyBody` | Nous avons envoyé un lien à {email}. Cliquez dessus pour continuer. |
| `onboardingVerifyHint` | Rien reçu ? Regardez dans vos courriers indésirables, puis renvoyez le lien. |
| `resendVerificationEmail` | Renvoyer le lien |
| `verificationEmailSent` | Lien renvoyé, vérifiez votre boîte de réception |
| `verificationLinkInvalid` | Ce lien n'est pas valide |
| `verificationLinkExpired` | Ce lien a expiré, renvoyez-en un nouveau |
| `emailVerified` | Adresse confirmée, créons votre club |

- [ ] **Step 7 : lancer**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/OnboardingAccountTest.php && symfony console lint:twig templates/public/onboarding templates/email && symfony console lint:xliff translations/
```

Expected : 6 tests OK. Ouvrir `https://www.tarmac.wip/creer-mon-club` sur un viewport de 320 px : un seul bouton principal, champs empilés.

- [ ] **Step 8 : commit**

```bash
git add src/Controller/Public/OnboardingController.php src/Service/Onboarding/EmailVerificationService.php templates/public/onboarding templates/email/verifyEmail.html.twig translations tests/Functional/OnboardingAccountTest.php
git commit -m "feat(onboarding): création de compte avec confirmation d'e-mail

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 10 : écrans « Club », « Doublon » et « Bienvenue »

**Files:**
- Create: `templates/public/onboarding/club.html.twig`, `duplicate.html.twig`, `done.html.twig`
- Modify: `src/Controller/Public/OnboardingController.php`
- Test: `tests/Functional/OnboardingClubTest.php`

**Interfaces:**
- Consumes: `OnboardingClubType`, partial `component/aerodromeSelector.html.twig`, contrôleur Stimulus `subdomain-preview` (Task 8), `ClubDuplicateFinder::find` et `DuplicateReport` (Task 5), `ClubRegistrationService::register` (Task 7), `is_granted('MANAGE', club)` avec sujet (Task 2).
- Produces: routes `public_onboarding_club` (`GET|POST /creer-mon-club/club`), `public_onboarding_done` (`GET /creer-mon-club/bienvenue/{id}`).

- [ ] **Step 1 : tests**

`tests/Functional/OnboardingClubTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\Aerodrome;
use App\Entity\Club;

class OnboardingClubTest extends FunctionalTestCase
{
    private function aerodrome(string $name, float $lat, float $lng): Aerodrome
    {
        $aerodrome = (new Aerodrome())->setSourceId(random_int(1, 1_000_000))->setName($name)->setLatitude($lat)->setLongitude($lng)->refreshSearchIndex();
        $this->entityManager()->persist($aerodrome);
        $this->entityManager()->flush();

        return $aerodrome;
    }

    public function testUnverifiedUserIsSentBackToVerification(): void
    {
        $this->client->loginUser($this->createUser('nonverifie@example.test', verified: false));

        $this->client->request('GET', $this->www('/creer-mon-club/club'));

        $this->assertResponseRedirects($this->www('/creer-mon-club/verification'));
    }

    public function testClubCreationMakesUserManagerAndNotifiesAdmins(): void
    {
        $this->createUser('admin@example.test', admin: true);
        $president = $this->createUser('president@example.test');
        $this->client->loginUser($president);
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);

        $this->client->request('GET', $this->www('/creer-mon-club/club'));
        $this->assertResponseIsSuccessful();
        $this->client->submitForm('Créer mon club', [
            'onboarding_club[name]' => 'Planeurs de Beauce',
            'onboarding_club[subdomain]' => 'beauce',
            'onboarding_club[aerodrome]' => (string) $field->getId(),
        ]);

        $club = $this->entityManager()->getRepository(Club::class)->findOneBy(['subdomain' => 'beauce']);
        $this->assertNotNull($club);
        $this->assertResponseRedirects($this->www('/creer-mon-club/bienvenue/' . $club->getId()));
        $this->assertSame($president->getId(), $club->getCreatedBy()->getId());
        $this->assertTrue($club->getMembership($president)->isManager());
        $this->assertEqualsWithDelta(48.5, $club->getLatitude(), 0.001);
        $this->assertEmailCount(2);

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'beauce.' . static::getContainer()->getParameter('domain'));
    }

    public function testReservedSubdomainIsRejected(): void
    {
        $this->client->loginUser($this->createUser('president@example.test'));
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);

        $this->client->request('GET', $this->www('/creer-mon-club/club'));
        $this->client->submitForm('Créer mon club', [
            'onboarding_club[name]' => 'Planeurs de Beauce',
            'onboarding_club[subdomain]' => 'admin',
            'onboarding_club[aerodrome]' => (string) $field->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Cette adresse est réservée');
    }

    public function testDuplicateScreenThenConfirmedCreation(): void
    {
        $this->client->loginUser($this->createUser('president@example.test'));
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);
        $existing = $this->createClub('Centre de Vol à Voile de Bailleau', 'cvvb');
        $existing->setAerodrome($field);
        $this->entityManager()->flush();

        $this->client->request('GET', $this->www('/creer-mon-club/club'));
        $this->client->submitForm('Créer mon club', [
            'onboarding_club[name]' => 'CVV Bailleau',
            'onboarding_club[subdomain]' => 'cvv-bailleau',
            'onboarding_club[aerodrome]' => (string) $field->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Ce club existe déjà');
        $this->assertSelectorTextContains('body', 'Centre de Vol à Voile de Bailleau');
        $this->assertNull($this->entityManager()->getRepository(Club::class)->findOneBy(['subdomain' => 'cvv-bailleau']));

        $this->client->submitForm("Ce n'est pas mon club, continuer");

        $this->assertNotNull($this->entityManager()->getRepository(Club::class)->findOneBy(['subdomain' => 'cvv-bailleau']));
    }

    public function testNeighbourOnSameAerodromeIsShownAsInformation(): void
    {
        $this->client->loginUser($this->createUser('president@example.test'));
        $field = $this->aerodrome('Bailleau', 48.5, 1.6);
        $neighbour = $this->createClub('Aéroclub de Bailleau', 'acb');
        $neighbour->setAerodrome($field);
        $this->entityManager()->flush();

        $this->client->request('GET', $this->www('/creer-mon-club/club'));
        $this->client->submitForm('Créer mon club', [
            'onboarding_club[name]' => 'Planeurs de Beauce',
            'onboarding_club[subdomain]' => 'beauce',
            'onboarding_club[aerodrome]' => (string) $field->getId(),
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', "D'autres clubs sont déjà sur ce terrain");
        $this->assertSelectorTextNotContains('body', 'Ce club existe déjà');
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Functional/OnboardingClubTest.php
```

Expected : 404.

- [ ] **Step 3 : actions**

Ajouter les `use` :

```php
use App\Entity\Club;
use App\Form\Onboarding\OnboardingClubType;
use App\Service\Onboarding\DuplicateReport;
```

et les méthodes :

```php
    /** Renvoie l'utilisateur connecté et vérifié, ou la redirection vers l'écran adéquat. */
    private function verifiedUserOrRedirect(): User|Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('public_onboarding_account');
        }
        if (!$user->isVerified()) {
            return $this->redirectToRoute('public_onboarding_verify');
        }

        return $user;
    }

    #[Route('/club', name: 'public_onboarding_club', methods: ['GET', 'POST'])]
    public function club(Request $request): Response
    {
        $user = $this->verifiedUserOrRedirect();
        if ($user instanceof Response) {
            return $user;
        }

        $club = new Club();
        $form = $this->createForm(OnboardingClubType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $report = $this->duplicateFinder->find((string) $club->getName(), $club->getAerodrome());
            $confirmed = $form->get('confirmed')->getData() === '1';

            if (!$report->isEmpty() && !$confirmed) {
                return $this->render('public/onboarding/duplicate.html.twig', [
                    'form' => $form,
                    'club' => $club,
                    'report' => $report,
                ]);
            }

            $this->registration->register($user, $club, $confirmed ? $report : new DuplicateReport());

            return $this->redirectToRoute('public_onboarding_done', ['id' => $club->getId()]);
        }

        return $this->render('public/onboarding/club.html.twig', [
            'form' => $form,
            'domain' => $this->subdomainService->getDomain(),
        ]);
    }

    #[Route('/bienvenue/{id}', name: 'public_onboarding_done', methods: ['GET'])]
    public function done(Club $club): Response
    {
        $this->denyAccessUnlessGranted('MANAGE', $club);

        return $this->render('public/onboarding/done.html.twig', [
            'club' => $club,
            'clubUrl' => $this->subdomainService->generateClubUrl((string) $club->getSubdomain()),
        ]);
    }
```

- [ ] **Step 4 : templates**

`templates/public/onboarding/club.html.twig` :

```twig
{% extends 'public/base.html.twig' %}

{% block title %}{{ 'onboardingClubTitle'|trans }}{% endblock %}

{% block body %}
	<div class="page page-center">
		<div class="container container-tight py-4">
			<div class="text-center mb-4">
				<a href="{{ path('public_landing') }}" class="navbar-brand navbar-brand-autodark">
					<img src="{{ asset('logo.svg') }}" height="48" alt="Tarmac">
				</a>
			</div>
			<div class="tarmac-panel">
				<h2 style="font-size:1.4rem;font-weight:800;text-align:center;margin:0 0 0.5rem;letter-spacing:-0.015em;">{{ 'onboardingClubTitle'|trans }}</h2>
				<p class="text-center text-secondary mb-4">{{ 'onboardingClubLead'|trans }}</p>

				{% for message in app.flashes('success') %}
					<div class="alert alert-success">{{ message|trans }}</div>
				{% endfor %}

				{{ form_start(form, {attr: {
					'data-controller': 'subdomain-preview',
					'data-subdomain-preview-check-url-value': path('public_onboarding_subdomain_check'),
					'data-subdomain-preview-domain-value': domain,
				}}) }}
				{{ form_errors(form) }}

				{{ form_row(form.name, {attr: {'data-subdomain-preview-target': 'name', 'data-action': 'input->subdomain-preview#fromName'}}) }}

				<div class="mb-3">
					{{ form_label(form.subdomain) }}
					<div class="input-group" style="min-height:44px;">
						<span class="input-group-text">https://</span>
						{{ form_widget(form.subdomain, {attr: {'data-subdomain-preview-target': 'subdomain', 'data-action': 'input->subdomain-preview#fromSubdomain'}}) }}
						<span class="input-group-text">.{{ domain }}</span>
					</div>
					{{ form_errors(form.subdomain) }}
					<div class="form-hint">{{ 'clubAddressHelp'|trans }} <strong data-subdomain-preview-target="preview"></strong></div>
					<div class="form-hint" data-subdomain-preview-target="status"></div>
				</div>

				{% include 'component/aerodromeSelector.html.twig' with {
					aerodromeField: form.aerodrome,
					locationField: form.locationLabel,
					searchUrl: path('public_onboarding_aerodromes'),
				} %}

				{{ form_widget(form.confirmed) }}

				<button type="submit" class="btn btn-primary w-100 mt-3" style="min-height: 48px;">
					<i class="ti ti-windsock me-2"></i>{{ 'createMyClub'|trans }}
				</button>
				{{ form_end(form) }}
			</div>
		</div>
	</div>
{% endblock %}
```

`templates/public/onboarding/duplicate.html.twig` (renvoie le formulaire caché avec `confirmed=1`) :

```twig
{% extends 'public/base.html.twig' %}

{% block title %}{{ report.duplicates is not empty ? 'onboardingDuplicateTitle'|trans : 'onboardingNeighboursTitle'|trans }}{% endblock %}

{% block body %}
	<div class="page page-center">
		<div class="container container-tight py-4">
			<div class="text-center mb-4">
				<a href="{{ path('public_landing') }}" class="navbar-brand navbar-brand-autodark">
					<img src="{{ asset('logo.svg') }}" height="48" alt="Tarmac">
				</a>
			</div>
			<div class="tarmac-panel">
				{% if report.duplicates is not empty %}
					<h2 style="font-size:1.4rem;font-weight:800;text-align:center;margin:0 0 0.5rem;">{{ 'onboardingDuplicateTitle'|trans }}</h2>
					<p class="text-center text-secondary mb-4">{{ 'onboardingDuplicateLead'|trans }}</p>
					<div class="list-group mb-4">
						{% for other in report.duplicates %}
							<div class="list-group-item">
								<div class="fw-bold">{{ other.name }}</div>
								<div class="text-secondary" style="font-size:0.9rem;">{{ other.locationText }}{% if other.locationText %} · {% endif %}{{ other.subdomain }}.{{ subdomainService.domain }}</div>
								<a href="{{ path('public_onboarding_join_request', {id: other.id}) }}" class="btn btn-outline-primary mt-2" style="min-height:44px;">
									<i class="ti ti-door-enter me-2"></i>{{ 'requestToJoin'|trans }}
								</a>
							</div>
						{% endfor %}
					</div>
				{% else %}
					<h2 style="font-size:1.4rem;font-weight:800;text-align:center;margin:0 0 0.5rem;">{{ 'onboardingNeighboursTitle'|trans }}</h2>
					<p class="text-center text-secondary mb-4">{{ 'onboardingNeighboursHint'|trans }}</p>
				{% endif %}

				{% if report.neighbours is not empty %}
					{% if report.duplicates is not empty %}<h3 style="font-size:1rem;">{{ 'onboardingNeighboursSubtitle'|trans }}</h3>{% endif %}
					<div class="list-group mb-4">
						{% for other in report.neighbours %}
							<div class="list-group-item">
								<div class="fw-bold">{{ other.name }}</div>
								<div class="text-secondary" style="font-size:0.9rem;">{{ other.subdomain }}.{{ subdomainService.domain }}</div>
								<a href="{{ path('public_onboarding_join_request', {id: other.id}) }}" class="btn btn-outline-primary mt-2" style="min-height:44px;">
									<i class="ti ti-door-enter me-2"></i>{{ 'requestToJoin'|trans }}
								</a>
							</div>
						{% endfor %}
					</div>
				{% endif %}

				{{ form_start(form, {action: path('public_onboarding_club')}) }}
				<input type="hidden" name="{{ field_name(form.name) }}" value="{{ field_value(form.name) }}">
				<input type="hidden" name="{{ field_name(form.subdomain) }}" value="{{ field_value(form.subdomain) }}">
				<input type="hidden" name="{{ field_name(form.locationLabel) }}" value="{{ field_value(form.locationLabel) }}">
				{{ form_widget(form.aerodrome) }}
				<input type="hidden" name="{{ field_name(form.confirmed) }}" value="1">
				{{ form_widget(form._token) }}
				<button type="submit" class="btn btn-primary w-100" style="min-height:48px;">
					<i class="ti ti-arrow-right me-2"></i>{{ 'notMyClubContinue'|trans }}
				</button>
				{{ form_end(form, {render_rest: false}) }}
			</div>
		</div>
	</div>
{% endblock %}
```

`render_rest: false` évite que `form_end` réaffiche les champs visibles ; le jeton CSRF est donc rendu à la main, à l'intérieur du `<form>`.

`templates/public/onboarding/done.html.twig` :

```twig
{% extends 'public/base.html.twig' %}

{% block title %}{{ 'onboardingDoneTitle'|trans }}{% endblock %}

{% block body %}
	<div class="page page-center">
		<div class="container container-tight py-4">
			<div class="text-center mb-4">
				<a href="{{ path('public_landing') }}" class="navbar-brand navbar-brand-autodark">
					<img src="{{ asset('logo.svg') }}" height="48" alt="Tarmac">
				</a>
			</div>
			<div class="tarmac-panel text-center">
				<div style="font-size:2.5rem;color:var(--td-success);margin-bottom:0.75rem;"><i class="ti ti-circle-check"></i></div>
				<h2 style="font-size:1.4rem;font-weight:800;margin:0 0 0.75rem;">{{ 'onboardingDoneTitle'|trans({club: club.name}) }}</h2>
				<p class="text-secondary">{{ 'onboardingDoneAddress'|trans }}</p>
				<p style="font-size:1.1rem;font-weight:600;word-break:break-all;"><a href="{{ clubUrl }}">{{ clubUrl }}</a></p>
				<p class="text-secondary">{{ 'onboardingDoneBody'|trans }}</p>
				<a href="{{ clubUrl }}" class="btn btn-primary w-100 mt-3" style="min-height:48px;">
					<i class="ti ti-arrow-right me-2"></i>{{ 'openMyClub'|trans }}
				</a>
			</div>
		</div>
	</div>
{% endblock %}
```

- [ ] **Step 5 : traductions**

Sous `<!-- Onboarding : club, doublon, bienvenue -->` :

| resname | target |
|---|---|
| `onboardingClubTitle` | Votre club |
| `onboardingClubLead` | Le nom tel que vos membres le connaissent, et le terrain où vous volez. |
| `clubAddressHelp` | Vos membres se connecteront sur |
| `createMyClub` | Créer mon club |
| `onboardingDuplicateTitle` | Ce club existe déjà ? |
| `onboardingDuplicateLead` | Un club au nom proche est déjà sur Tarmac, tout près de chez vous. Si c'est le vôtre, demandez à le rejoindre plutôt que d'en créer un second. |
| `onboardingNeighboursTitle` | D'autres clubs sont déjà sur ce terrain |
| `onboardingNeighboursHint` | Si votre club est l'un d'eux, demandez à le rejoindre. Sinon, continuez : plusieurs clubs peuvent partager un aérodrome. |
| `onboardingNeighboursSubtitle` | Également sur ce terrain |
| `requestToJoin` | Demander à rejoindre ce club |
| `notMyClubContinue` | Ce n'est pas mon club, continuer |
| `onboardingDoneTitle` | {club} est prêt |
| `onboardingDoneAddress` | Vos membres se connecteront à cette adresse, notez-la : |
| `onboardingDoneBody` | Le tableau de bord vous indique ce qu'il reste à configurer. Prenez les points dans l'ordre, chacun prend quelques minutes. |
| `openMyClub` | Ouvrir mon club |

- [ ] **Step 6 : lancer**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/OnboardingClubTest.php && symfony console lint:twig templates/public/onboarding && symfony console lint:xliff translations/
```

Expected : 5 tests OK. Vérifier à la main dans le navigateur, à 320 px, que taper un nom remplit l'adresse et que l'autocomplétion d'aérodrome propose des résultats.

- [ ] **Step 7 : commit**

```bash
git add src/Controller/Public/OnboardingController.php templates/public/onboarding translations tests/Functional/OnboardingClubTest.php
git commit -m "feat(onboarding): création du club avec détection de doublon et écran de bienvenue

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 11 : demande d'adhésion (tunnel, e-mail, côté club, admin)

**Files:**
- Create: `src/Service/Onboarding/JoinRequestService.php` (remplace la classe vide), `src/Form/Onboarding/JoinRequestType.php`, `templates/public/onboarding/joinRequest.html.twig`, `joinRequestSent.html.twig`, `templates/email/joinRequest.html.twig`
- Modify: `src/Controller/Public/OnboardingController.php`, `src/Controller/Club/InvitationController.php`, `src/Service/InvitationService.php`, `templates/club/members/invitations/index.html.twig`
- Test: `tests/Functional/JoinRequestTest.php`

**Interfaces:**
- Consumes: `JoinRequest`, `JoinRequestRepository` (Task 6), `SubdomainService::generateClubUrl`.
- Produces: `JoinRequestService::submit(User, Club, ?string $message): JoinRequest` ; routes `public_onboarding_join_request` (`GET|POST /creer-mon-club/rejoindre/{id}`), `club_join_request_ignore` (`POST /invitations/demandes/{id}/ignorer`) ; `club_invitation_create` accepte `?email=&firstname=&lastname=` ; `InvitationService::acceptInvitation` clôt la demande ouverte du même utilisateur.

- [ ] **Step 1 : tests**

`tests/Functional/JoinRequestTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\JoinRequest;
use App\Entity\Membership;

class JoinRequestTest extends FunctionalTestCase
{
    public function testRequestNotifiesManagersAndIsListedForThem(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();

        $requester = $this->createUser('candidat@example.test');
        $this->client->loginUser($requester);

        $this->client->request('GET', $this->www('/creer-mon-club/rejoindre/' . $club->getId()));
        $this->assertResponseIsSuccessful();
        $this->client->submitForm('Envoyer ma demande', ['join_request[message]' => 'Je suis membre depuis 2019.']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'ont été prévenus');
        $this->assertEmailCount(1);
        $email = $this->getMailerMessage();
        $this->assertEmailAddressContains($email, 'to', 'manager@example.test');
        $this->assertEmailHtmlBodyContains($email, 'candidat@example.test');
        $this->assertEmailHtmlBodyContains($email, 'Je suis membre depuis 2019.');

        $request = $this->entityManager()->getRepository(JoinRequest::class)->findOneBy(['user' => $requester, 'club' => $club]);
        $this->assertNotNull($request);
        $this->assertTrue($request->isOpen());

        $this->client->loginUser($manager);
        $this->client->request('GET', 'https://beauce.' . static::getContainer()->getParameter('domain') . '/invitations');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'candidat@example.test');
    }

    public function testSecondRequestIsNotDuplicated(): void
    {
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $requester = $this->createUser('candidat@example.test');
        $this->client->loginUser($requester);

        $this->client->request('GET', $this->www('/creer-mon-club/rejoindre/' . $club->getId()));
        $this->client->submitForm('Envoyer ma demande', ['join_request[message]' => '']);
        $this->client->request('GET', $this->www('/creer-mon-club/rejoindre/' . $club->getId()));
        $this->client->submitForm('Envoyer ma demande', ['join_request[message]' => '']);

        $this->assertSame(1, $this->entityManager()->getRepository(JoinRequest::class)->count(['user' => $requester, 'club' => $club]));
    }

    public function testMemberIsRedirectedAway(): void
    {
        $member = $this->createUser('membre@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->addMembership((new Membership())->setUser($member)->setClub($club));
        $this->entityManager()->flush();
        $this->client->loginUser($member);

        $this->client->request('GET', $this->www('/creer-mon-club/rejoindre/' . $club->getId()));

        $this->assertResponseRedirects($this->www('/clubs'));
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Functional/JoinRequestTest.php
```

Expected : 404.

- [ ] **Step 3 : service et formulaire**

`src/Service/Onboarding/JoinRequestService.php` :

```php
<?php

namespace App\Service\Onboarding;

use App\Entity\Club;
use App\Entity\JoinRequest;
use App\Entity\Membership;
use App\Entity\User;
use App\Repository\JoinRequestRepository;
use App\Repository\UserRepository;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class JoinRequestService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JoinRequestRepository $joinRequestRepository,
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly SubdomainService $subdomainService,
    ) {
    }

    /**
     * Enregistre la demande (une seule ouverte par utilisateur et club) et prévient
     * les gestionnaires ; sans gestionnaire, les admins reçoivent le message.
     */
    public function submit(User $user, Club $club, ?string $message): JoinRequest
    {
        $existing = $this->joinRequestRepository->findOpenByUserAndClub($user, $club);
        if ($existing !== null) {
            return $existing;
        }

        $joinRequest = (new JoinRequest())->setUser($user)->setClub($club)->setMessage($message !== null && trim($message) !== '' ? trim($message) : null);
        $this->entityManager->persist($joinRequest);
        $this->entityManager->flush();

        $managers = $club->getMemberships()
            ->filter(static fn (Membership $membership): bool => $membership->isManager())
            ->map(static fn (Membership $membership): User => $membership->getUser())
            ->toArray();
        $recipients = $managers !== [] ? $managers : $this->userRepository->findAdmins();

        $inviteUrl = $this->subdomainService->generateClubUrl((string) $club->getSubdomain(), '/invitations/create', [
            'email' => $user->getEmail(),
            'firstname' => $user->getFirstname(),
            'lastname' => $user->getLastname(),
        ]);

        foreach ($recipients as $recipient) {
            $this->mailer->send((new TemplatedEmail())
                ->from(new Address('contact@tarmac.center', 'Tarmac'))
                ->to((string) $recipient->getEmail())
                ->subject(sprintf('%s souhaite rejoindre %s', $user->getFullName(), $club->getName()))
                ->htmlTemplate('email/joinRequest.html.twig')
                ->context(['joinRequest' => $joinRequest, 'inviteUrl' => $inviteUrl]));
        }

        return $joinRequest;
    }
}
```

`src/Form/Onboarding/JoinRequestType.php` :

```php
<?php

namespace App\Form\Onboarding;

use App\Entity\JoinRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

class JoinRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('message', TextareaType::class, [
            'label' => 'joinRequestMessage',
            'help' => 'joinRequestMessageHelp',
            'required' => false,
            'attr' => ['rows' => 4, 'maxlength' => 500],
            'constraints' => [new Length(max: 500)],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => JoinRequest::class]);
    }
}
```

- [ ] **Step 4 : action du tunnel**

`use App\Entity\JoinRequest; use App\Form\Onboarding\JoinRequestType;` puis dans `OnboardingController` :

```php
    #[Route('/rejoindre/{id}', name: 'public_onboarding_join_request', methods: ['GET', 'POST'])]
    public function joinRequest(Request $request, Club $club): Response
    {
        $user = $this->verifiedUserOrRedirect();
        if ($user instanceof Response) {
            return $user;
        }
        if ($club->hasUser($user)) {
            $this->addFlash('info', 'alreadyMemberOfClub');

            return $this->redirectToRoute('public_clubs');
        }

        $form = $this->createForm(JoinRequestType::class, new JoinRequest());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->joinRequestService->submit($user, $club, $form->getData()->getMessage());

            return $this->render('public/onboarding/joinRequestSent.html.twig', ['club' => $club]);
        }

        return $this->render('public/onboarding/joinRequest.html.twig', ['club' => $club, 'form' => $form]);
    }
```

- [ ] **Step 5 : templates et e-mail**

`templates/public/onboarding/joinRequest.html.twig` :

```twig
{% extends 'public/base.html.twig' %}

{% block title %}{{ 'joinRequestTitle'|trans({club: club.name}) }}{% endblock %}

{% block body %}
	<div class="page page-center">
		<div class="container container-tight py-4">
			<div class="text-center mb-4">
				<a href="{{ path('public_landing') }}" class="navbar-brand navbar-brand-autodark">
					<img src="{{ asset('logo.svg') }}" height="48" alt="Tarmac">
				</a>
			</div>
			<div class="tarmac-panel">
				<h2 style="font-size:1.4rem;font-weight:800;text-align:center;margin:0 0 0.5rem;">{{ 'joinRequestTitle'|trans({club: club.name}) }}</h2>
				<p class="text-center text-secondary mb-4">{{ 'joinRequestLead'|trans }}</p>
				{{ form_start(form) }}
				{{ form_row(form.message) }}
				<button type="submit" class="btn btn-primary w-100 mt-3" style="min-height:48px;">
					<i class="ti ti-send me-2"></i>{{ 'sendJoinRequest'|trans }}
				</button>
				{{ form_end(form) }}
			</div>
			<div class="text-center mt-3">
				<a href="{{ path('public_onboarding_club') }}" style="color:var(--td-ink-2);font-size:0.85rem;">{{ 'backToClubCreation'|trans }}</a>
			</div>
		</div>
	</div>
{% endblock %}
```

`templates/public/onboarding/joinRequestSent.html.twig` :

```twig
{% extends 'public/base.html.twig' %}

{% block title %}{{ 'joinRequestSentTitle'|trans }}{% endblock %}

{% block body %}
	<div class="page page-center">
		<div class="container container-tight py-4">
			<div class="text-center mb-4">
				<a href="{{ path('public_landing') }}" class="navbar-brand navbar-brand-autodark">
					<img src="{{ asset('logo.svg') }}" height="48" alt="Tarmac">
				</a>
			</div>
			<div class="tarmac-panel text-center">
				<div style="font-size:2.5rem;color:var(--td-success);margin-bottom:0.75rem;"><i class="ti ti-mail-check"></i></div>
				<h2 style="font-size:1.4rem;font-weight:800;margin:0 0 0.75rem;">{{ 'joinRequestSentTitle'|trans }}</h2>
				<p class="text-secondary">{{ 'joinRequestSentBody'|trans({club: club.name}) }}</p>
				<a href="{{ path('public_clubs') }}" class="btn btn-outline-primary w-100 mt-3" style="min-height:48px;">{{ 'myClubs'|trans }}</a>
			</div>
		</div>
	</div>
{% endblock %}
```

`templates/email/joinRequest.html.twig` :

```twig
{% extends 'email/base.html.twig' %}

{% block title %}Demande d'adhésion - Tarmac{% endblock %}
{% block preheader %}{{ joinRequest.user.fullName }} souhaite rejoindre {{ joinRequest.club.name }}{% endblock %}
{% block email_title %}Demande d'adhésion{% endblock %}

{% block email_body %}
	<h2 style="margin-top: 0; font-size: 20px; color: #111827; font-weight: 600;">{{ joinRequest.user.fullName }} souhaite rejoindre {{ joinRequest.club.name }}</h2>

	<p style="margin: 16px 0; color: #4b5563;">Cette personne a trouvé votre club en voulant en créer un sur Tarmac. Son adresse : <a href="mailto:{{ joinRequest.user.email }}" style="color: #345B7C;">{{ joinRequest.user.email }}</a>.</p>

	{% if joinRequest.message %}
		<table cellpadding="0" cellspacing="0" style="width: 100%; margin: 24px 0;">
			<tr>
				<td style="background-color: #e3f2fd; border-left: 4px solid #345B7C; border-radius: 6px; padding: 16px;">
					<p style="margin: 0; color: #1565c0; white-space: pre-line;">{{ joinRequest.message }}</p>
				</td>
			</tr>
		</table>
	{% endif %}

	<p style="margin: 16px 0; color: #4b5563;">Si vous la reconnaissez, invitez-la : le formulaire est prérempli.</p>

	<table cellpadding="0" cellspacing="0" style="width: 100%; margin: 30px 0;">
		<tr>
			<td align="center">
				<a href="{{ inviteUrl }}" style="display: inline-block; padding: 14px 32px; background-color: #345B7C; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px;">Inviter {{ joinRequest.user.firstname }}</a>
			</td>
		</tr>
	</table>

	<p style="font-size: 14px; color: #667382; margin: 16px 0;">Si vous ne connaissez pas cette personne, ignorez cet e-mail : rien ne se passera sans invitation de votre part.</p>
{% endblock %}
```

- [ ] **Step 6 : côté club**

Dans `src/Controller/Club/InvitationController.php` :

1. Injecter `private readonly JoinRequestRepository $joinRequestRepository` dans le constructeur (`use App\Repository\JoinRequestRepository; use App\Entity\JoinRequest;`).
2. Dans `index()`, passer `'joinRequests' => $this->joinRequestRepository->findOpenByClub($club)` au template.
3. Dans `create()`, après `$invitation = new Invitation();` :

```php
        if ($request->isMethod('GET') && $request->query->has('email')) {
            $invitation->setEmail((string) $request->query->get('email'));
            $invitation->setFirstname($request->query->get('firstname'));
            $invitation->setLastname($request->query->get('lastname'));
        }
```

4. Nouvelle action :

```php
    #[Route('/demandes/{id}/ignorer', name: 'club_join_request_ignore', methods: ['POST'])]
    public function ignoreJoinRequest(Request $request, JoinRequest $joinRequest): Response
    {
        $club = $this->clubResolver->resolve();
        if ($joinRequest->getClub() !== $club) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('ignore-join-request-' . $joinRequest->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $joinRequest->markHandled($this->getUser());
        $this->entityManager->flush();
        $this->addFlash('success', 'joinRequestIgnored');

        return $this->redirectToRoute('club_invitations');
    }
```

Dans `templates/club/members/invitations/index.html.twig`, au début de `page_content`, avant `<div class="row">` :

```twig
	{% if joinRequests is not empty %}
		<div class="tarmac-panel mb-4">
			<h3 style="font-size:1.05rem;font-weight:700;margin:0 0 0.75rem;"><i class="ti ti-door-enter me-2"></i>{{ 'joinRequests'|trans }}</h3>
			<p class="text-secondary" style="font-size:0.9rem;">{{ 'joinRequestsHint'|trans }}</p>
			<div class="list-group">
				{% for joinRequest in joinRequests %}
					<div class="list-group-item">
						<div class="fw-bold">{{ joinRequest.user.fullName }}</div>
						<div class="text-secondary" style="font-size:0.9rem;"><i class="ti ti-at"></i> {{ joinRequest.user.email }} · {{ joinRequest.createdAt|date('d/m/Y') }}</div>
						{% if joinRequest.message %}<p class="mb-2 mt-1" style="white-space:pre-line;">{{ joinRequest.message }}</p>{% endif %}
						<div class="d-flex gap-2 mt-2">
							<a href="{{ path('club_invitation_create', {email: joinRequest.user.email, firstname: joinRequest.user.firstname, lastname: joinRequest.user.lastname}) }}" class="btn btn-primary" style="min-height:44px;">
								<i class="ti ti-plus me-2"></i>{{ 'invite'|trans }}
							</a>
							<form method="post" action="{{ path('club_join_request_ignore', {id: joinRequest.id}) }}">
								<input type="hidden" name="_token" value="{{ csrf_token('ignore-join-request-' ~ joinRequest.id) }}">
								<button type="submit" class="btn btn-outline-secondary" style="min-height:44px;">{{ 'ignore'|trans }}</button>
							</form>
						</div>
					</div>
				{% endfor %}
			</div>
		</div>
	{% endif %}
```

Dans `src/Service/InvitationService.php`, injecter `JoinRequestRepository $joinRequestRepository` dans le constructeur et, dans `acceptInvitation()`, juste avant le `$this->entityManager->flush();` final :

```php
        // Une demande d'adhésion ouverte pour ce club est satisfaite par cette invitation.
        $joinRequest = $this->joinRequestRepository->findOpenByUserAndClub($user, $invitation->getClub());
        $joinRequest?->markHandled($user);
```

- [ ] **Step 7 : traductions**

Sous `<!-- Onboarding : demandes d'adhésion -->` :

| resname | target |
|---|---|
| `joinRequestTitle` | Rejoindre {club} |
| `joinRequestLead` | Les gestionnaires du club recevront votre nom, votre e-mail et ce message. Ils vous enverront une invitation s'ils vous reconnaissent. |
| `joinRequestMessage` | Un mot pour les gestionnaires |
| `joinRequestMessageHelp` | Facultatif : qui vous êtes, depuis quand vous volez au club… |
| `sendJoinRequest` | Envoyer ma demande |
| `backToClubCreation` | Revenir à la création de mon club |
| `joinRequestSentTitle` | Demande envoyée |
| `joinRequestSentBody` | Les gestionnaires de {club} ont été prévenus. Sans réponse sous quelques jours, écrivez à contact@tarmac.center. |
| `alreadyMemberOfClub` | Vous êtes déjà membre de ce club |
| `joinRequests` | Demandes d'adhésion |
| `joinRequestsHint` | Ces personnes ont trouvé votre club en voulant en créer un. Invitez-les si vous les reconnaissez. |
| `ignore` | Ignorer |
| `joinRequestIgnored` | Demande ignorée |

- [ ] **Step 8 : lancer**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/JoinRequestTest.php && vendor/bin/phpunit && symfony console lint:twig templates && symfony console lint:xliff translations/
```

Expected : suite verte.

- [ ] **Step 9 : commit**

```bash
git add src templates translations tests/Functional/JoinRequestTest.php
git commit -m "feat(onboarding): demande d'adhésion à un club existant

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 12 : page d'accueil et points d'entrée

**Files:**
- Modify: `templates/public/landing.html.twig`, `templates/layout/base.html.twig`, `templates/public/clubs.html.twig`, `templates/club/base.html.twig`, `translations/messages+intl-icu.fr.xlf`
- Test: `tests/Functional/LandingTest.php`

**Interfaces:**
- Consumes: route `public_onboarding_account` (Task 9).

- [ ] **Step 1 : test**

`tests/Functional/LandingTest.php` :

```php
<?php

namespace App\Tests\Functional;

class LandingTest extends FunctionalTestCase
{
    public function testAnonymousVisitorSeesCreateClubCallToAction(): void
    {
        $crawler = $this->client->request('GET', $this->www('/'));

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(2, $crawler->filter('a[href$="/creer-mon-club"]')->count());
        $this->assertSelectorTextContains('body', 'Gratuit pour les associations');
        $this->assertSelectorTextNotContains('body', 'Tarmac fonctionne sur invitation');
    }

    public function testLoggedInUserSeesMyClubsFirst(): void
    {
        $this->client->loginUser($this->createUser('membre@example.test'));
        $crawler = $this->client->request('GET', $this->www('/'));

        $this->assertSame(1, $crawler->filter('.landing-hero a.landing-btn-primary')->count());
        $this->assertStringContainsString('/clubs', $crawler->filter('.landing-hero a.landing-btn-primary')->attr('href'));
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Functional/LandingTest.php
```

Expected : aucun lien vers `/creer-mon-club`.

- [ ] **Step 3 : héros**

Dans `templates/public/landing.html.twig`, remplacer le bloc `landing-cta-row` du héros (de `<div class="landing-cta-row">` à son `</div>` fermant, avant le commentaire des mockups) par :

```twig
						<div class="landing-cta-row">
							{% if app.user %}
								<a href="{{ path('public_clubs') }}" class="landing-btn landing-btn-primary">
									<i class="ti ti-building-community"></i>
									{{ 'myClubs'|trans }}
								</a>
								<a href="{{ path('public_onboarding_account') }}" class="landing-btn landing-btn-ghost">
									<i class="ti ti-windsock"></i>
									{{ 'createClub'|trans }}
								</a>
							{% else %}
								<a href="{{ path('public_onboarding_account') }}" class="landing-btn landing-btn-primary">
									<i class="ti ti-windsock"></i>
									{{ 'createMyClub'|trans }}
								</a>
								<a href="{{ path('public_login') }}" class="landing-btn landing-btn-ghost">
									<i class="ti ti-login"></i>
									{{ 'signIn'|trans }}
								</a>
							{% endif %}
						</div>
						<p class="landing-free-note">{{ 'landingFreeNote'|trans }}</p>
```

Ajouter dans le `<style>` de la page, après `.landing-cta-row` :

```css
		.landing-free-note { margin: 1rem 0 0; font-size: 0.9rem; color: rgba(255, 255, 255, 0.7); }
		.landing-values { padding: 2.5rem 1rem 0; }
		.landing-value { display: flex; gap: 0.9rem; align-items: flex-start; }
		.landing-value i { font-size: 1.5rem; color: var(--landing-glow); flex: none; }
		.landing-value h3 { font-size: 1rem; font-weight: 700; margin: 0 0 0.25rem; }
		.landing-value p { margin: 0; color: #5b6b7b; font-size: 0.92rem; }
		.landing-final-grid { display: grid; gap: 1.5rem; grid-template-columns: 1fr; text-align: left; }
		@media (min-width: 768px) { .landing-final-grid { grid-template-columns: 1fr 1fr; } }
		.landing-final-card { background: #fff; border: 1px solid #e6ebf1; border-radius: 14px; padding: 1.5rem; }
		.landing-final-card h3 { font-size: 1.1rem; font-weight: 800; margin: 0 0 0.5rem; }
		.landing-final-card p { color: #5b6b7b; margin: 0 0 1rem; }
```

- [ ] **Step 4 : bloc valeurs**

Entre la section héros et la section « FEATURES », insérer :

```twig
		{# ========== VALEURS ========== #}
		<section class="landing-values">
			<div class="container-xl">
				<div class="row g-4">
					<div class="col-12 col-md-4">
						<div class="landing-value">
							<i class="ti ti-code"></i>
							<div><h3>{{ 'landingValueOpenSource'|trans }}</h3><p>{{ 'landingValueOpenSourceBody'|trans }}</p></div>
						</div>
					</div>
					<div class="col-12 col-md-4">
						<div class="landing-value">
							<i class="ti ti-heart-handshake"></i>
							<div><h3>{{ 'landingValueVolunteer'|trans }}</h3><p>{{ 'landingValueVolunteerBody'|trans }}</p></div>
						</div>
					</div>
					<div class="col-12 col-md-4">
						<div class="landing-value">
							<i class="ti ti-eye-off"></i>
							<div><h3>{{ 'landingValueNoAds'|trans }}</h3><p>{{ 'landingValueNoAdsBody'|trans }}</p></div>
						</div>
					</div>
				</div>
			</div>
		</section>
```

- [ ] **Step 5 : contact et CTA final**

Remplacer la section « CONTACT » et la section « FINAL CTA » (de `{# ========== CONTACT ========== #}` à la fin de `</section>` du CTA final, en gardant `<p class="landing-footnote">`) par :

```twig
		{# ========== CTA FINAL : deux publics ========== #}
		<section class="landing-section">
			<div class="container-xl">
				<div class="landing-final">
					<h2>{{ 'landingFinalCtaTitle'|trans|raw }}</h2>
					<div class="landing-final-grid mt-4">
						<div class="landing-final-card">
							<h3>{{ 'landingFinalMemberTitle'|trans }}</h3>
							<p>{{ 'landingFinalMemberBody'|trans }}</p>
							<a href="{{ path('public_guide') }}" class="landing-btn landing-btn-ghost">
								<i class="ti ti-book-2"></i>{{ 'readUserGuide'|trans }}
							</a>
						</div>
						<div class="landing-final-card">
							<h3>{{ 'landingFinalPresidentTitle'|trans }}</h3>
							<p>{{ 'landingFinalPresidentBody'|trans }}</p>
							<a href="{{ path('public_onboarding_account') }}" class="landing-btn landing-btn-primary">
								<i class="ti ti-windsock"></i>{{ 'createMyClub'|trans }}
							</a>
						</div>
					</div>
				</div>
			</div>
		</section>

		{# ========== CONTACT ========== #}
		<section class="landing-section" style="padding-top: 0;">
			<div class="container-xl">
				<div class="landing-contact">
					<span class="landing-contact-eyebrow">{{ 'landingContactEyebrow'|trans|raw }}</span>
					<h2>{{ 'landingContactTitle'|trans }}</h2>
					<p>{{ 'landingContactBody'|trans }}</p>
					<a href="mailto:contact@tarmac.center?subject=Question%20sur%20Tarmac" class="landing-contact-btn">
						<i class="ti ti-mail"></i>
						{{ 'landingContactCta'|trans }}
					</a>
				</div>
				<p class="landing-footnote">{{ 'landingMadeWith'|trans }}</p>
			</div>
		</section>
```

- [ ] **Step 6 : points d'entrée**

`templates/layout/base.html.twig`, bloc `anonymous_actions` : remplacer le bouton `public_login` par :

```twig
								<a href="{{ path('public_login') }}" class="nav-link px-2 me-1" style="min-height: 44px;">
									<span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-login"></i></span>
									<span class="nav-link-title d-none d-sm-inline">{{ 'signIn'|trans }}</span>
								</a>
								<a href="{{ path('public_onboarding_account') }}" class="btn btn-primary" style="min-height: 44px;">
									<i class="ti ti-windsock me-2"></i>
									{{ 'createMyClub'|trans }}
								</a>
```

`templates/public/clubs.html.twig` : dans le bloc `body`, juste après le titre de la page (repérer le premier `<h2` ou `page-title`), ajouter :

```twig
			<a href="{{ path('public_onboarding_account') }}" class="btn btn-outline-primary" style="min-height:44px;">
				<i class="ti ti-windsock me-2"></i>{{ 'createClub'|trans }}
			</a>
```

`templates/club/base.html.twig`, bloc `user_dropdown_menu`, après l'entrée `userGuide` :

```twig
	<a href="{{ url('public_onboarding_account') }}" class="dropdown-item">
		<i class="ti ti-windsock"></i>
		{{ 'createClub'|trans }}
	</a>
```

- [ ] **Step 7 : traductions**

Modifier les cibles existantes :

| resname | nouvelle target |
|---|---|
| `landingStep1Title` | Créez votre club |
| `landingStep1Body` | Votre compte, votre e-mail, le nom du club et son terrain. Cinq minutes. |
| `landingStep2Title` | Déclarez votre matériel |
| `landingStep2Body` | Planeurs, avions, treuils, remorques : tout ce qui demande de l'entretien. |
| `landingStep3Title` | Invitez vos membres |
| `landingStep3Body` | Un fichier Excel ou une saisie à la main. Chacun reçoit un lien et choisit son mot de passe. |
| `landingStep4Title` | Appliquez vos plans d'entretien |
| `landingStep4Body` | Tarmac génère les tâches, chacun contribue, les qualifiés signent. |
| `landingContactEyebrow` | Une question ? |
| `landingContactTitle` | Besoin d'un coup de main ? |
| `landingContactBody` | Écrivez-moi, je réponds et je vous aide à démarrer. C'est gratuit et sans engagement. |
| `landingFinalCtaTitle` | Prêt à décoller ? |

Ajouter sous `<!-- Landing : inscription autonome -->` :

| resname | target |
|---|---|
| `createClub` | Créer un club |
| `landingFreeNote` | Gratuit pour les associations. Aucune carte bancaire, aucun engagement. |
| `landingFinalMemberTitle` | Vous êtes membre d'un club ? |
| `landingFinalMemberBody` | Demandez une invitation à votre club. En attendant, le guide explique tout ce que vous pourrez y faire. |
| `landingFinalPresidentTitle` | Vous présidez ou gérez un club ? |
| `landingFinalPresidentBody` | Créez votre club maintenant. Vous êtes guidé pas à pas, et vos membres vous rejoignent sur invitation. |

`createMyClub` existe depuis la tâche 10. Les clés `landingFinalCtaBody`, `landingCtaTitle`, `landingCtaBody` et `landingContactCta` peuvent rester : ne pas les supprimer sur la seule foi de `debug:translation`.

- [ ] **Step 8 : lancer**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/LandingTest.php tests/Functional/SmokeTest.php && symfony console lint:twig templates && symfony console lint:xliff translations/
```

Expected : tests OK. Contrôler la page à 320 px et à 1200 px : bloc valeurs sous le héros, deux cartes finales empilées puis côte à côte.

- [ ] **Step 9 : commit**

```bash
git add templates translations tests/Functional/LandingTest.php
git commit -m "feat(landing): appel à créer son club et points d'entrée vers l'inscription

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 13 : administration (aérodrome, fiche, tableau de bord, filtre)

**Files:**
- Modify: `src/Form/ClubType.php`, `src/Form/Filter/ClubFilterType.php`, `src/Repository/ClubRepository.php`, `src/Controller/Admin/ClubController.php`, `src/Controller/Admin/DashboardController.php`, `templates/admin/club/new.html.twig`, `edit.html.twig`, `show.html.twig`, `index.html.twig`, `templates/admin/dashboard.html.twig`
- Test: `tests/Functional/AdminClubTest.php`

**Interfaces:**
- Consumes: `AerodromeSelectorType`, partial `component/aerodromeSelector.html.twig`, route `public_onboarding_aerodromes` (Task 8), `JoinRequestRepository::findOpenByClub` (Task 6).
- Produces: `ClubRepository::findCreatedSince(\DateTimeImmutable): Club[]`, `findByAerodrome(Aerodrome, ?Club $except = null): Club[]`, filtre `aerodrome` (`with` / `without`) dans `queryByFilters`.

- [ ] **Step 1 : test**

`tests/Functional/AdminClubTest.php` :

```php
<?php

namespace App\Tests\Functional;

class AdminClubTest extends FunctionalTestCase
{
    public function testDashboardListsRecentClubsAndShowPageDisplaysCreator(): void
    {
        $admin = $this->createUser('admin@example.test', admin: true);
        $president = $this->createUser('president@example.test');
        $club = $this->createClub('Planeurs de Beauce', 'beauce');
        $club->setCreatedBy($president);
        $this->entityManager()->flush();
        $this->client->loginUser($admin);

        $this->client->request('GET', $this->www('/admin'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Planeurs de Beauce');

        $this->client->request('GET', $this->www('/admin/clubs/' . $club->getId()));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'president@example.test');
        $this->assertSelectorTextContains('body', 'Sans aérodrome');
    }

    public function testIndexFiltersClubsWithoutAerodrome(): void
    {
        $this->client->loginUser($this->createUser('admin@example.test', admin: true));
        $this->createClub('Sans terrain', 'sans');

        $this->client->request('GET', $this->www('/admin/clubs?aerodrome=without'));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Sans terrain');
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Functional/AdminClubTest.php
```

Expected : le tableau de bord admin ne liste aucun club.

- [ ] **Step 3 : dépôt**

Dans `src/Repository/ClubRepository.php` (`use App\Entity\Aerodrome;`) :

```php
    /**
     * @return Club[]
     */
    public function findCreatedSince(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('club')
            ->andWhere('club.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('club.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Club[]
     */
    public function findByAerodrome(Aerodrome $aerodrome, ?Club $except = null): array
    {
        $queryBuilder = $this->createQueryBuilder('club')
            ->andWhere('club.aerodrome = :aerodrome')
            ->andWhere('club.active = true')
            ->setParameter('aerodrome', $aerodrome)
            ->orderBy('club.name', 'ASC');

        if ($except !== null && $except->getId() !== null) {
            $queryBuilder->andWhere('club.id != :except')->setParameter('except', $except->getId());
        }

        return $queryBuilder->getQuery()->getResult();
    }
```

Dans `queryByFilters`, avant le `return` :

```php
        if (($filters['aerodrome'] ?? '') === 'without') {
            $qb->andWhere('club.aerodrome IS NULL');
        } elseif (($filters['aerodrome'] ?? '') === 'with') {
            $qb->andWhere('club.aerodrome IS NOT NULL');
        }
```

- [ ] **Step 4 : formulaires**

`src/Form/ClubType.php` : ajouter `use App\Form\Type\AerodromeSelectorType;` et, après le champ `subdomain` :

```php
            ->add('aerodrome', AerodromeSelectorType::class)
            ->add('locationLabel', TextType::class, [
                'label' => 'municipality',
                'required' => false,
            ])
```

`src/Form/Filter/ClubFilterType.php`, après `active` :

```php
            ->add('aerodrome', ChoiceType::class, [
                'label' => 'aerodrome',
                'choices' => ['all' => '', 'withAerodrome' => 'with', 'withoutAerodrome' => 'without'],
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'label_attr' => ['class' => 'form-label'],
            ])
```

Dans `templates/admin/club/new.html.twig` et `edit.html.twig`, après `{{ form_row(form.subdomain) }}` :

```twig
			{% include 'component/aerodromeSelector.html.twig' with {
				aerodromeField: form.aerodrome,
				locationField: form.locationLabel,
				searchUrl: path('public_onboarding_aerodromes'),
			} %}
```

- [ ] **Step 5 : fiche, liste et tableau de bord**

`src/Controller/Admin/ClubController.php` : injecter `JoinRequestRepository $joinRequestRepository` et, dans `show()`, passer :

```php
            'joinRequests' => $this->joinRequestRepository->findOpenByClub($club),
            'neighbours' => $club->getAerodrome() !== null ? $this->clubRepository->findByAerodrome($club->getAerodrome(), $club) : [],
```

`templates/admin/club/show.html.twig`, dans le `<dl>` après « Créé le » :

```twig
				<dt class="col-5 col-sm-4">Créé par</dt>
				<dd class="col-7 col-sm-8">
					{% if club.createdBy %}{{ club.createdBy.fullName }} <span class="text-secondary">({{ club.createdBy.email }})</span>{% else %}<span class="text-secondary">Administration</span>{% endif %}
				</dd>

				<dt class="col-5 col-sm-4">Terrain</dt>
				<dd class="col-7 col-sm-8">
					{% if club.locationText %}{{ club.locationText }}{% else %}<span class="badge bg-warning-lt">Sans aérodrome</span>{% endif %}
				</dd>
```

Après la carte « Informations » :

```twig
	{% if neighbours is not empty %}
		<div class="card mt-3">
			<div class="card-header"><h3 class="card-title">Sur le même terrain</h3></div>
			<div class="list-group list-group-flush">
				{% for other in neighbours %}
					<a href="{{ path('admin_club_show', {id: other.id}) }}" class="list-group-item list-group-item-action">{{ other.name }} <span class="text-secondary">· {{ other.subdomain }}.{{ domain }}</span></a>
				{% endfor %}
			</div>
		</div>
	{% endif %}

	{% if joinRequests is not empty %}
		<div class="card mt-3">
			<div class="card-header"><h3 class="card-title">Demandes d'adhésion ouvertes</h3></div>
			<div class="list-group list-group-flush">
				{% for joinRequest in joinRequests %}
					<div class="list-group-item">
						<strong>{{ joinRequest.user.fullName }}</strong> <span class="text-secondary">({{ joinRequest.user.email }}) · {{ joinRequest.createdAt|date('d/m/Y') }}</span>
						{% if joinRequest.message %}<p class="mb-0 mt-1" style="white-space:pre-line;">{{ joinRequest.message }}</p>{% endif %}
					</div>
				{% endfor %}
			</div>
		</div>
	{% endif %}
```

`templates/admin/club/index.html.twig`, dans la zone des badges desktop (à côté du badge « Membres ») et dans celle des badges mobile :

```twig
									{% if not club.aerodrome %}
										<span class="badge bg-warning text-white"><i class="ti ti-map-pin-off me-1"></i>Sans aérodrome</span>
									{% endif %}
```

`src/Controller/Admin/DashboardController.php` :

```php
<?php

namespace App\Controller\Admin;

use App\Repository\ClubRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', host: 'www.%domain%')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(private readonly ClubRepository $clubRepository)
    {
    }

    #[Route('', name: 'admin_dashboard')]
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'recentClubs' => $this->clubRepository->findCreatedSince(new \DateTimeImmutable('-30 days')),
            'domain' => $this->getParameter('domain'),
        ]);
    }
}
```

`templates/admin/dashboard.html.twig`, après la carte de bienvenue :

```twig
	<div class="row mt-3">
		<div class="col-12">
			<div class="card">
				<div class="card-header"><h3 class="card-title">Clubs créés ces 30 derniers jours</h3></div>
				{% if recentClubs is empty %}
					<div class="card-body text-secondary"><em>Aucune création récente</em></div>
				{% else %}
					<div class="list-group list-group-flush">
						{% for club in recentClubs %}
							<a href="{{ path('admin_club_show', {id: club.id}) }}" class="list-group-item list-group-item-action">
								<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
									<div>
										<strong>{{ club.name }}</strong> <span class="text-secondary">· {{ club.subdomain }}.{{ domain }}</span><br>
										<span class="text-secondary small">{{ club.createdAt|date('d/m/Y') }}{% if club.createdBy %} · {{ club.createdBy.fullName }}{% endif %}{% if club.locationText %} · {{ club.locationText }}{% endif %}</span>
									</div>
									<div class="d-flex gap-2">
										<span class="badge bg-blue-lt"><i class="ti ti-plane-tilt me-1"></i>{{ club.equipments|length }}</span>
										<span class="badge bg-green-lt"><i class="ti ti-users me-1"></i>{{ club.memberships|length }}</span>
										<span class="badge bg-orange-lt"><i class="ti ti-mail me-1"></i>{{ club.invitations|length }}</span>
									</div>
								</div>
							</a>
						{% endfor %}
					</div>
				{% endif %}
			</div>
		</div>
	</div>
```

Les collections `equipments`, `memberships`, `invitations` sont `EXTRA_LAZY` : `|length` déclenche un `COUNT`, pas un chargement.

- [ ] **Step 6 : traductions**

Sous `<!-- Admin : aérodrome des clubs -->` : `withAerodrome` → « Avec aérodrome », `withoutAerodrome` → « Sans aérodrome ». Vérifier que `all` existe déjà (utilisé par le filtre `active`).

- [ ] **Step 7 : lancer**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/AdminClubTest.php && symfony console lint:twig templates/admin && symfony console lint:xliff translations/
```

Expected : 2 tests OK. Dans le navigateur, modifier un club existant depuis l'admin et lui affecter un aérodrome ; la fiche affiche le terrain.

- [ ] **Step 8 : commit**

```bash
git add src templates translations tests/Functional/AdminClubTest.php
git commit -m "feat(admin): aérodrome des clubs, créateur, clubs récents et demandes d'adhésion

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 14 : purge des comptes non vérifiés

**Files:**
- Create: `src/Command/PurgeUnverifiedUsersCommand.php`
- Modify: `src/Repository/UserRepository.php`
- Test: `tests/Command/PurgeUnverifiedUsersCommandTest.php`

**Interfaces:**
- Produces: `UserRepository::findUnverifiedOlderThan(\DateTimeImmutable): User[]` (sans adhésion), commande `app:users:purge-unverified [--days=7] [--dry-run]`.

- [ ] **Step 1 : test**

`tests/Command/PurgeUnverifiedUsersCommandTest.php` :

```php
<?php

namespace App\Tests\Command;

use App\Entity\Membership;
use App\Entity\User;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class PurgeUnverifiedUsersCommandTest extends FunctionalTestCase
{
    public function testPurgesOldUnverifiedAccountsWithoutMembership(): void
    {
        $old = $this->createUser('vieux@example.test', verified: false);
        $old->setCreatedAt(new \DateTimeImmutable('-10 days'));
        $recent = $this->createUser('recent@example.test', verified: false);
        $verified = $this->createUser('verifie@example.test');
        $verified->setCreatedAt(new \DateTimeImmutable('-10 days'));
        $member = $this->createUser('membre@example.test', verified: false);
        $member->setCreatedAt(new \DateTimeImmutable('-10 days'));
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($member)->setClub($club));
        $this->entityManager()->flush();

        $tester = new CommandTester((new Application(static::$kernel))->find('app:users:purge-unverified'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $repository = $this->entityManager()->getRepository(User::class);
        $this->assertNull($repository->findOneBy(['email' => 'vieux@example.test']));
        $this->assertNotNull($repository->findOneBy(['email' => 'recent@example.test']));
        $this->assertNotNull($repository->findOneBy(['email' => 'verifie@example.test']));
        $this->assertNotNull($repository->findOneBy(['email' => 'membre@example.test']));
        $this->assertStringContainsString('1 compte', $tester->getDisplay());
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Command/PurgeUnverifiedUsersCommandTest.php
```

Expected : commande introuvable.

- [ ] **Step 3 : dépôt et commande**

Dans `src/Repository/UserRepository.php` :

```php
    /**
     * Comptes jamais vérifiés, sans aucune adhésion, créés avant la date donnée.
     *
     * @return User[]
     */
    public function findUnverifiedOlderThan(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('user')
            ->leftJoin('user.memberships', 'membership')
            ->andWhere('user.isVerified = false')
            ->andWhere('user.createdAt < :before')
            ->andWhere('membership.id IS NULL')
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }
```

`src/Command/PurgeUnverifiedUsersCommand.php` :

```php
<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:users:purge-unverified', description: 'Supprime les comptes jamais vérifiés, sans club, plus vieux que N jours')]
final class PurgeUnverifiedUsersCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Ancienneté minimale en jours', '7')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste sans supprimer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $before = new \DateTimeImmutable(sprintf('-%d days', (int) $input->getOption('days')));
        $users = $this->userRepository->findUnverifiedOlderThan($before);

        foreach ($users as $user) {
            $io->text(sprintf('%s (créé le %s)', $user->getEmail(), $user->getCreatedAt()?->format('d/m/Y')));
            if (!$input->getOption('dry-run')) {
                $this->entityManager->remove($user);
            }
        }

        if (!$input->getOption('dry-run')) {
            $this->entityManager->flush();
        }

        $io->success(sprintf('%d compte%s %s.', count($users), count($users) > 1 ? 's' : '', $input->getOption('dry-run') ? 'à supprimer' : 'supprimé(s)'));

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4 : lancer**

```bash
vendor/bin/phpunit tests/Command/PurgeUnverifiedUsersCommandTest.php && symfony console app:users:purge-unverified --dry-run
```

Expected : test OK, la commande liste 0 compte en local.

- [ ] **Step 5 : planification**

Ajouter dans `README.md` (section déploiement, ou la créer) : « Heroku Scheduler, une fois par jour : `php bin/console app:users:purge-unverified` ». L'add-on s'active depuis le dashboard Heroku ; ce n'est pas automatisable depuis le dépôt.

- [ ] **Step 6 : commit**

```bash
git add src/Command/PurgeUnverifiedUsersCommand.php src/Repository/UserRepository.php tests/Command README.md
git commit -m "feat(users): purge des comptes non vérifiés sans club

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 15 : guide utilisateur et vérification finale

**Files:**
- Modify: `templates/public/guide/index.html.twig`

- [ ] **Step 1 : sommaire**

Dans le sommaire, après `1. À quoi sert Tarmac ?`, insérer `<a href="#creer-club" class="list-group-item list-group-item-action">2. Créer votre club</a>` et décaler la numérotation des entrées suivantes (3 à 17). Faire de même sur les `<h2>` des sections.

- [ ] **Step 2 : nouvelle section**

Après la section `#intro` et son `<hr>`, insérer :

```twig
							<section id="creer-club">
								<h2>2. Créer votre club</h2>
								<p>Vous présidez ou gérez un club et il n'est pas encore sur Tarmac ? Vous pouvez le créer vous-même, depuis la page d'accueil, en quelques minutes. C'est gratuit et sans engagement.</p>
								<ol>
									<li><strong>Votre compte.</strong> Prénom, nom, e-mail et mot de passe. Si vous avez déjà un compte Tarmac (parce qu'un autre club vous a invité), connectez-vous, puis choisissez « Créer un club » dans le menu de votre avatar.</li>
									<li><strong>Votre e-mail.</strong> Nous vous envoyons un lien. Cliquez dessus : vous reprenez là où vous en étiez. Rien reçu ? Regardez dans les courriers indésirables, puis « Renvoyer le lien ».</li>
									<li><strong>Votre club.</strong> Son nom, son adresse Tarmac (proposée d'après le nom, vous pouvez la changer) et votre aérodrome, à choisir dans la liste. Si votre terrain n'y figure pas, indiquez votre commune.</li>
									<li><strong>Ce club existe déjà ?</strong> Si un club au nom proche est déjà sur Tarmac près de chez vous, nous vous le montrons. Si c'est le vôtre, cliquez sur « Demander à rejoindre ce club » : ses gestionnaires reçoivent votre nom et votre e-mail et vous invitent. Si ce n'est pas le vôtre, continuez. Plusieurs clubs peuvent partager un même aérodrome.</li>
									<li><strong>C'est prêt.</strong> Vous êtes gestionnaire de votre club. Notez son adresse, elle est aussi dans l'e-mail de bienvenue. Le tableau de bord vous indique ensuite ce qu'il reste à configurer.</li>
								</ol>
								<p>Vous ne dévoilez rien sur les autres clubs, et rien ne se passe dans un club existant sans invitation de ses gestionnaires.</p>
							</section>

							<hr>
```

- [ ] **Step 3 : « Premiers pas » et « Membres »**

Dans la section « Premiers pas », remplacer le premier `<li>` (« Vous recevez une invitation par e-mail ») par :

```twig
									<li>Vous arrivez sur Tarmac de deux façons&nbsp;: soit vous recevez une <strong>invitation par e-mail</strong> de votre club et choisissez votre mot de passe, soit vous venez de <strong>créer votre club</strong> (voir la section précédente).</li>
```

Dans la section « Membres & invitations », ajouter avant le `</section>` :

```twig
								<h3>Demandes d'adhésion</h3>
								<p>Quand quelqu'un tente de créer un club qui ressemble au vôtre, Tarmac lui propose de vous rejoindre à la place. Vous recevez alors un e-mail avec son nom, son adresse et son message, et la demande apparaît en haut de la page <strong>Invitations</strong>. Si vous reconnaissez la personne, cliquez sur <strong>Inviter</strong>&nbsp;: le formulaire est prérempli. Sinon, <strong>Ignorer</strong>.</p>
```

- [ ] **Step 4 : vérification complète**

```bash
symfony console cache:clear && vendor/bin/phpunit && symfony console lint:twig templates && symfony console lint:xliff translations/ && symfony console debug:translation fr --domain=messages --only-missing && symfony console doctrine:schema:validate
```

Expected : suite verte, aucune clé manquante, schéma synchronisé. Parcours manuel complet sur `https://www.tarmac.wip/creer-mon-club` à 320 px : compte, e-mail (le mail arrive dans la boîte configurée en dev), club, doublon, bienvenue, puis tableau de bord du club.

- [ ] **Step 5 : commit et déploiement**

```bash
git add templates/public/guide/index.html.twig
git commit -m "docs(guide): section « Créer votre club » et demandes d'adhésion

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
git push origin main
```

Après le déploiement, en production : `heroku run php bin/console app:aerodromes:import`, puis activer Heroku Scheduler pour la purge.

---

## Auto-revue du plan

- Couverture de la spec : §3.1 → Task 9 ; §3.2 → Task 9 et 14 ; §3.3 → Task 4, 8, 10 ; §3.4 → Task 7 ; §3.5 → Task 5 et 10 ; §3.6 → Task 6 et 11 ; §3.7 → Task 10 ; §3.8 → Task 12 ; §5 → Task 12 ; §6 → Task 7 et 13 ; §7 → Task 15 ; §8 → Task 3, 4, 6 ; §10 → tests de chaque tâche ; §11 → Task 2, 9, 14 ; §13 → Task 2, 3.
- Hors périmètre de ce plan : §4 (checklist, page aérodrome du club, page d'explication des plans) et `Club::healthCheckDismissedAt`, traités dans `2026-09-02-onboarding-checklist.md`.
- Cohérence des noms : `ClubDuplicateFinder::find(string, ?Aerodrome)` (Task 5, 10) ; `ClubRegistrationService::register(User, Club, DuplicateReport)` (Task 7, 10) ; `EmailVerificationService::send/confirm` (Task 9) ; `JoinRequestService::submit(User, Club, ?string)` (Task 11) ; partial `component/aerodromeSelector.html.twig` avec `aerodromeField`, `locationField`, `searchUrl` (Task 8, 10, 13) ; routes `public_onboarding_*` (Task 8 à 12).
