# Checklist de configuration du club — plan d'implémentation (partie 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sur le tableau de bord, une carte « Votre Tarmac est-il prêt ? » indique aux gestionnaires ce qu'il reste à configurer, avec un bouton par item, et disparaît quand tout est fait.

**Architecture:** Un service `ClubHealthCheck` agrège des contrôles taggués (`app.health_check`), chacun déduit de la base à l'affichage, sans état par étape. Le tableau de bord rend le rapport ; une extension Twig l'expose au menu pour le badge. Deux nouvelles pages club : le réglage de l'aérodrome et l'explication des plans d'entretien.

**Tech Stack:** Symfony 7.4, PHP 8.5, Doctrine ORM 3, Twig + Tabler, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-02-onboarding-club-design.md` (section 4.1 à 4.5, 7, 8).

**Prérequis :** plan `2026-09-02-onboarding-inscription.md` exécuté jusqu'à la tâche 8 incluse (entité `Aerodrome`, champs `Club::aerodrome`, `AerodromeSelectorType`, partial `component/aerodromeSelector.html.twig`, route `api_aerodromes`, socle de tests fonctionnels).

## Global Constraints

Identiques au plan partie 1 : clés de traduction `lowerCamelCase` ajoutées à la main dans le XLF, templates camelCase mobile-first, aliases Doctrine descriptifs, Voters pour tout accès, `ExtendedController`, commits sur `main` en français, guide mis à jour dans le même commit. Breadcrumb obligatoire sur les pages de niveau 3 (`['label' => 'home', 'route' => 'club_dashboard']` en premier).

## Fichiers

Créés : `src/Service/HealthCheck/HealthCheckInterface.php`, `HealthCheckItem.php`, `HealthReport.php`, `ClubHealthCheck.php`, `Check/AerodromeCheck.php`, `Check/EquipmentCheck.php`, `Check/MembersCheck.php`, `Check/SecondManagerCheck.php`, `Check/InspectorCheck.php`, `Check/PlanCheck.php`, `Check/WelcomeMessageCheck.php` ; `src/Twig/HealthCheckExtension.php` ; `src/Controller/Club/SettingsController.php`, `src/Form/ClubAerodromeType.php` ; `templates/club/dashboard/healthCheck.html.twig`, `templates/club/settings/aerodrome.html.twig`, `templates/club/plan/explain.html.twig` ; tests `tests/Service/HealthCheck/*Test.php`, `tests/Functional/HealthCheckTest.php`.

Modifiés : `src/Entity/Club.php`, `src/Repository/EquipmentRepository.php`, `MembershipRepository.php`, `InvitationRepository.php`, `PlanApplicationRepository.php`, `src/Controller/Club/DashboardController.php`, `src/Controller/Club/PlanController.php`, `templates/club/dashboard.html.twig`, `templates/club/base.html.twig`, `templates/public/guide/index.html.twig`, `translations/messages+intl-icu.fr.xlf`.

---

### Task 1 : champ de masquage et compteurs

**Files:**
- Modify: `src/Entity/Club.php`, `src/Repository/EquipmentRepository.php`, `MembershipRepository.php`, `InvitationRepository.php`, `PlanApplicationRepository.php`, migration générée
- Test: `tests/Functional/ClubCountersTest.php`

**Interfaces:**
- Produces: `Club::getHealthCheckDismissedAt/setHealthCheckDismissedAt(?\DateTimeImmutable)` ; `EquipmentRepository::countActiveByClub(Club): int` ; `MembershipRepository::countByClubExcludingUser(Club, ?User): int`, `countManagersByClub(Club): int`, `countInspectorsByClub(Club): int` ; `InvitationRepository::countPendingByClub(Club): int` ; `PlanApplicationRepository::countActiveByClub(Club): int`.

- [ ] **Step 1 : test**

`tests/Functional/ClubCountersTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\Membership;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Service\InvitationService;

class ClubCountersTest extends FunctionalTestCase
{
    public function testMembershipAndInvitationCounters(): void
    {
        $president = $this->createUser('president@example.test');
        $inspector = $this->createUser('qualifie@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($president)->setClub($club)->setIsManager(true));
        $club->addMembership((new Membership())->setUser($inspector)->setClub($club)->setIsInspector(true));
        $this->entityManager()->flush();
        static::getContainer()->get(InvitationService::class)->createInvitation($club, ['email' => 'invite@example.test']);

        $memberships = static::getContainer()->get(MembershipRepository::class);
        $this->assertSame(1, $memberships->countByClubExcludingUser($club, $president));
        $this->assertSame(2, $memberships->countByClubExcludingUser($club, null));
        $this->assertSame(1, $memberships->countManagersByClub($club));
        $this->assertSame(1, $memberships->countInspectorsByClub($club));
        $this->assertSame(1, static::getContainer()->get(InvitationRepository::class)->countPendingByClub($club));
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Functional/ClubCountersTest.php
```

Expected : méthode `countByClubExcludingUser` inexistante.

- [ ] **Step 3 : dépôts**

`src/Repository/MembershipRepository.php` :

```php
    public function countByClubExcludingUser(Club $club, ?User $user): int
    {
        $queryBuilder = $this->createQueryBuilder('membership')
            ->select('COUNT(membership.id)')
            ->andWhere('membership.club = :club')
            ->setParameter('club', $club);

        if ($user !== null) {
            $queryBuilder->andWhere('membership.user != :user')->setParameter('user', $user);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function countManagersByClub(Club $club): int
    {
        return (int) $this->createQueryBuilder('membership')
            ->select('COUNT(membership.id)')
            ->andWhere('membership.club = :club')
            ->andWhere('membership.isManager = true')
            ->setParameter('club', $club)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countInspectorsByClub(Club $club): int
    {
        return (int) $this->createQueryBuilder('membership')
            ->select('COUNT(membership.id)')
            ->andWhere('membership.club = :club')
            ->andWhere('membership.isInspector = true')
            ->setParameter('club', $club)
            ->getQuery()
            ->getSingleScalarResult();
    }
```

`src/Repository/InvitationRepository.php` (une invitation acceptée est supprimée : « en attente » = non expirée) :

```php
    public function countPendingByClub(Club $club): int
    {
        return (int) $this->createQueryBuilder('invitation')
            ->select('COUNT(invitation.id)')
            ->andWhere('invitation.club = :club')
            ->andWhere('invitation.expiresAt > :now')
            ->setParameter('club', $club)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }
```

`src/Repository/EquipmentRepository.php` (le champ booléen d'activité de `Equipment` s'appelle `active` ; vérifier dans `src/Entity/Equipment.php` et reprendre la condition de `filterByActive`) :

```php
    public function countActiveByClub(Club $club): int
    {
        $queryBuilder = $this->createQueryBuilder('equipment')
            ->select('COUNT(equipment.id)')
            ->andWhere('equipment.club = :club')
            ->setParameter('club', $club);
        $this->filterByActive($queryBuilder, true);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }
```

`src/Repository/PlanApplicationRepository.php` (reprendre la condition d'annulation de `filterByCancelled`) :

```php
    public function countActiveByClub(Club $club): int
    {
        $queryBuilder = $this->createQueryBuilder('application')
            ->select('COUNT(application.id)')
            ->join('application.equipment', 'equipment')
            ->andWhere('equipment.club = :club')
            ->setParameter('club', $club);
        $this->filterByCancelled($queryBuilder, false);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }
```

Si `filterByActive` ou `filterByCancelled` ajoutent un `join` ou utilisent un autre alias racine que `equipment` / `application`, aligner l'alias du `createQueryBuilder` sur celui attendu par ces méthodes.

- [ ] **Step 4 : champ `Club`**

Dans `src/Entity/Club.php`, après `$createdBy` :

```php
    /** Date à laquelle un gestionnaire a masqué la carte « Votre Tarmac est-il prêt ? ». */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $healthCheckDismissedAt = null;

    public function getHealthCheckDismissedAt(): ?\DateTimeImmutable { return $this->healthCheckDismissedAt; }
    public function setHealthCheckDismissedAt(?\DateTimeImmutable $at): static { $this->healthCheckDismissedAt = $at; return $this; }
