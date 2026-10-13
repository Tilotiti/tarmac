# Anomalies liées aux sous-tâches : plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** une anomalie se rattache à une ou plusieurs sous-tâches au lieu de tâches entières, via un parcours « Lier des sous-tâches » en deux écrans.

**Architecture:** relation ManyToMany `Anomaly ↔ SubTask` (table `anomaly_sub_task`) qui remplace `anomaly_task`. Le statut de l'anomalie reste dérivé, calculé sur les sous-tâches actives. Deux nouveaux écrans dans `AnomalyController`, réutilisation des formulaires de tâche et de sous-tâche existants avec le paramètre `?anomaly=`. On ajoute le nouveau lien d'abord (tâches 1 à 4), on retire l'ancien à la fin (tâche 5).

**Tech Stack:** Symfony 7.4, PHP 8.5, Doctrine ORM + migrations (PostgreSQL), Twig, Stimulus (AssetMapper), PHPUnit + DAMA.

**Spec:** `docs/superpowers/specs/2026-10-13-anomalies-sous-taches-design.md`

## Global Constraints

- Ne pas pousser sur `main` avant la fin de la tâche 6 : un push déclenche le déploiement Heroku et les migrations.
- Commits directs sur `main`, messages en français, conventional commits, terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Contrôleurs : étendre `ExtendedController`. Toute vérification de droit passe par un Voter.
- Aliases de QueryBuilder descriptifs et complets (`anomaly_with_subtask`, `linked_subtask`, `linked_subtask_task`), jamais `a`, `s`, `t`.
- Traductions : clés `lowerCamelCase`, ajout manuel en fin de `<body>` de `translations/messages+intl-icu.fr.xlf`, ids à partir de **1903**, groupées sous un commentaire XML, puis `symfony console lint:xliff translations/`. Ne jamais lancer `translation:extract`.
- Templates camelCase, mobile-first (320 px d'abord), cibles tactiles ≥ 44 px, breadcrumb obligatoire sur les nouvelles pages (début `['label' => 'home', 'route' => 'club_dashboard']`).
- Liens stylés en carte ou bouton : `text-decoration: none` sur l'état de base et sur `:hover`/`:focus`/`:active`.
- Statut « actif » d'une sous-tâche liée : `subTask.status !== 'cancelled'` **et** `subTask.task.status !== 'cancelled'`.
- Tests : `vendor/bin/phpunit` (base de test = PostgreSQL locale de `.env.test.local`, transactions DAMA). `failOnDeprecation/Notice/Warning` actif.

## Review Focus

1. Un POST de l'écran 2 avec des ids de sous-tâches d'une autre tâche, d'un autre matériel ou d'un autre club : rien n'est lié pour ces ids (tâche 3, `testForeignSubTaskIdsAreIgnored`).
2. L'URL de l'écran 2 avec une tâche d'un autre matériel : redirection vers l'anomalie avec le message `anomalyTaskEquipmentMismatch` (tâche 3, `testTaskOfAnotherEquipmentIsRefused`).
3. Une anomalie traitée ou ignorée pendant que l'écran 2 est ouvert : le POST est refusé, rien n'est lié (tâche 3, `testResolvedAnomalyCannotBeLinked`).
4. Tâche annulée après rattachement d'une sous-tâche close : l'anomalie repasse en « Relevé » et n'est plus traitable (tâche 1, `testSubTasksOfACancelledTaskDoNotCount`).
5. Double soumission de l'écran 2 (retour arrière, double clic) : pas de doublon de lien ni d'entrée de journal (tâche 2, `testLinkSubTasksIsIdempotent`).

---

### Task 1 : Modèle, migration de conversion et statut dérivé

**Files:**
- Modify: `src/Entity/Anomaly.php` (ajout `$subTasks`, statut sur les sous-tâches)
- Modify: `src/Entity/SubTask.php` (relation inverse `$anomalies`)
- Modify: `src/Repository/AnomalyRepository.php:131-190` (`filterByStatus`, sous-requête)
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (générée, puis complétée)
- Modify: `tests/Entity/AnomalyTest.php` (réécrite sur les sous-tâches)
- Modify: `tests/Service/Maintenance/AnomalyStatusServiceTest.php` (`testMarkTreatedRefusesAnIneligibleAnomaly`, `testMarkTreatedStampsTheResolution`)
- Create: `tests/Repository/AnomalyRepositoryTest.php`

**Interfaces:**
- Produces:
  - `Anomaly::getSubTasks(): Collection<int, SubTask>`, `Anomaly::addSubTask(SubTask): static`, `Anomaly::removeSubTask(SubTask): static` (maintiennent les deux côtés)
  - `Anomaly::getActiveSubTasks(): Collection<int, SubTask>`
  - `SubTask::getAnomalies(): Collection<int, Anomaly>`, `SubTask::addAnomaly(Anomaly): static`, `SubTask::removeAnomaly(Anomaly): static` (côté inverse, sans rappel)
  - table `anomaly_sub_task (anomaly_id, sub_task_id)`, `ON DELETE CASCADE` des deux côtés
- L'ancien lien (`$tasks`, `addTask`, `getActiveTasks`, `anomaly_task`) reste en place jusqu'à la tâche 5, mais `getStatus()` et `canBeMarkedAsTreated()` ne le lisent plus.

- [ ] **Step 1 : réécrire `tests/Entity/AnomalyTest.php` sur les sous-tâches**

Remplacer le helper `createTask()` par :

```php
private function createSubTask(string $status, string $taskStatus = 'open'): SubTask
{
    $task = new Task();
    $task->setStatus($taskStatus);
    $subTask = new SubTask();
    $subTask->setStatus($status);
    $task->addSubTask($subTask);

    return $subTask;
}
```

Chaque test existant passe de `addTask($this->createTask(X))` à `addSubTask($this->createSubTask(X))`, noms de méthodes renommés `…Task…` → `…SubTask…`. Ajouter :

```php
public function testCannotBeTreatedWhileOneSubTaskAwaitsInspection(): void
{
    $anomaly = new Anomaly();
    $anomaly->addSubTask($this->createSubTask('closed'));
    $anomaly->addSubTask($this->createSubTask('done'));

    $this->assertSame(AnomalyStatus::ACKNOWLEDGED, $anomaly->getStatus());
    $this->assertFalse($anomaly->canBeMarkedAsTreated());
}

public function testSubTasksOfACancelledTaskDoNotCount(): void
{
    // handleCancelTask n'annule que les sous-tâches ouvertes : une sous-tâche close
    // d'une tâche annulée garde son statut, mais ne doit plus compter.
    $anomaly = new Anomaly();
    $anomaly->addSubTask($this->createSubTask('closed', 'cancelled'));

    $this->assertSame(AnomalyStatus::REPORTED, $anomaly->getStatus());
    $this->assertFalse($anomaly->canBeMarkedAsTreated());
}

public function testLinkIsMaintainedOnBothSides(): void
{
    $anomaly = new Anomaly();
    $subTask = $this->createSubTask('open');
    $anomaly->addSubTask($subTask);

    $this->assertTrue($subTask->getAnomalies()->contains($anomaly));

    $anomaly->removeSubTask($subTask);

    $this->assertFalse($subTask->getAnomalies()->contains($anomaly));
}
```

- [ ] **Step 2 : lancer les tests, vérifier l'échec**

Run: `vendor/bin/phpunit tests/Entity/AnomalyTest.php`
Expected: FAIL, `Call to undefined method App\Entity\Anomaly::addSubTask()`.

- [ ] **Step 3 : implémenter la relation et le statut**

Dans `Anomaly` : `#[ORM\ManyToMany(targetEntity: SubTask::class, inversedBy: 'anomalies')]` + `#[ORM\JoinTable(name: 'anomaly_sub_task')]`, initialisée dans le constructeur. `getActiveSubTasks()` filtre selon la contrainte globale « actif ». `getStatus()` et `canBeMarkedAsTreated()` utilisent `getActiveSubTasks()` et `SubTask::isClosed()`, avec la même structure qu'aujourd'hui. Dans `SubTask` : `#[ORM\ManyToMany(targetEntity: Anomaly::class, mappedBy: 'subTasks')]`.

- [ ] **Step 4 : lancer les tests, vérifier le succès**

Run: `vendor/bin/phpunit tests/Entity/AnomalyTest.php`
Expected: PASS.

Dans `AnomalyStatusServiceTest`, `testMarkTreatedRefusesAnIneligibleAnomaly` et `testMarkTreatedStampsTheResolution` rendent l'anomalie (in)éligible avec `addTask()`. Les passer sur `addSubTask()` avec une sous-tâche `open`, puis `closed`, rangée dans une tâche `open`.
Run: `vendor/bin/phpunit tests/Service/Maintenance/AnomalyStatusServiceTest.php`
Expected: PASS.

- [ ] **Step 5 : générer la migration et y ajouter la copie**

Run: `symfony console doctrine:migrations:diff`
La migration générée crée `anomaly_sub_task` (index et clés étrangères `ON DELETE CASCADE`). Ajouter dans `up()`, après la création, la copie :

```sql
INSERT INTO anomaly_sub_task (anomaly_id, sub_task_id)
SELECT DISTINCT anomaly_task.anomaly_id, sub_task.id
FROM anomaly_task
JOIN task ON task.id = anomaly_task.task_id
JOIN sub_task ON sub_task.task_id = task.id
WHERE task.status <> 'cancelled' AND sub_task.status <> 'cancelled'
```

`getDescription()` : « Liaison anomaly_sub_task et conversion des liens anomalie → tâche ». `down()` : `DROP TABLE anomaly_sub_task` (la table `anomaly_task` existe encore à ce stade).

- [ ] **Step 6 : migrer la base locale et vérifier**

Run: `symfony console doctrine:migrations:migrate -n && symfony console doctrine:schema:validate`
Expected: migration OK, `[OK] The mapping files are correct` et `[OK] The database schema is in sync`.
Run: `psql "$DATABASE_URL" -Atc "SELECT count(*) FROM anomaly_sub_task"` (URL de `.env.local` sans la query string)
Expected: un nombre > 0 si la base locale a des liens (12 lignes dans `anomaly_task` au 2026-10-13). Référence prod, même requête en lecture seule : 106 lignes pour 12 anomalies.

- [ ] **Step 7 : test du filtre par statut**

Créer `tests/Repository/AnomalyRepositoryTest.php` (`KernelTestCase`). Il persiste un club, un utilisateur, un matériel, une tâche `open` avec une sous-tâche `open`, et trois anomalies : A liée à la sous-tâche, B sans lien, C liée à une sous-tâche `closed` d'une tâche `cancelled`.

```php
public function testAcknowledgedAndReportedFollowTheActiveSubTasks(): void
{
    // … fixtures ci-dessus …
    $acknowledged = $this->idsFor('acknowledged', [$a, $b, $c]);
    $reported = $this->idsFor('reported', [$a, $b, $c]);

    $this->assertSame([$a->getId()], $acknowledged);
    $this->assertEqualsCanonicalizing([$b->getId(), $c->getId()], $reported);
}
```

`idsFor()` construit `createQueryBuilder('anomaly')->where('anomaly.id IN (:ids)')`, appelle `filterByStatus($qb, $status)` et renvoie les ids triés.

- [ ] **Step 8 : lancer, vérifier l'échec, puis passer `filterByStatus` sur les sous-tâches**

Run: `vendor/bin/phpunit tests/Repository/AnomalyRepositoryTest.php` → FAIL (A n'a aucune tâche, donc « reported »).
Réécrire la sous-requête : `createQueryBuilder('anomaly_with_subtask')->select('1')->join('anomaly_with_subtask.subTasks', 'linked_subtask')->join('linked_subtask.task', 'linked_subtask_task')`, conditions `linked_subtask.status != :cancelledStatus` et `linked_subtask_task.status != :cancelledStatus`. Mettre à jour le docblock (« sous-tâche » au lieu de « tâche »).
Run: `vendor/bin/phpunit` (suite complète) → PASS.

- [ ] **Step 9 : commit**

```bash
git add src/Entity/Anomaly.php src/Entity/SubTask.php src/Repository/AnomalyRepository.php migrations/ tests/Entity/AnomalyTest.php tests/Service/Maintenance/AnomalyStatusServiceTest.php tests/Repository/AnomalyRepositoryTest.php
git commit -m "feat(anomalies): lien anomalie ↔ sous-tâches et statut dérivé des sous-tâches"
```

---

### Task 2 : Rattachement côté service et journal d'activité

**Files:**
- Modify: `src/Entity/Enum/ActivityType.php`
- Modify: `src/Entity/Activity.php` (`getTypeLabel`, `getTypeIcon`, `getTypeColor`)
- Modify: `src/Service/Maintenance/AnomalyStatusService.php`
- Modify: `tests/Service/Maintenance/AnomalyStatusServiceTest.php`
- Modify: `translations/messages+intl-icu.fr.xlf`

**Interfaces:**
- Consumes: `Anomaly::addSubTask/removeSubTask/getSubTasks` (tâche 1)
- Produces:
  - `ActivityType::SUBTASK_LINKED = 'subtask_linked'`, `ActivityType::SUBTASK_UNLINKED = 'subtask_unlinked'`
  - `AnomalyStatusService::handleLinkSubTasks(Anomaly $anomaly, iterable $subTasks, User $user): int` : lie les sous-tâches pas encore liées, une paire d'entrées de journal par sous-tâche, un seul `flush()`, renvoie le nombre de liens créés
  - `AnomalyStatusService::handleUnlinkSubTask(Anomaly $anomaly, SubTask $subTask, User $user): void`

- [ ] **Step 1 : écrire les tests**

Ajouter un helper `createSubTask(string $title = 'Changer le pneu'): SubTask` (sous-tâche `open` rangée dans une tâche `open`) et `activityForSubTask(SubTask)` sur le modèle de `activityForTask`. Tests :

```php
public function testLinkSubTasksIsIdempotent(): void
{
    $service = $this->createService();
    $anomaly = $this->createAnomaly();
    $subTask = $this->createSubTask();

    $this->assertSame(1, $service->handleLinkSubTasks($anomaly, [$subTask], new User()));
    $this->assertSame(0, $service->handleLinkSubTasks($anomaly, [$subTask], new User()));

    $this->assertCount(1, $anomaly->getSubTasks());
    $this->assertCount(2, $this->persisted);
}

public function testLinkSubTasksIsLoggedOnBothSides(): void
{
    $service = $this->createService();
    $anomaly = $this->createAnomaly();
    $first = $this->createSubTask('Changer le pneu');
    $second = $this->createSubTask('Contrôler la jante');

    $service->handleLinkSubTasks($anomaly, [$first, $second], new User());

    $this->assertCount(4, $this->persisted);
    $onSubTask = $this->activityForSubTask($first);
    $this->assertSame(ActivityType::SUBTASK_LINKED, $onSubTask?->getType());
    $this->assertSame($anomaly->getTitle(), $onSubTask->getMessage());
    // L'entrée miroir porte aussi la tâche, comme les autres entrées de sous-tâche
    $this->assertSame($first->getTask(), $onSubTask->getTask());
    $messagesOnAnomaly = array_map(
        static fn (Activity $activity) => $activity->getMessage(),
        array_filter($this->persisted, static fn (Activity $activity) => $activity->getAnomaly() === $anomaly)
    );
    $this->assertEqualsCanonicalizing(['Changer le pneu', 'Contrôler la jante'], array_values($messagesOnAnomaly));
}

public function testUnlinkSubTaskDetachesAndLogsBothSides(): void
{
    $service = $this->createService();
    $anomaly = $this->createAnomaly();
    $subTask = $this->createSubTask();
    $anomaly->addSubTask($subTask);

    $service->handleUnlinkSubTask($anomaly, $subTask, new User());

    $this->assertCount(0, $anomaly->getSubTasks());
    $this->assertCount(0, $subTask->getAnomalies());
    $this->assertSame(ActivityType::SUBTASK_UNLINKED, $this->activityFor($anomaly)?->getType());
    $this->assertSame(ActivityType::SUBTASK_UNLINKED, $this->activityForSubTask($subTask)?->getType());
}
```

Vérifier aussi, dans `testLinkSubTasksIsLoggedOnBothSides`, que `(new Activity())->setAnomaly($anomaly)->setType(ActivityType::SUBTASK_LINKED)->getTypeLabel()` vaut `anomalySubTaskLinked`, et qu'une entrée de sous-tâche vaut `subTaskLinkedToAnomaly`.

- [ ] **Step 2 : lancer, vérifier l'échec**

Run: `vendor/bin/phpunit tests/Service/Maintenance/AnomalyStatusServiceTest.php`
Expected: FAIL, méthode `handleLinkSubTasks` inexistante.

- [ ] **Step 3 : implémenter**

- `ActivityType` : les deux nouveaux cas.
- `Activity::getTypeLabel()` : branche anomalie `SUBTASK_LINKED => 'anomalySubTaskLinked'`, `SUBTASK_UNLINKED => 'anomalySubTaskUnlinked'` ; branche par défaut `SUBTASK_LINKED => 'subTaskLinkedToAnomaly'`, `SUBTASK_UNLINKED => 'subTaskUnlinkedFromAnomaly'`. Icônes `ti-link` / `ti-unlink`, couleurs `info` / `secondary`, comme `TASK_LINKED` / `TASK_UNLINKED`. Les cas `TASK_*` restent pour l'historique.
- `AnomalyStatusService` : les deux méthodes ; l'entrée miroir appelle `setTask($subTask->getTask())` et `setSubTask($subTask)`.

- [ ] **Step 4 : traductions (ids 1903+)**

| Clé | Texte |
|---|---|
| `anomalySubTaskLinked` | a rattaché une sous-tâche |
| `anomalySubTaskUnlinked` | a détaché une sous-tâche |
| `subTaskLinkedToAnomaly` | a rattaché cette sous-tâche à une anomalie |
| `subTaskUnlinkedFromAnomaly` | a détaché cette sous-tâche d'une anomalie |

- [ ] **Step 5 : lancer, vérifier le succès**

Run: `vendor/bin/phpunit tests/Service/Maintenance/AnomalyStatusServiceTest.php && symfony console lint:xliff translations/`
Expected: PASS, `[OK] All 1 XLIFF files contain valid syntax`.

- [ ] **Step 6 : commit**

```bash
git add src/Entity/Enum/ActivityType.php src/Entity/Activity.php src/Service/Maintenance/AnomalyStatusService.php tests/Service/Maintenance/AnomalyStatusServiceTest.php translations/messages+intl-icu.fr.xlf
git commit -m "feat(anomalies): rattacher et détacher des sous-tâches, tracé dans les deux journaux"
```

---

### Task 3 : Parcours « Lier des sous-tâches » (écrans 1 et 2, détacher)

**Files:**
- Modify: `src/Controller/Club/AnomalyController.php` (3 actions)
- Create: `templates/club/anomaly/linkTask.html.twig` (écran 1)
- Create: `templates/club/anomaly/linkSubTasks.html.twig` (écran 2)
- Modify: `templates/club/anomaly/show.html.twig` (en-tête et bloc « Sous-tâches liées »)
- Modify: `templates/component/taskCard.html.twig` (paramètre `href`)
- Rename + modify: `assets/controllers/user_list_filter_controller.js` → `assets/controllers/checklist_filter_controller.js`
- Modify: `translations/messages+intl-icu.fr.xlf`
- Create: `tests/Functional/AnomalySubTaskLinkTest.php`

**Interfaces:**
- Consumes: `handleLinkSubTasks`, `handleUnlinkSubTask` (tâche 2), `AnomalyVoter::LINK_TASK` (inchangé, gestionnaire et anomalie non résolue)
- Produces (routes, préfixe de classe `/anomalies`) :
  - `GET /{id}/link` → `club_anomaly_link` (écran 1)
  - `GET|POST /{id}/link/{taskId}` → `club_anomaly_link_subtasks` (écran 2) ; champ POST `subTaskIds[]`, jeton CSRF `anomaly_link_subtasks_{anomalyId}` dans `_token`
  - `POST /{id}/subtasks/{subTaskId}/unlink` → `club_anomaly_unlink_subtask` ; jeton `anomaly_unlink_subtask_{anomalyId}`
  - `taskCard.html.twig` accepte `href` (défaut : `path('club_task_show', …)`)

- [ ] **Step 1 : écrire le test fonctionnel**

`tests/Functional/AnomalySubTaskLinkTest.php` étend `FunctionalTestCase`. Un helper `scenario()` persiste : club `beauce` avec un gestionnaire, matériel planeur `F-CJKL`, tâche `open` « Visite annuelle 2026 » avec trois sous-tâches (`open` « Contrôle des pneus », `open` « Graissage roulette », `cancelled` « Ancienne étape »), une tâche `closed` « Visite 2025 » avec une sous-tâche `closed` « Pesée », une anomalie « Pneu usé » sur `F-CJKL`, et un second matériel `F-CXYZ` avec sa tâche `open` et une sous-tâche `open`. Le client se connecte en gestionnaire, hôte `https://beauce.<domain>`.

```php
public function testManagerLinksTwoSubTasksFromScreenTwo(): void
{
    $s = $this->scenario();
    $crawler = $this->client->request('GET', $s->host . '/anomalies/' . $s->anomaly->getId() . '/link');
    $this->assertResponseIsSuccessful();
    $this->assertSelectorTextContains('body', 'Visite annuelle 2026');
    $this->assertSelectorTextContains('details', 'Visite 2025'); // tâches closes repliées

    $crawler = $this->client->request('GET', $s->host . '/anomalies/' . $s->anomaly->getId() . '/link/' . $s->visit->getId());
    $this->assertSelectorTextNotContains('body', 'Ancienne étape');
    $form = $crawler->filter('form[data-controller~="checklist-filter"]')->form();
    $form['subTaskIds'][0]->tick();
    $form['subTaskIds'][1]->tick();
    $this->client->submit($form);

    $this->assertResponseRedirects('/anomalies/' . $s->anomaly->getId());
    $this->entityManager()->refresh($s->anomaly);
    $this->assertCount(2, $s->anomaly->getSubTasks());
}

public function testForeignSubTaskIdsAreIgnored(): void
{
    $s = $this->scenario();
    $this->client->request('POST', $s->host . '/anomalies/' . $s->anomaly->getId() . '/link/' . $s->visit->getId(), [
        '_token' => $this->csrfToken('anomaly_link_subtasks_' . $s->anomaly->getId()),
        'subTaskIds' => [$s->otherEquipmentSubTask->getId(), $s->cancelledSubTask->getId()],
    ]);

    $this->entityManager()->refresh($s->anomaly);
    $this->assertCount(0, $s->anomaly->getSubTasks());
}

public function testTaskOfAnotherEquipmentIsRefused(): void
{
    $s = $this->scenario();
    $this->client->request('GET', $s->host . '/anomalies/' . $s->anomaly->getId() . '/link/' . $s->otherEquipmentTask->getId());
    $this->assertResponseRedirects('/anomalies/' . $s->anomaly->getId());
}

public function testResolvedAnomalyCannotBeLinked(): void
{
    $s = $this->scenario();
    $s->anomaly->setResolution(AnomalyResolution::IGNORED);
    $this->entityManager()->flush();

    $this->client->request('POST', $s->host . '/anomalies/' . $s->anomaly->getId() . '/link/' . $s->visit->getId(), [
        '_token' => $this->csrfToken('anomaly_link_subtasks_' . $s->anomaly->getId()),
        'subTaskIds' => [$s->tyreSubTask->getId()],
    ]);

    $this->assertResponseStatusCodeSame(403);
    $this->entityManager()->refresh($s->anomaly);
    $this->assertCount(0, $s->anomaly->getSubTasks());
}

public function testManagerUnlinksASubTask(): void
{
    $s = $this->scenario();
    $s->anomaly->addSubTask($s->tyreSubTask);
    $this->entityManager()->flush();

    $this->client->request('POST', $s->host . '/anomalies/' . $s->anomaly->getId() . '/subtasks/' . $s->tyreSubTask->getId() . '/unlink', [
        '_token' => $this->csrfToken('anomaly_unlink_subtask_' . $s->anomaly->getId()),
    ]);

    $this->assertResponseRedirects('/anomalies/' . $s->anomaly->getId());
    $this->entityManager()->refresh($s->anomaly);
    $this->assertCount(0, $s->anomaly->getSubTasks());
}
```

Jetons CSRF : pas d'helper `csrfToken()`. Suivre le motif de `tests/Functional/JoinRequestTest.php:132-134` : un GET de la page qui porte le formulaire (écran 2, ou fiche de l'anomalie pour le détachement), lecture de `input[name="_token"]` dans le crawler, puis le POST. Dans `testResolvedAnomalyCannotBeLinked`, lire le jeton sur l'écran 2 **avant** de passer l'anomalie à `IGNORED`. Les extraits ci-dessus écrivent `$this->csrfToken(…)` pour la lisibilité : remplacer par la valeur lue.

- [ ] **Step 2 : lancer, vérifier l'échec**

Run: `vendor/bin/phpunit tests/Functional/AnomalySubTaskLinkTest.php`
Expected: FAIL, 404 sur `/anomalies/{id}/link`.

- [ ] **Step 3 : contrôleur**

Les trois actions portent `#[IsGranted(AnomalyVoter::LINK_TASK, 'anomaly')]`.
- `linkTask(Anomaly $anomaly)` : tâches du matériel de l'anomalie (alias `task`), deux listes : `open`/`done` puis `closed`, chacune par `createdAt` décroissant. Breadcrumb : home › anomalies › `$anomaly.title` (lien show) › `anomalyLinkSubTasks`.
- `linkSubTasks(Anomaly $anomaly, int $taskId, Request $request)` : tâche introuvable, annulée ou d'un autre matériel → flash `anomalyTaskEquipmentMismatch`, redirection show. En POST : CSRF invalide → flash `invalidRequest` ; ne retenir que les ids parmi les sous-tâches non annulées de cette tâche ; aucune retenue → flash `anomalyLinkNoSubTaskSelected` et retour sur l'écran 2 ; sinon `handleLinkSubTasks` et flash `anomalySubTasksLinkedFlash` (paramètre `count`). Breadcrumb : … › `anomalyLinkSubTasks` (lien écran 1) › titre de la tâche.
- `unlinkSubTask(Anomaly $anomaly, int $subTaskId, Request $request)` : même structure que l'actuel `unlinkTask`, sur `getSubTasks()`, flash `anomalySubTaskUnlinkedFlash`.

- [ ] **Step 4 : templates**

- `taskCard.html.twig` : `href="{{ href ?? path('club_task_show', {id: task.id}) }}"`, documenter le paramètre dans l'en-tête.
- Écran 1 : `taskCard` avec `href: path('club_anomaly_link_subtasks', …)` pour les tâches en cours, puis une carte en pointillés « Nouvelle tâche dédiée » vers `club_task_new` avec `anomaly`, puis un `<details>` natif dont le `<summary>` affiche `anomalyLinkShowClosedTasks` et qui contient les cartes des tâches closes.
- Écran 2 : formulaire `data-controller="checklist-filter"`. En tête, si `is_granted('TASK_CREATE_SUBTASK', task)`, un lien en pointillés `anomalyLinkCreateSubTask` vers `club_subtask_new` avec `taskId` et `anomaly`. Champ de filtre (target `search`, `input->checklist-filter#filter`). Groupes `anomalyLinkGroupTodo` (`open`, `done`) et `anomalyLinkGroupClosed` (`closed`). Chaque ligne est un `<label>` (target `item`) de 44 px minimum avec `<input type="checkbox" name="subTaskIds[]">`, titre et badge de statut ; une sous-tâche déjà liée s'affiche sans case, avec une icône `ti-check` et `anomalyLinkAlreadyLinked`. Bouton `anomalyLinkSubmit` (target `submit`) avec un compteur (target `count`). Pied de panneau `.tarmac-panel__footer` : action principale à droite, retour vers l'écran 1 à gauche.
- `show.html.twig` : le bouton d'en-tête et le bloc deviennent `anomalyLinkSubTasks` vers `club_anomaly_link` ; le bloc « Sous-tâches liées » (`anomalyLinkedSubTasks`) liste chaque sous-tâche (titre en lien vers `club_subtask_show`, titre de la tâche en dessous, badge de statut, bouton détacher `anomalyUnlinkSubTask` avec `data-confirm="{{ 'anomalyUnlinkSubTaskConfirm'|trans }}"`), ou `anomalyNoLinkedSubTask`. Le formulaire `linkTaskForm` sort de la page ; ne plus le passer depuis `show()`.

- [ ] **Step 5 : contrôleur Stimulus**

`git mv assets/controllers/user_list_filter_controller.js assets/controllers/checklist_filter_controller.js` (aucune référence dans le projet au 2026-10-13). Supprimer la valeur `type` et la synchronisation `doneBy`. Garder `filter()` et `updateCount()`, ajouter la cible optionnelle `submit` : `updateCount()` met `this.submitTarget.disabled = checked === 0`. Brancher `change->checklist-filter#updateCount` sur le formulaire.

- [ ] **Step 6 : traductions (ids suivants)**

| Clé | Texte |
|---|---|
| `anomalyLinkSubTasks` | Lier des sous-tâches |
| `anomalyLinkedSubTasks` | Sous-tâches liées |
| `anomalyNoLinkedSubTask` | Aucune sous-tâche ne répare encore cette anomalie. |
| `anomalyLinkChooseTask` | Dans quelle tâche ? |
| `anomalyLinkShowClosedTasks` | Afficher les tâches closes ({count}) |
| `anomalyLinkNewDedicatedTask` | Nouvelle tâche dédiée |
| `anomalyLinkNewDedicatedTaskHelp` | Une tâche à part, pré-remplie avec l'anomalie. |
| `anomalyLinkChooseSubTasks` | Quelles sous-tâches réparent l'anomalie ? |
| `anomalyLinkCreateSubTask` | Créer une sous-tâche dans cette tâche |
| `anomalyLinkFilterPlaceholder` | Filtrer les sous-tâches… |
| `anomalyLinkGroupTodo` | À faire |
| `anomalyLinkGroupClosed` | Déjà closes |
| `anomalyLinkAlreadyLinked` | Déjà liée |
| `anomalyLinkSubmit` | Lier la sélection |
| `anomalyLinkNoSubTaskSelected` | Cochez au moins une sous-tâche. |
| `anomalySubTasksLinkedFlash` | {count, plural, one {# sous-tâche rattachée à l'anomalie.} other {# sous-tâches rattachées à l'anomalie.}} |
| `anomalySubTaskUnlinkedFlash` | La sous-tâche a été détachée de l'anomalie. |
| `anomalyUnlinkSubTask` | Détacher la sous-tâche |
| `anomalyUnlinkSubTaskConfirm` | Détacher cette sous-tâche de l'anomalie ? |

- [ ] **Step 7 : lancer, vérifier le succès**

Run: `vendor/bin/phpunit tests/Functional/AnomalySubTaskLinkTest.php && symfony console lint:xliff translations/ && symfony console debug:translation fr --domain=messages --only-missing`
Expected: PASS, XLIFF valide, aucune clé manquante parmi les nouvelles.

- [ ] **Step 8 : commit**

```bash
git add src/Controller/Club/AnomalyController.php templates/club/anomaly/ templates/component/taskCard.html.twig assets/controllers/ translations/messages+intl-icu.fr.xlf tests/Functional/AnomalySubTaskLinkTest.php
git commit -m "feat(anomalies): parcours « Lier des sous-tâches » en deux écrans"
```

---

### Task 4 : Créer une sous-tâche ou une tâche dédiée depuis l'anomalie

**Files:**
- Modify: `src/Controller/Club/SubTaskController.php` (`new`)
- Modify: `templates/club/task/subtask/new.html.twig`
- Modify: `src/Controller/Club/TaskController.php:94-210` (`new`, `resolveRequestedAnomaly`)
- Modify: `translations/messages+intl-icu.fr.xlf`
- Modify: `tests/Functional/AnomalySubTaskLinkTest.php`

**Interfaces:**
- Consumes: `handleLinkSubTasks` (tâche 2), route `club_subtask_new` avec `?anomaly={id}` (lien posé à la tâche 3)

- [ ] **Step 1 : écrire les tests**

```php
public function testSubTaskCreatedFromTheAnomalyIsPrefilledAndLinked(): void
{
    $s = $this->scenario();
    $crawler = $this->client->request('GET', $s->host . '/tasks/' . $s->visit->getId() . '/subtasks/new?anomaly=' . $s->anomaly->getId());
    $form = $crawler->selectButton('Enregistrer')->form();
    $this->assertSame('Pneu usé', $form['sub_task[title]']->getValue());

    $this->client->submit($form);

    $this->assertResponseRedirects('/anomalies/' . $s->anomaly->getId());
    $this->entityManager()->refresh($s->anomaly);
    $this->assertCount(1, $s->anomaly->getSubTasks());
    $this->assertSame('Pneu usé', $s->anomaly->getSubTasks()->first()->getTitle());
}

public function testEverySubTaskOfADedicatedTaskIsLinked(): void
{
    $s = $this->scenario();
    $crawler = $this->client->request('GET', $s->host . '/tasks/new?anomaly=' . $s->anomaly->getId());
    $form = $crawler->selectButton('Enregistrer')->form();
    $values = $form->getPhpValues();
    $values['task']['subTasks'][0]['title'] = 'Déposer la roulette';
    $values['task']['subTasks'][1] = $values['task']['subTasks'][0];
    $values['task']['subTasks'][1]['title'] = 'Monter le pneu neuf';
    $this->client->request('POST', $form->getUri(), $values);

    $this->assertResponseRedirects('/anomalies/' . $s->anomaly->getId());
    $this->entityManager()->refresh($s->anomaly);
    $this->assertCount(2, $s->anomaly->getSubTasks());
}
```

Adapter les noms de champs (`sub_task[...]`, `task[subTasks][...]`) et le libellé du bouton au rendu réel des formulaires.

- [ ] **Step 2 : lancer, vérifier l'échec**

Run: `vendor/bin/phpunit --filter 'SubTaskCreatedFromTheAnomaly|DedicatedTask' tests/Functional/AnomalySubTaskLinkTest.php`
Expected: FAIL (titre vide, puis 0 sous-tâche liée).

- [ ] **Step 3 : implémenter**

- `SubTaskController::new` : résoudre `?anomaly=` comme `TaskController::resolveRequestedAnomaly`, avec en plus `anomaly.equipment === task.equipment` (sinon null). Si résolue et requête GET : titre et description de la sous-tâche pris dans l'anomalie. Après l'enregistrement : `handleLinkSubTasks($anomaly, [$subTask], $user)`, redirection vers `club_anomaly_show`. Passer `anomaly` au template. Le formulaire garde l'action courante (query string comprise) pour que le POST conserve `?anomaly=`.
- `subtask/new.html.twig` : si `anomaly`, l'en-tête affiche un lien vers l'anomalie (`ti-alert-triangle` + titre, comme `club/task/new.html.twig`) et une note `subTaskWillBeLinkedToAnomaly` ; le bouton « Annuler » ramène à l'anomalie.
- `TaskController::new` : remplacer `handleLinkTask($anomaly, $task, …)` par `handleLinkSubTasks($anomaly, $task->getSubTasks()->toArray(), …)`.

- [ ] **Step 4 : traductions**

| Clé | Texte |
|---|---|
| `subTaskWillBeLinkedToAnomaly` | Cette sous-tâche sera rattachée à l'anomalie. |

Modifier la cible de `taskWillBeLinkedToAnomaly` : « Cette tâche portera sur {equipment}, et toutes ses sous-tâches seront rattachées à l'anomalie. »

- [ ] **Step 5 : lancer, vérifier le succès**

Run: `vendor/bin/phpunit tests/Functional/AnomalySubTaskLinkTest.php && symfony console lint:xliff translations/`
Expected: PASS.

- [ ] **Step 6 : commit**

```bash
git add src/Controller/Club/SubTaskController.php src/Controller/Club/TaskController.php templates/club/task/subtask/new.html.twig translations/messages+intl-icu.fr.xlf tests/Functional/AnomalySubTaskLinkTest.php
git commit -m "feat(anomalies): créer une sous-tâche ou une tâche dédiée liée à l'anomalie"
```

---

### Task 5 : Retrait du lien aux tâches et affichages dérivés

**Files:**
- Modify: `src/Entity/Anomaly.php` (retrait `$tasks`, `addTask`, `removeTask`, `getTasks`, `getActiveTasks`)
- Modify: `src/Entity/Task.php:92-105, 509-530` (retrait du mapping `$anomalies` ; `getAnomalies()` dérivée)
- Modify: `src/Service/Maintenance/AnomalyStatusService.php` (retrait `handleLinkTask`, `handleUnlinkTask`, `logOnTask`)
- Modify: `src/Controller/Club/AnomalyController.php` (retrait `linkTask`, `unlinkTask`, import `AnomalyLinkTaskType`)
- Delete: `src/Form/AnomalyLinkTaskType.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (suppression `anomaly_task`)
- Create: `templates/component/anomalyLinks.html.twig`
- Modify: `templates/club/task/show.html.twig:786-830`, `templates/club/task/subtask/show.html.twig` (avant le bloc journal, ligne ~193), `templates/component/anomalyCard.html.twig:82-84`
- Modify: `tests/Service/Maintenance/AnomalyStatusServiceTest.php`, `tests/Entity/AnomalyTest.php`
- Modify: `translations/messages+intl-icu.fr.xlf`

**Interfaces:**
- Produces: `Task::getAnomalies(): Collection<int, Anomaly>`, sans doublon, union des `SubTask::getAnomalies()` de ses sous-tâches. `component/anomalyLinks.html.twig` prend `anomalies` et rend le panneau « Anomalies » actuel de la fiche tâche (titre, badge de statut, badge d'impact si `isGrounding`, bouton vers l'anomalie), rien si la liste est vide.

- [ ] **Step 1 : test de `Task::getAnomalies()`**

Dans `tests/Entity/AnomalyTest.php` :

```php
public function testTaskListsTheAnomaliesOfItsSubTasksOnce(): void
{
    $task = new Task();
    $first = new SubTask();
    $second = new SubTask();
    $task->addSubTask($first);
    $task->addSubTask($second);
    $anomaly = new Anomaly();
    $anomaly->addSubTask($first);
    $anomaly->addSubTask($second);

    $this->assertCount(1, $task->getAnomalies());
}
```

- [ ] **Step 2 : lancer, vérifier l'échec**

Run: `vendor/bin/phpunit --filter testTaskListsTheAnomaliesOfItsSubTasksOnce tests/Entity/AnomalyTest.php`
Expected: FAIL (0 anomalie : l'ancien mapping est encore lu).

- [ ] **Step 3 : retirer l'ancien lien**

Retirer tout ce que liste **Files**. Supprimer de `AnomalyStatusServiceTest` les tests `testLinkTask*` / `testUnlinkTask*` et le helper `activityForTask` s'il ne sert plus. Vérifier qu'aucune référence ne subsiste :
Run: `grep -rnE "getTasks\(\)|addTask\(|getActiveTasks|handleLinkTask|handleUnlinkTask|AnomalyLinkTaskType|linkTaskForm|club_anomaly_(un)?link_task" src templates tests`
Expected: aucune ligne qui concerne les anomalies (`getTasks()` d'autres entités, `PlanApplication` par exemple, peut rester).

- [ ] **Step 4 : migration de suppression**

Run: `symfony console doctrine:migrations:diff` → la migration supprime `anomaly_task`. Compléter `down()` : recréer `anomaly_task` (structure de `Version20260727171451`) puis `INSERT INTO anomaly_task (anomaly_id, task_id) SELECT DISTINCT anomaly_sub_task.anomaly_id, sub_task.task_id FROM anomaly_sub_task JOIN sub_task ON sub_task.id = anomaly_sub_task.sub_task_id`.
Run: `symfony console doctrine:migrations:migrate -n && symfony console doctrine:schema:validate`
Expected: OK, schéma synchronisé.

- [ ] **Step 5 : affichages**

- Extraire le panneau « Anomalies » de `task/show.html.twig` dans `component/anomalyLinks.html.twig`, l'inclure dans `task/show.html.twig` (`task.anomalies`) et dans `subtask/show.html.twig` (`subTask.anomalies`), juste avant le panneau d'activité.
- `anomalyCard.html.twig` : `anomaly.subTasks|length` et la clé `anomalyLinkedSubTasksCount`.

- [ ] **Step 6 : traductions**

Ajouter `anomalyLinkedSubTasksCount` : `{count, plural, one {# sous-tâche} other {# sous-tâches}}`.
Supprimer, après un `grep -rn "<clé>" src templates` vide pour chacune : `anomalyLinkedTasks`, `anomalyLinkedTasksCount`, `anomalyNoLinkedTask`, `anomalyCreateTask`, `anomalyLinkTask`, `anomalyLinkTaskPlaceholder`, `anomalyLinkExistingTask`, `anomalyUnlinkTask`, `anomalyUnlinkTaskConfirm`, `anomalyTaskRequired`, `anomalyTaskLinkedFlash`, `anomalyTaskUnlinkedFlash`. Garder `anomalyTaskLinked`, `anomalyTaskUnlinked`, `taskLinkedToAnomaly`, `taskUnlinkedFromAnomaly` (historique du journal) et `anomalyTaskEquipmentMismatch` (tâche 3).

- [ ] **Step 7 : suite complète**

Run: `vendor/bin/phpunit && symfony console lint:xliff translations/ && symfony console lint:twig templates/ && symfony console lint:container`
Expected: tout vert.

- [ ] **Step 8 : commit**

```bash
git add -A src templates migrations tests translations
git commit -m "refactor(anomalies): retirer le lien aux tâches, anomalies affichées sur les sous-tâches"
```

---

### Task 6 : Guide utilisateur et recette

**Files:**
- Modify: `templates/public/guide/index.html.twig:456-495` (section 9, « Le cycle de vie » et « Faire le lien avec les tâches »)

- [ ] **Step 1 : réécrire la section**

Avant d'écrire, invoquer le skill `anthropic-skills:stop-slop`. Ton du guide : un président de club qui explique à ses membres, glossaire du projet.
- « Le cycle de vie » : le statut se déduit des **sous-tâches** rattachées ; « Pris en compte » dès qu'une sous-tâche est rattachée ; « Marquer comme traité » s'active quand toutes les sous-tâches liées sont closes, inspection comprise. Une sous-tâche ou une tâche annulée ne compte plus.
- « Faire le lien avec les tâches » devient « Faire le lien avec les sous-tâches » : le bouton « Lier des sous-tâches », l'écran « Dans quelle tâche ? » (tâches closes repliées), les cases à cocher et le filtre, « Créer une sous-tâche dans cette tâche », « Nouvelle tâche dédiée » (toutes ses sous-tâches rattachées). Exemple concret : le pneu usé réparé pendant la visite annuelle, rattaché à la seule étape qui le répare. Le lien se voit sur la fiche de la sous-tâche et sur celle de sa tâche, et chaque rattachement s'inscrit dans les deux journaux.

- [ ] **Step 2 : vérifier**

Run: `symfony console lint:twig templates/public/guide/index.html.twig && grep -n "rattacher une tâche existante\|créer une tâche</strong>" templates/public/guide/index.html.twig`
Expected: lint OK, grep vide.

- [ ] **Step 3 : recette manuelle**

Sur `https://demo.tarmac.wip` (Chrome de l'utilisateur, le navigateur intégré bloque `/assets`) : anomalie « Soucis d'anémométrie », vérifier les liens convertis, l'écran 1 (tâches closes repliées), l'écran 2 (filtre, compteur, bouton inactif à zéro), la création d'une sous-tâche, le détachement, la fiche sous-tâche, à 320 px puis en desktop.

- [ ] **Step 4 : commit**

```bash
git add templates/public/guide/index.html.twig
git commit -m "docs(guide): anomalies rattachées aux sous-tâches"
```

Après la recette : l'utilisateur décide du push. Après déploiement, contrôle en lecture seule : `heroku pg:psql -a tarmac -c "SELECT count(*), count(DISTINCT anomaly_id) FROM anomaly_sub_task"` → 106 lignes, 12 anomalies (plus les liens créés entre-temps).