```

```bash
symfony console make:migration && symfony console doctrine:migrations:migrate -n && symfony console doctrine:migrations:migrate --env=test -n
```

Vérifier que la migration n'ajoute que `club.health_check_dismissed_at`.

- [ ] **Step 5 : lancer**

```bash
vendor/bin/phpunit tests/Functional/ClubCountersTest.php
```

Expected : OK.

- [ ] **Step 6 : commit**

```bash
git add src migrations tests/Functional/ClubCountersTest.php
git commit -m "feat(club): compteurs par club et date de masquage de la checklist

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 2 : service `ClubHealthCheck` et les sept contrôles

**Files:**
- Create: `src/Service/HealthCheck/HealthCheckInterface.php`, `HealthCheckItem.php`, `HealthReport.php`, `ClubHealthCheck.php`, `Check/*.php`
- Test: `tests/Service/HealthCheck/ChecksTest.php`, `tests/Service/HealthCheck/ClubHealthCheckTest.php`

**Interfaces:**
- Consumes: compteurs de la Task 1, `Club::getAerodrome`, `getWelcomeMessage`, `getCreatedBy`, `getHealthCheckDismissedAt`.
- Produces: `HealthCheckItem` (`key`, `done`, `route`, `routeParams`, `essential`) ; `HealthReport` (`items`, `doneCount()`, `total()`, `isComplete()`, `hasEssentialTodo()`, `todo()`, `done()`) ; `ClubHealthCheck::check(Club): HealthReport`, `shouldDisplay(Club, HealthReport): bool`. Routes visées : `club_settings_aerodrome` (Task 5), `club_equipment_new`, `club_invitations`, `club_members`, `club_plans_explain` (Task 6), `club_dashboard_edit_welcome_message`.

- [ ] **Step 1 : tests**

`tests/Service/HealthCheck/ChecksTest.php` :

```php
<?php

namespace App\Tests\Service\HealthCheck;

use App\Entity\Aerodrome;
use App\Entity\Club;
use App\Entity\User;
use App\Repository\EquipmentRepository;
use App\Repository\InvitationRepository;
use App\Repository\MembershipRepository;
use App\Repository\PlanApplicationRepository;
use App\Service\HealthCheck\Check\AerodromeCheck;
use App\Service\HealthCheck\Check\EquipmentCheck;
use App\Service\HealthCheck\Check\InspectorCheck;
use App\Service\HealthCheck\Check\MembersCheck;
use App\Service\HealthCheck\Check\PlanCheck;
use App\Service\HealthCheck\Check\SecondManagerCheck;
use App\Service\HealthCheck\Check\WelcomeMessageCheck;
use PHPUnit\Framework\TestCase;

class ChecksTest extends TestCase
{
    public function testAerodrome(): void
    {
        $check = new AerodromeCheck();
        $this->assertFalse($check->check(new Club())->done);
        $this->assertTrue($check->check((new Club())->setAerodrome(new Aerodrome()))->done);
        $this->assertSame('club_settings_aerodrome', $check->check(new Club())->route);
        $this->assertFalse($check->check(new Club())->essential);
    }

    public function testEquipment(): void
    {
        $repository = $this->createMock(EquipmentRepository::class);
        $repository->method('countActiveByClub')->willReturnOnConsecutiveCalls(0, 2);
        $check = new EquipmentCheck($repository);

        $this->assertFalse($check->check(new Club())->done);
        $item = $check->check(new Club());
        $this->assertTrue($item->done);
        $this->assertTrue($item->essential);
        $this->assertSame('club_equipment_new', $item->route);
    }

    public function testMembersCountsInvitationsAndOtherMembershipsThanCreator(): void
    {
        $creator = new User();
        $club = (new Club())->setCreatedBy($creator);
        $memberships = $this->createMock(MembershipRepository::class);
        $memberships->method('countByClubExcludingUser')->with($club, $creator)->willReturnOnConsecutiveCalls(0, 0, 1);
        $invitations = $this->createMock(InvitationRepository::class);
        $invitations->method('countPendingByClub')->willReturnOnConsecutiveCalls(0, 1, 0);
        $check = new MembersCheck($memberships, $invitations);

        $this->assertFalse($check->check($club)->done);
        $this->assertTrue($check->check($club)->done);
        $this->assertTrue($check->check($club)->done);
    }

    public function testSecondManagerAndInspector(): void
    {
        $memberships = $this->createMock(MembershipRepository::class);
        $memberships->method('countManagersByClub')->willReturnOnConsecutiveCalls(1, 2);
        $memberships->method('countInspectorsByClub')->willReturnOnConsecutiveCalls(0, 1);

        $secondManager = new SecondManagerCheck($memberships);
        $this->assertFalse($secondManager->check(new Club())->done);
        $this->assertTrue($secondManager->check(new Club())->done);
        $this->assertFalse($secondManager->check(new Club())->essential);

        $inspector = new InspectorCheck($memberships);
        $this->assertFalse($inspector->check(new Club())->done);
        $this->assertTrue($inspector->check(new Club())->done);
        $this->assertTrue($inspector->check(new Club())->essential);
    }

    public function testPlanAndWelcomeMessage(): void
    {
        $applications = $this->createMock(PlanApplicationRepository::class);
        $applications->method('countActiveByClub')->willReturnOnConsecutiveCalls(0, 1);
        $plan = new PlanCheck($applications);
        $this->assertFalse($plan->check(new Club())->done);
        $this->assertTrue($plan->check(new Club())->done);
        $this->assertSame('club_plans_explain', $plan->check(new Club())->route);

        $welcome = new WelcomeMessageCheck();
        $this->assertFalse($welcome->check((new Club())->setWelcomeMessage('  '))->done);
        $this->assertTrue($welcome->check((new Club())->setWelcomeMessage('Bienvenue'))->done);
    }
}
```

`tests/Service/HealthCheck/ClubHealthCheckTest.php` :

```php
<?php

namespace App\Tests\Service\HealthCheck;

use App\Entity\Club;
use App\Service\HealthCheck\ClubHealthCheck;
use App\Service\HealthCheck\HealthCheckInterface;
use App\Service\HealthCheck\HealthCheckItem;
use PHPUnit\Framework\TestCase;

class ClubHealthCheckTest extends TestCase
{
    private function check(string $key, bool $done, bool $essential): HealthCheckInterface
    {
        return new class($key, $done, $essential) implements HealthCheckInterface {
            public function __construct(private readonly string $key, private readonly bool $done, private readonly bool $essential) {}

            public function check(Club $club): HealthCheckItem
            {
                return new HealthCheckItem($this->key, $this->done, 'club_dashboard', [], $this->essential);
            }
        };
    }

    public function testReportCountsAndOrder(): void
    {
        $service = new ClubHealthCheck([$this->check('a', true, true), $this->check('b', false, false), $this->check('c', false, true)]);
        $report = $service->check(new Club());

        $this->assertSame(['a', 'b', 'c'], array_map(static fn ($item) => $item->key, $report->items));
        $this->assertSame(1, $report->doneCount());
        $this->assertSame(3, $report->total());
        $this->assertFalse($report->isComplete());
        $this->assertTrue($report->hasEssentialTodo());
        $this->assertSame(['b', 'c'], array_map(static fn ($item) => $item->key, $report->todo()));
    }

    public function testDisplayRules(): void
    {
        $complete = new ClubHealthCheck([$this->check('a', true, true)]);
        $this->assertFalse($complete->shouldDisplay(new Club(), $complete->check(new Club())));

        $pending = new ClubHealthCheck([$this->check('a', false, true)]);
        $club = new Club();
        $this->assertTrue($pending->shouldDisplay($club, $pending->check($club)));

        $club->setHealthCheckDismissedAt(new \DateTimeImmutable('-2 days'));
        $this->assertFalse($pending->shouldDisplay($club, $pending->check($club)));

        $club->setHealthCheckDismissedAt(new \DateTimeImmutable('-40 days'));
        $this->assertTrue($pending->shouldDisplay($club, $pending->check($club)));

        $optionalOnly = new ClubHealthCheck([$this->check('a', false, false)]);
        $this->assertFalse($optionalOnly->shouldDisplay($club, $optionalOnly->check($club)));
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Service/HealthCheck
```

Expected : classes introuvables.

- [ ] **Step 3 : contrat et rapport**

`src/Service/HealthCheck/HealthCheckInterface.php` :

```php
<?php

namespace App\Service\HealthCheck;

use App\Entity\Club;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Un contrôle de configuration du club. Implémentations taguées app.health_check,
 * ordonnées par la priorité du tag (la plus haute en premier).
 */
#[AutoconfigureTag('app.health_check')]
interface HealthCheckInterface
{
    public function check(Club $club): HealthCheckItem;
}
```

`src/Service/HealthCheck/HealthCheckItem.php` :

```php
<?php

namespace App\Service\HealthCheck;

final readonly class HealthCheckItem
{
    /**
     * @param string $key clé de traduction : healthCheck<Key>Title et healthCheck<Key>Why
     * @param array<string, mixed> $routeParams
     */
    public function __construct(
        public string $key,
        public bool $done,
        public string $route,
        public array $routeParams = [],
        public bool $essential = false,
    ) {
    }

    public function translationKey(string $suffix): string
    {
        return 'healthCheck' . ucfirst($this->key) . $suffix;
    }
}
```

`src/Service/HealthCheck/HealthReport.php` :

```php
<?php

namespace App\Service\HealthCheck;

final readonly class HealthReport
{
    /** @param HealthCheckItem[] $items */
    public function __construct(public array $items)
    {
    }

    public function total(): int
    {
        return count($this->items);
    }

    public function doneCount(): int
    {
        return count($this->done());
    }

    public function isComplete(): bool
    {
        return $this->todo() === [];
    }

    public function hasEssentialTodo(): bool
    {
        foreach ($this->todo() as $item) {
            if ($item->essential) {
                return true;
            }
        }

        return false;
    }

    /** @return HealthCheckItem[] */
    public function todo(): array
    {
        return array_values(array_filter($this->items, static fn (HealthCheckItem $item): bool => !$item->done));
    }

    /** @return HealthCheckItem[] */
    public function done(): array
    {
        return array_values(array_filter($this->items, static fn (HealthCheckItem $item): bool => $item->done));
    }
}
```

`src/Service/HealthCheck/ClubHealthCheck.php` :

```php
<?php

namespace App\Service\HealthCheck;

use App\Entity\Club;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ClubHealthCheck
{
    public const REDISPLAY_AFTER_DAYS = 30;

    /** @param iterable<HealthCheckInterface> $checks */
    public function __construct(
        #[AutowireIterator('app.health_check')] private readonly iterable $checks,
    ) {
    }

    public function check(Club $club): HealthReport
    {
        $items = [];
        foreach ($this->checks as $check) {
            $items[] = $check->check($club);
        }

        return new HealthReport($items);
    }

    /**
     * La carte disparaît quand tout est fait ; masquée, elle revient si un contrôle
     * essentiel manque et que le masquage date de plus de 30 jours.
     */
    public function shouldDisplay(Club $club, HealthReport $report): bool
    {
        if ($report->isComplete()) {
            return false;
        }

        $dismissedAt = $club->getHealthCheckDismissedAt();
        if ($dismissedAt === null) {
            return true;
        }

        $threshold = new \DateTimeImmutable(sprintf('-%d days', self::REDISPLAY_AFTER_DAYS));

        return $report->hasEssentialTodo() && $dismissedAt < $threshold;
    }
}
```

- [ ] **Step 4 : les contrôles**

Chaque classe dans `src/Service/HealthCheck/Check/`, avec `use App\Entity\Club; use App\Service\HealthCheck\HealthCheckInterface; use App\Service\HealthCheck\HealthCheckItem; use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;`.

```php
#[AutoconfigureTag('app.health_check', ['priority' => 100])]
final class AerodromeCheck implements HealthCheckInterface
{
    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('aerodrome', $club->getAerodrome() !== null, 'club_settings_aerodrome', [], false);
    }
}
```

```php
#[AutoconfigureTag('app.health_check', ['priority' => 90])]
final class EquipmentCheck implements HealthCheckInterface
{
    public function __construct(private readonly EquipmentRepository $equipmentRepository) {}

    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('equipment', $this->equipmentRepository->countActiveByClub($club) > 0, 'club_equipment_new', [], true);
    }
}
```

```php
#[AutoconfigureTag('app.health_check', ['priority' => 80])]
final class MembersCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly MembershipRepository $membershipRepository,
        private readonly InvitationRepository $invitationRepository,
    ) {}

    public function check(Club $club): HealthCheckItem
    {
        $others = $this->membershipRepository->countByClubExcludingUser($club, $club->getCreatedBy());
        $pending = $this->invitationRepository->countPendingByClub($club);

        return new HealthCheckItem('members', $others + $pending > 0, 'club_invitations', [], true);
    }
}
```

```php
#[AutoconfigureTag('app.health_check', ['priority' => 70])]
final class SecondManagerCheck implements HealthCheckInterface
{
    public function __construct(private readonly MembershipRepository $membershipRepository) {}

    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('secondManager', $this->membershipRepository->countManagersByClub($club) >= 2, 'club_members', [], false);
    }
}
```

```php
#[AutoconfigureTag('app.health_check', ['priority' => 60])]
final class InspectorCheck implements HealthCheckInterface
{
    public function __construct(private readonly MembershipRepository $membershipRepository) {}

    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('inspector', $this->membershipRepository->countInspectorsByClub($club) >= 1, 'club_members', [], true);
    }
}
```

```php
#[AutoconfigureTag('app.health_check', ['priority' => 50])]
final class PlanCheck implements HealthCheckInterface
{
    public function __construct(private readonly PlanApplicationRepository $planApplicationRepository) {}

    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('plan', $this->planApplicationRepository->countActiveByClub($club) >= 1, 'club_plans_explain', [], false);
    }
}
```

```php
#[AutoconfigureTag('app.health_check', ['priority' => 40])]
final class WelcomeMessageCheck implements HealthCheckInterface
{
    public function check(Club $club): HealthCheckItem
    {
        return new HealthCheckItem('welcomeMessage', trim((string) $club->getWelcomeMessage()) !== '', 'club_dashboard_edit_welcome_message', [], false);
    }
}
```

Le `#[AutoconfigureTag]` sur l'interface suffit à taguer ; celui sur chaque classe porte la priorité.

- [ ] **Step 5 : lancer**

```bash
vendor/bin/phpunit tests/Service/HealthCheck && symfony console cache:clear && symfony console debug:container --tag=app.health_check
```

Expected : 7 tests OK ; sept services listés, priorités 100 à 40.

- [ ] **Step 6 : commit**

```bash
git add src/Service/HealthCheck tests/Service/HealthCheck
git commit -m "feat(healthcheck): service de contrôle de configuration du club

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 3 : carte sur le tableau de bord, masquage et badge de menu

**Files:**
- Create: `templates/club/dashboard/healthCheck.html.twig`, `src/Twig/HealthCheckExtension.php`
- Modify: `src/Controller/Club/DashboardController.php`, `templates/club/dashboard.html.twig`, `templates/club/base.html.twig`
- Test: `tests/Functional/HealthCheckTest.php`

**Interfaces:**
- Consumes: `ClubHealthCheck::check/shouldDisplay` (Task 2), `Club::setHealthCheckDismissedAt` (Task 1).
- Produces: route `club_dashboard_dismiss_health_check` (`POST /health-check/dismiss`), paramètre `?healthCheck=1` sur `club_dashboard` qui annule le masquage, fonction Twig `club_health_report(club)`.

- [ ] **Step 1 : test**

`tests/Functional/HealthCheckTest.php` :

```php
<?php

namespace App\Tests\Functional;

use App\Entity\Club;
use App\Entity\Membership;

class HealthCheckTest extends FunctionalTestCase
{
    private function clubUrl(Club $club, string $path = '/'): string
    {
        return 'https://' . $club->getSubdomain() . '.' . static::getContainer()->getParameter('domain') . $path;
    }

    public function testManagerSeesCardAndCanDismissIt(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $this->entityManager()->flush();
        $this->client->loginUser($manager);

        $crawler = $this->client->request('GET', $this->clubUrl($club));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Votre Tarmac est-il prêt');
        $this->assertSelectorTextContains('.tarmac-healthcheck', '0 sur 7');

        $this->client->submit($crawler->selectButton('Masquer')->form());
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertSelectorNotExists('.tarmac-healthcheck');
        $this->assertSelectorTextContains('body', 'Configuration 0/7');

        $this->client->request('GET', $this->clubUrl($club, '/?healthCheck=1'));
        $this->assertSelectorExists('.tarmac-healthcheck');
    }

    public function testPlainMemberDoesNotSeeCard(): void
    {
        $member = $this->createUser('membre@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($member)->setClub($club));
        $this->entityManager()->flush();
        $this->client->loginUser($member);

        $this->client->request('GET', $this->clubUrl($club));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('.tarmac-healthcheck');
    }
}
```

- [ ] **Step 2 : vérifier l'échec**

```bash
vendor/bin/phpunit tests/Functional/HealthCheckTest.php
```

Expected : « Votre Tarmac est-il prêt » absent.

- [ ] **Step 3 : contrôleur**

Dans `src/Controller/Club/DashboardController.php`, injecter `private readonly ClubHealthCheck $clubHealthCheck` (`use App\Service\HealthCheck\ClubHealthCheck;`). Dans `index()`, après `$isInspector = ...` :

```php
        $healthReport = null;
        $showHealthCheck = false;
        if ($isManager) {
            if ($request->query->getBoolean('healthCheck') && $club->getHealthCheckDismissedAt() !== null) {
                $club->setHealthCheckDismissedAt(null);
                $this->entityManager->flush();
            }
            $healthReport = $this->clubHealthCheck->check($club);
            $showHealthCheck = $this->clubHealthCheck->shouldDisplay($club, $healthReport);
        }
```

Ajouter `'healthReport' => $healthReport, 'showHealthCheck' => $showHealthCheck,` aux variables passées à `render()`. Nouvelle action :

```php
    #[Route('/health-check/dismiss', name: 'club_dashboard_dismiss_health_check', methods: ['POST'])]
    #[IsGranted('MANAGE')]
    public function dismissHealthCheck(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('dismiss-health-check', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $this->clubResolver->resolve()->setHealthCheckDismissedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->redirectToRoute('club_dashboard');
    }
```

- [ ] **Step 4 : partial et intégration**

`templates/club/dashboard/healthCheck.html.twig` :

```twig
{# Carte « Votre Tarmac est-il prêt ? » — gestionnaires uniquement #}
<div class="tarmac-panel tarmac-healthcheck mb-4">
	<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
		<div>
			<h3 style="font-size:1.1rem;font-weight:800;margin:0;"><i class="ti ti-checklist me-2"></i>{{ 'healthCheckTitle'|trans }}</h3>
			<div class="text-secondary" style="font-size:0.9rem;">{{ 'healthCheckProgress'|trans({done: report.doneCount, total: report.total}) }}</div>
		</div>
		<form method="post" action="{{ path('club_dashboard_dismiss_health_check') }}">
			<input type="hidden" name="_token" value="{{ csrf_token('dismiss-health-check') }}">
			<button type="submit" class="btn btn-sm btn-ghost-secondary" style="min-height:44px;">{{ 'hide'|trans }}</button>
		</form>
	</div>
	<div class="progress mt-3 mb-3" style="height:6px;">
		<div class="progress-bar" role="progressbar" style="width: {{ report.total > 0 ? (report.doneCount * 100 / report.total)|round : 0 }}%"></div>
	</div>
	<div class="list-group list-group-flush">
		{% for item in report.todo %}
			<div class="list-group-item px-0 d-flex gap-3 align-items-start">
				<span class="mt-1" style="color:var(--td-ink-3);"><i class="ti ti-circle"></i></span>
				<div class="flex-fill" style="min-width:0;">
					<div class="fw-bold">{{ item.translationKey('Title')|trans }}{% if item.essential %} <span class="badge bg-orange-lt ms-1">{{ 'essential'|trans }}</span>{% endif %}</div>
					<div class="text-secondary" style="font-size:0.9rem;">{{ item.translationKey('Why')|trans }}</div>
					<a href="{{ path(item.route, item.routeParams) }}" class="btn btn-sm btn-primary mt-2" style="min-height:40px;">{{ item.translationKey('Action')|trans }}</a>
				</div>
			</div>
		{% endfor %}
	</div>
	{% if report.done is not empty %}
		<details class="mt-2">
			<summary class="text-secondary" style="cursor:pointer;min-height:44px;line-height:44px;">{{ 'healthCheckShowDone'|trans({count: report.doneCount}) }}</summary>
			<ul class="list-unstyled mb-0">
				{% for item in report.done %}
					<li class="text-secondary"><i class="ti ti-circle-check text-success me-2"></i>{{ item.translationKey('Title')|trans }}</li>
				{% endfor %}
			</ul>
		</details>
	{% endif %}
</div>
```

Dans `templates/club/dashboard.html.twig`, juste avant `{# Welcome Message — « mot du gestionnaire » #}` :

```twig
	{% if showHealthCheck and healthReport %}
		{% include 'club/dashboard/healthCheck.html.twig' with {report: healthReport} %}
	{% endif %}
```

- [ ] **Step 5 : badge de menu**

`src/Twig/HealthCheckExtension.php` :

```php
<?php

namespace App\Twig;

use App\Entity\Club;
use App\Service\HealthCheck\ClubHealthCheck;
use App\Service\HealthCheck\HealthReport;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** Expose le rapport de configuration au layout (badge du menu), calculé une fois par requête. */
final class HealthCheckExtension extends AbstractExtension
{
    private ?HealthReport $cached = null;
    private ?int $cachedClubId = null;

    public function __construct(private readonly ClubHealthCheck $clubHealthCheck)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('club_health_report', $this->report(...))];
    }

    public function report(Club $club): HealthReport
    {
        if ($this->cached === null || $this->cachedClubId !== $club->getId()) {
            $this->cached = $this->clubHealthCheck->check($club);
            $this->cachedClubId = $club->getId();
        }

        return $this->cached;
    }
}
```

Dans `templates/club/base.html.twig`, dans le menu déroulant « Club » (bloc `navbar_menu`), après l'entrée `club_specialisations` et à l'intérieur du même `{% if is_granted('MANAGE') %}` :

```twig
					{% set healthReport = club_health_report(club) %}
					{% if not healthReport.isComplete %}
						<a href="{{ path('club_dashboard', {healthCheck: 1}) }}" class="dropdown-item">
							<i class="ti ti-checklist me-2"></i>
							{{ 'healthCheckMenu'|trans({done: healthReport.doneCount, total: healthReport.total}) }}
						</a>
					{% endif %}
```

Si la variable `club` n'est pas disponible dans `club/base.html.twig`, la lire via `app.request.attributes.get('_club')` (cache de `ClubResolver`).

- [ ] **Step 6 : traductions**

Sous `<!-- Checklist de configuration -->` :

| resname | target |
|---|---|
| `healthCheckTitle` | Votre Tarmac est-il prêt ? |
| `healthCheckProgress` | {done} sur {total} |
| `healthCheckMenu` | Configuration {done}/{total} |
| `healthCheckShowDone` | {count, plural, one {Voir le point terminé} other {Voir les # points terminés}} |
| `hide` | Masquer |
| `essential` | Essentiel |
| `healthCheckAerodromeTitle` | Votre aérodrome |
| `healthCheckAerodromeWhy` | Il situe votre club et évite qu'un autre président le recrée par erreur. |
| `healthCheckAerodromeAction` | Indiquer mon terrain |
| `healthCheckEquipmentTitle` | Votre matériel |
| `healthCheckEquipmentWhy` | Sans matériel, rien à entretenir : commencez par un planeur, un avion ou un treuil. |
| `healthCheckEquipmentAction` | Ajouter un matériel |
| `healthCheckMembersTitle` | Vos membres |
| `healthCheckMembersWhy` | Chaque membre invité pourra saisir son temps, commenter et signaler une anomalie. |
| `healthCheckMembersAction` | Inviter des membres |
| `healthCheckSecondManagerTitle` | Un second gestionnaire |
| `healthCheckSecondManagerWhy` | Pour que le club ne dépende pas d'une seule personne. |
| `healthCheckSecondManagerAction` | Choisir un membre |
| `healthCheckInspectorTitle` | Un qualifié |
| `healthCheckInspectorWhy` | Sans qualifié, les sous-tâches à inspecter restent bloquées. |
| `healthCheckInspectorAction` | Choisir un membre |
| `healthCheckPlanTitle` | Un plan d'entretien appliqué |
| `healthCheckPlanWhy` | Le plan décrit les opérations récurrentes ; Tarmac génère ensuite les tâches tout seul. |
| `healthCheckPlanAction` | Comprendre les plans |
| `healthCheckWelcomeMessageTitle` | Un message d'accueil |
| `healthCheckWelcomeMessageWhy` | Vos membres le lisent en arrivant : consignes, contacts, ce que vous attendez d'eux. |
| `healthCheckWelcomeMessageAction` | Rédiger le message |

- [ ] **Step 7 : lancer**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/HealthCheckTest.php && symfony console lint:twig templates/club && symfony console lint:xliff translations/
```

Expected : 2 tests OK (les routes `club_settings_aerodrome` et `club_plans_explain` n'existent pas encore : si `path()` échoue, exécuter les tâches 5 et 6 avant de relancer, l'ordre des commits reste le même).

- [ ] **Step 8 : commit**

```bash
git add src templates translations tests/Functional/HealthCheckTest.php
git commit -m "feat(dashboard): carte « Votre Tarmac est-il prêt ? » et badge de configuration

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 4 : page « Votre aérodrome » du club

**Files:**
- Create: `src/Controller/Club/SettingsController.php`, `src/Form/ClubAerodromeType.php`, `templates/club/settings/aerodrome.html.twig`
- Test: ajouter un test dans `tests/Functional/HealthCheckTest.php`

**Interfaces:**
- Consumes: `AerodromeSelectorType`, partial `component/aerodromeSelector.html.twig`, route `api_aerodromes` (plan 1, Task 8).
- Produces: route `club_settings_aerodrome` (`GET|POST /reglages/aerodrome`).

- [ ] **Step 1 : test**

Dans `HealthCheckTest` :

```php
    public function testManagerSetsAerodrome(): void
    {
        $manager = $this->createUser('manager@example.test');
        $club = $this->createClub('Club', 'club');
        $club->addMembership((new Membership())->setUser($manager)->setClub($club)->setIsManager(true));
        $aerodrome = (new \App\Entity\Aerodrome())->setSourceId(1)->setName('Bailleau')->setLatitude(48.5)->setLongitude(1.6)->refreshSearchIndex();
        $this->entityManager()->persist($aerodrome);
        $this->entityManager()->flush();
        $this->client->loginUser($manager);

        $this->client->request('GET', $this->clubUrl($club, '/reglages/aerodrome'));
        $this->assertResponseIsSuccessful();
        $this->client->submitForm('Enregistrer', ['club_aerodrome[aerodrome]' => (string) $aerodrome->getId()]);

        $this->assertResponseRedirects($this->clubUrl($club));
        $this->entityManager()->refresh($club);
        $this->assertSame('Bailleau', $club->getAerodrome()->getName());
        $this->assertEqualsWithDelta(48.5, $club->getLatitude(), 0.001);
    }
```

- [ ] **Step 2 : formulaire et contrôleur**

`src/Form/ClubAerodromeType.php` :

```php
<?php

namespace App\Form;

use App\Entity\Club;
use App\Form\Type\AerodromeSelectorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ClubAerodromeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('aerodrome', AerodromeSelectorType::class)
            ->add('locationLabel', TextType::class, ['label' => 'municipality', 'required' => false]);
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

`src/Controller/Club/SettingsController.php` :

```php
<?php

namespace App\Controller\Club;

use App\Controller\ExtendedController;
use App\Form\ClubAerodromeType;
use App\Service\ClubResolver;
use App\Service\SubdomainService;
use Doctrine\ORM\EntityManagerInterface;
use SlopeIt\BreadcrumbBundle\Attribute\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reglages', host: '{subdomain}.%domain%', requirements: ['subdomain' => '(?!www|app).*'])]
#[IsGranted('MANAGE')]
class SettingsController extends ExtendedController
{
    public function __construct(
        SubdomainService $subdomainService,
        private readonly ClubResolver $clubResolver,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($subdomainService);
    }

    #[Route('/aerodrome', name: 'club_settings_aerodrome', methods: ['GET', 'POST'])]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'aerodrome'],
    ])]
    public function aerodrome(Request $request): Response
    {
        $club = $this->clubResolver->resolve();
        $form = $this->createForm(ClubAerodromeType::class, $club);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $club->syncCoordinatesFromAerodrome();
            $this->entityManager->flush();
            $this->addFlash('success', 'aerodromeSaved');

            return $this->redirectToRoute('club_dashboard');
        }

        return $this->render('club/settings/aerodrome.html.twig', ['club' => $club, 'form' => $form]);
    }
}
```

- [ ] **Step 3 : template**

`templates/club/settings/aerodrome.html.twig` :

```twig
{% extends 'club/base.html.twig' %}

{% block page_title %}{{ 'aerodrome'|trans }}{% endblock %}

{% block page_content %}
	<div class="tarmac-panel" style="max-width:640px;">
		<p class="text-secondary">{{ 'aerodromeSettingsLead'|trans }}</p>
		{{ form_start(form) }}
		{% include 'component/aerodromeSelector.html.twig' with {
			aerodromeField: form.aerodrome,
			locationField: form.locationLabel,
			searchUrl: path('api_aerodromes'),
		} %}
		<button type="submit" class="btn btn-primary w-100" style="min-height:48px;">
			<i class="ti ti-device-floppy me-2"></i>{{ 'save'|trans }}
		</button>
		{{ form_end(form) }}
	</div>
{% endblock %}
```

Traductions : `aerodromeSettingsLead` → « Le terrain où votre club vole. Il sert à situer votre club et à éviter les doublons. », `aerodromeSaved` → « Aérodrome enregistré ». Vérifier que `save` existe (sinon « Enregistrer »).

- [ ] **Step 4 : lancer et commit**

```bash
symfony console cache:clear && vendor/bin/phpunit tests/Functional/HealthCheckTest.php && symfony console lint:twig templates/club/settings && symfony console lint:xliff translations/
git add src templates translations tests
git commit -m "feat(club): page de réglage de l'aérodrome

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 5 : page « Comprendre les plans d'entretien »

**Files:**
- Modify: `src/Controller/Club/PlanController.php`
- Create: `templates/club/plan/explain.html.twig`

**Interfaces:**
- Produces: route `club_plans_explain` (`GET /plans/comprendre`), gestionnaires.

- [ ] **Step 1 : action**

Dans `PlanController`, **avant** l'action `show` (`/{id}`) pour que `comprendre` ne soit pas capté comme id :

```php
    #[Route('/comprendre', name: 'club_plans_explain', methods: ['GET'])]
    #[IsGranted('MANAGE')]
    #[Breadcrumb([
        ['label' => 'home', 'route' => 'club_dashboard'],
        ['label' => 'maintenancePlans', 'route' => 'club_plans'],
        ['label' => 'understandPlans'],
    ])]
    public function explain(): Response
    {
        return $this->render('club/plan/explain.html.twig');
    }
```

Vérifier le libellé utilisé par le breadcrumb existant de `club_plans` (clé `maintenancePlans` ou équivalent) et le reprendre.

- [ ] **Step 2 : template**

`templates/club/plan/explain.html.twig` :

```twig
{% extends 'club/base.html.twig' %}

{% block page_title %}{{ 'understandPlans'|trans }}{% endblock %}

{% block page_content %}
	<div class="row g-3">
		{% for step in ['Describe', 'Apply', 'Generate'] %}
			<div class="col-12 col-md-4">
				<div class="tarmac-panel h-100">
					<div style="font-size:2rem;color:var(--td-glow);"><i class="ti {{ step == 'Describe' ? 'ti-list-details' : (step == 'Apply' ? 'ti-plane-tilt' : 'ti-refresh') }}"></i></div>
					<h3 style="font-size:1.05rem;font-weight:800;margin:0.5rem 0;">{{ ('planExplain' ~ step ~ 'Title')|trans }}</h3>
					<p class="text-secondary mb-0">{{ ('planExplain' ~ step ~ 'Body')|trans }}</p>
				</div>
			</div>
		{% endfor %}
	</div>
	<div class="tarmac-panel mt-4">
		<p class="mb-3">{{ 'planExplainNoPlanNote'|trans }}</p>
		<div class="d-flex gap-2 flex-wrap">
			<a href="{{ path('club_plan_new') }}" class="btn btn-primary" style="min-height:48px;"><i class="ti ti-plus me-2"></i>{{ 'planExplainCreateFirst'|trans }}</a>
			<a href="{{ url('public_guide') }}#plans" class="btn btn-outline-secondary" style="min-height:48px;"><i class="ti ti-book-2 me-2"></i>{{ 'planExplainReadGuide'|trans }}</a>
		</div>
	</div>
{% endblock %}
```

Traductions (les six clés dynamiques `planExplain{Describe,Apply,Generate}{Title,Body}` sont à ajouter toutes, aucun outil ne les voit) :

| resname | target |
|---|---|
| `understandPlans` | Comprendre les plans d'entretien |
| `planExplainDescribeTitle` | 1. Un plan décrit les opérations |
| `planExplainDescribeBody` | Pour un type de matériel (planeur, avion, infrastructure), vous listez les tâches récurrentes et leur fréquence : visite annuelle, contrôle des 50 heures, graissage du treuil. |
| `planExplainApplyTitle` | 2. Vous l'appliquez à chaque appareil |
| `planExplainApplyBody` | Le même plan sert à tous vos planeurs. Vous l'appliquez à chacun, avec sa date de départ. |
| `planExplainGenerateTitle` | 3. Tarmac génère les tâches |
| `planExplainGenerateBody` | Les tâches apparaissent au bon moment, chaque membre y contribue, les qualifiés signent, le carnet mécanicien se remplit. |
| `planExplainNoPlanNote` | Vous pouvez aussi créer des tâches ponctuelles sans plan. Le plan sert à ne plus y penser. |
| `planExplainCreateFirst` | Créer mon premier plan |
| `planExplainReadGuide` | Lire le guide |

- [ ] **Step 3 : lancer et commit**

```bash
symfony console cache:clear && symfony console lint:twig templates/club/plan && symfony console lint:xliff translations/ && vendor/bin/phpunit tests/Functional/HealthCheckTest.php
git add src/Controller/Club/PlanController.php templates/club/plan/explain.html.twig translations
git commit -m "feat(plans): page d'explication des plans d'entretien

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
```

---

### Task 6 : guide et vérification finale

**Files:**
- Modify: `templates/public/guide/index.html.twig`

- [ ] **Step 1 : guide**

Dans la section « Premiers pas », après la liste existante, ajouter :

```twig
								<h3>Vous venez de créer votre club</h3>
								<p>Votre tableau de bord affiche la carte <strong>« Votre Tarmac est-il prêt ? »</strong>, visible des gestionnaires seulement. Elle liste ce qu'il reste à configurer, dans l'ordre conseillé, avec un bouton par point&nbsp;:</p>
								<ol>
									<li><strong>Votre aérodrome</strong>, pour situer le club.</li>
									<li><strong>Votre matériel</strong>&nbsp;: au moins un planeur, un avion ou une infrastructure.</li>
									<li><strong>Vos membres</strong>&nbsp;: invitez-les par fichier ou un par un.</li>
									<li><strong>Un second gestionnaire</strong> et <strong>un qualifié</strong>, à désigner sur la fiche d'un membre.</li>
									<li><strong>Un plan d'entretien</strong> appliqué à un matériel. La page « Comprendre les plans » explique le principe en trois cartes.</li>
									<li><strong>Un message d'accueil</strong> pour vos membres.</li>
								</ol>
								<p>Les points marqués <strong>Essentiel</strong> sont ceux sans lesquels Tarmac ne peut pas fonctionner. La carte disparaît quand tout est fait. Vous pouvez la masquer&nbsp;; elle reste accessible depuis le menu <strong>Club</strong> (« Configuration 3/7 ») et revient d'elle-même au bout d'un mois s'il manque un point essentiel.</p>
```

Dans la section « Les matériels », signaler en une phrase que l'aérodrome du club se règle depuis le menu Club ou la carte de configuration.

- [ ] **Step 2 : vérification complète**

```bash
symfony console cache:clear && vendor/bin/phpunit && symfony console lint:twig templates && symfony console lint:xliff translations/ && symfony console debug:translation fr --domain=messages --only-missing && symfony console doctrine:schema:validate
```

Expected : suite verte, aucune clé manquante. Parcours manuel : créer un club via le tunnel, ouvrir son tableau de bord à 320 px, suivre chaque bouton de la carte, masquer, rouvrir depuis le menu.

- [ ] **Step 3 : commit et déploiement**

```bash
git add templates/public/guide/index.html.twig
git commit -m "docs(guide): checklist de configuration du club

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>"
git push origin main
```

---

## Auto-revue du plan

- Couverture de la spec : §4.1 → Task 2 ; §4.2 → Task 2 (sept contrôles, `essential` conforme au tableau) ; §4.3 → Task 3 ; §4.4 → Task 4 ; §4.5 → Task 5 ; §7 → Task 6 ; §8 (`healthCheckDismissedAt`) → Task 1.
- Cohérence des noms : `HealthCheckItem(key, done, route, routeParams, essential)` et `translationKey()` (Task 2, 3) ; `ClubHealthCheck::check/shouldDisplay` (Task 2, 3) ; routes `club_settings_aerodrome` (Task 2, 4), `club_plans_explain` (Task 2, 5), `club_dashboard_dismiss_health_check` (Task 3) ; compteurs de la Task 1 utilisés par les contrôles de la Task 2 avec les mêmes signatures.
- Dépendance sur le plan 1 : `Aerodrome`, `Club::aerodrome`, `AerodromeSelectorType`, partial et route `api_aerodromes` (Task 3, 4, 8 du plan 1), socle de tests (Task 1 du plan 1).
