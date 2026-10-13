<?php

namespace App\Tests\Functional;

use App\Entity\Anomaly;
use App\Entity\Club;
use App\Entity\Contribution;
use App\Entity\Enum\AnomalyResolution;
use App\Entity\Enum\EquipmentOwner;
use App\Entity\Enum\EquipmentType;
use App\Entity\Equipment;
use App\Entity\Membership;
use App\Entity\SubTask;
use App\Entity\Task;
use App\Entity\User;

class AnomalySubTaskLinkTest extends FunctionalTestCase
{
    private string $host;
    private User $manager;
    private Club $club;
    private Anomaly $anomaly;
    private Task $visit;
    private SubTask $tyreSubTask;
    private SubTask $cancelledSubTask;
    private Task $otherEquipmentTask;
    private SubTask $otherEquipmentSubTask;

    /**
     * Matériel F-CJKL : une visite en cours (deux sous-tâches ouvertes, une annulée),
     * une visite close, et l'anomalie « Pneu usé ». Un second matériel porte sa propre tâche.
     */
    private function scenario(): void
    {
        $this->manager = $this->createUser('manager@example.test');
        $this->club = $this->createClub('Planeurs de Beauce', 'beauce');
        // Des deux côtés : le voter lit les adhésions de l'utilisateur, déjà en mémoire
        // Pilote aussi : créer une tâche sur un planeur l'exige
        $membership = (new Membership())->setIsManager(true)->setIsPilote(true);
        $this->club->addMembership($membership);
        $this->manager->addMembership($membership);

        $glider = $this->createEquipment('F-CJKL');
        $this->visit = $this->createTask($glider, 'Visite annuelle 2026', 'open');
        $this->tyreSubTask = $this->createSubTask($this->visit, 'Contrôle des pneus', 'open');
        $this->createSubTask($this->visit, 'Graissage roulette', 'open');
        $this->cancelledSubTask = $this->createSubTask($this->visit, 'Ancienne étape', 'cancelled');

        $closedVisit = $this->createTask($glider, 'Visite 2025', 'closed');
        $this->createSubTask($closedVisit, 'Pesée', 'closed');

        $other = $this->createEquipment('F-CXYZ');
        $this->otherEquipmentTask = $this->createTask($other, 'Visite F-CXYZ', 'open');
        $this->otherEquipmentSubTask = $this->createSubTask($this->otherEquipmentTask, 'Contrôle F-CXYZ', 'open');

        $this->anomaly = new Anomaly();
        $this->anomaly->setClub($this->club);
        $this->anomaly->setEquipment($glider);
        $this->anomaly->setTitle('Pneu usé');
        $this->anomaly->setDescription('Le pneu de la roulette de queue montre la toile.');
        $this->anomaly->setReportedBy($this->manager);
        $this->entityManager()->persist($this->anomaly);

        $this->entityManager()->flush();

        $this->client->loginUser($this->manager);
        $this->host = 'https://beauce.' . static::getContainer()->getParameter('domain');
    }

    private function createEquipment(string $name): Equipment
    {
        $equipment = (new Equipment())
            ->setName($name)
            ->setType(EquipmentType::GLIDER)
            ->setOwner(EquipmentOwner::CLUB)
            ->setClub($this->club)
            ->setCreatedBy($this->manager);
        $this->entityManager()->persist($equipment);

        return $equipment;
    }

    private function createTask(Equipment $equipment, string $title, string $status): Task
    {
        $task = new Task();
        $task->setClub($this->club);
        $task->setEquipment($equipment);
        $task->setTitle($title);
        $task->setStatus($status);
        $task->setCreatedBy($this->manager);
        $this->entityManager()->persist($task);

        return $task;
    }

    private function createSubTask(Task $task, string $title, string $status): SubTask
    {
        $subTask = new SubTask();
        $subTask->setTitle($title);
        $subTask->setStatus($status);
        $subTask->setPosition($task->getSubTasks()->count() + 1);
        $task->addSubTask($subTask);
        $this->entityManager()->persist($subTask);

        return $subTask;
    }

    private function anomalyUrl(string $suffix = ''): string
    {
        return $this->host . '/anomalies/' . $this->anomaly->getId() . $suffix;
    }

    /**
     * Relu depuis la base : le client redémarre le noyau entre deux requêtes,
     * l'entité du scénario n'appartient plus au gestionnaire d'entités courant.
     *
     * @return string[]
     */
    private function linkedSubTaskTitles(): array
    {
        $anomaly = $this->entityManager()->getRepository(Anomaly::class)->find($this->anomaly->getId());
        $titles = $anomaly->getSubTasks()->map(static fn (SubTask $subTask) => $subTask->getTitle())->toArray();
        sort($titles);

        return $titles;
    }

    private function linkFormToken(): string
    {
        $crawler = $this->client->request('GET', $this->anomalyUrl('/link/' . $this->visit->getId()));

        return $crawler->filter('[data-controller~="checklist-filter"] form input[name="_token"]')->attr('value');
    }

    public function testManagerLinksTwoSubTasksFromScreenTwo(): void
    {
        $this->scenario();

        $this->client->request('GET', $this->anomalyUrl('/link'));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Visite annuelle 2026');
        // Tâches closes repliées
        $this->assertSelectorTextContains('details', 'Visite 2025');

        $crawler = $this->client->request('GET', $this->anomalyUrl('/link/' . $this->visit->getId()));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('body', 'Ancienne étape');

        $form = $crawler->filter('[data-controller~="checklist-filter"] form')->form();
        $form['subTaskIds'][0]->tick();
        $form['subTaskIds'][1]->tick();
        $this->client->submit($form);

        $this->assertResponseRedirects('/anomalies/' . $this->anomaly->getId());
        $this->assertSame(['Contrôle des pneus', 'Graissage roulette'], $this->linkedSubTaskTitles());
    }

    public function testForeignSubTaskIdsAreIgnored(): void
    {
        $this->scenario();

        $this->client->request('POST', $this->anomalyUrl('/link/' . $this->visit->getId()), [
            '_token' => $this->linkFormToken(),
            'subTaskIds' => [$this->otherEquipmentSubTask->getId(), $this->cancelledSubTask->getId()],
        ]);

        $this->assertSame([], $this->linkedSubTaskTitles());
    }

    public function testTaskOfAnotherEquipmentIsRefused(): void
    {
        $this->scenario();

        $this->client->request('GET', $this->anomalyUrl('/link/' . $this->otherEquipmentTask->getId()));

        $this->assertResponseRedirects('/anomalies/' . $this->anomaly->getId());
    }

    public function testResolvedAnomalyCannotBeLinked(): void
    {
        $this->scenario();
        $token = $this->linkFormToken();

        // Un autre gestionnaire ignore l'anomalie pendant que l'écran 2 est ouvert
        $anomaly = $this->entityManager()->getRepository(Anomaly::class)->find($this->anomaly->getId());
        $anomaly->setResolution(AnomalyResolution::IGNORED);
        $this->entityManager()->flush();

        $this->client->request('POST', $this->anomalyUrl('/link/' . $this->visit->getId()), [
            '_token' => $token,
            'subTaskIds' => [$this->tyreSubTask->getId()],
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertSame([], $this->linkedSubTaskTitles());
    }

    public function testManagerUnlinksASubTask(): void
    {
        $this->scenario();
        $this->anomaly->addSubTask($this->tyreSubTask);
        $this->entityManager()->flush();

        $crawler = $this->client->request('GET', $this->anomalyUrl());
        $token = $crawler->filter('form[action$="/subtasks/' . $this->tyreSubTask->getId() . '/unlink"] input[name="_token"]')->attr('value');
        $this->client->request('POST', $this->anomalyUrl('/subtasks/' . $this->tyreSubTask->getId() . '/unlink'), ['_token' => $token]);

        $this->assertResponseRedirects('/anomalies/' . $this->anomaly->getId());
        $this->assertSame([], $this->linkedSubTaskTitles());
    }

    public function testSubTaskCreatedFromTheAnomalyIsPrefilledAndLinked(): void
    {
        $this->scenario();

        $crawler = $this->client->request('GET', $this->host . '/tasks/' . $this->visit->getId() . '/subtasks/new?anomaly=' . $this->anomaly->getId());
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form();
        $this->assertSame('Pneu usé', $form['sub_task[title]']->getValue());

        $this->client->submit($form);

        $this->assertResponseRedirects('/anomalies/' . $this->anomaly->getId());
        $this->assertSame(['Pneu usé'], $this->linkedSubTaskTitles());
    }

    public function testEverySubTaskOfADedicatedTaskIsLinked(): void
    {
        $this->scenario();

        $crawler = $this->client->request('GET', $this->host . '/tasks/new?anomaly=' . $this->anomaly->getId());
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer')->form();
        $values = $form->getPhpValues();
        $values['task']['subTasks'][0]['title'] = 'Déposer la roulette';
        $values['task']['subTasks'][1] = $values['task']['subTasks'][0];
        $values['task']['subTasks'][1]['title'] = 'Monter le pneu neuf';
        $this->client->request('POST', $form->getUri(), $values);

        $this->assertResponseRedirects('/anomalies/' . $this->anomaly->getId());
        $this->assertSame(['Déposer la roulette', 'Monter le pneu neuf'], $this->linkedSubTaskTitles());
    }

    public function testTaskAndSubTaskPagesListTheLinkedAnomaly(): void
    {
        $this->scenario();
        $this->anomaly->addSubTask($this->tyreSubTask);
        $this->entityManager()->flush();

        $this->client->request('GET', $this->host . '/tasks/' . $this->visit->getId() . '/subtasks/' . $this->tyreSubTask->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('a[href$="/anomalies/' . $this->anomaly->getId() . '"]', 'Pneu usé');

        $this->client->request('GET', $this->host . '/tasks/' . $this->visit->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('a[href$="/anomalies/' . $this->anomaly->getId() . '"]', 'Pneu usé');

        $this->client->request('GET', $this->host . '/anomalies');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', '1 sous-tâche');
    }

    /**
     * Les textes d'aide du traitement décrivaient la règle des tâches entières.
     */
    public function testTreatmentHelpTextsDescribeTheSubTaskRule(): void
    {
        $translator = static::getContainer()->get('translator');

        foreach (['anomalyTreatHelp', 'anomalyCannotBeTreated', 'anomalyStatusHelp', 'anomalyBulkTreatHelp'] as $key) {
            $text = $translator->trans($key, [], 'messages', 'fr');
            $this->assertStringContainsString('sous-tâche', $text, $key);
            $this->assertDoesNotMatchRegularExpression('/(?<!sous-)tâches? (liées?|ouverte|en cours|fermées?|sont)/u', $text, $key);
        }
    }

    /**
     * Sur mobile, la touche « Rechercher » du clavier soumettait le formulaire avec
     * une sélection incomplète : le filtre doit vivre hors du formulaire.
     */
    public function testFilterFieldCannotSubmitTheLinkForm(): void
    {
        $this->scenario();

        $this->client->request('GET', $this->anomalyUrl('/link/' . $this->visit->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-controller~="checklist-filter"] input[type="search"]');
        $this->assertSelectorNotExists('form input[type="search"]');
    }

    /**
     * Chaque carte de tâche lit ses sous-tâches et leurs contributeurs : sans
     * jointure, un matériel chargé d'historique coûtait plusieurs centaines de requêtes.
     */
    public function testChooseTaskQueryCountDoesNotGrowWithHistory(): void
    {
        $this->scenario();

        // Second matériel : 3 tâches ouvertes, 20 closes, 5 sous-tâches chacune,
        // un mécanicien différent par tâche
        $glider = $this->createEquipment('F-CHIS');
        for ($i = 1; $i <= 23; ++$i) {
            $mechanic = $this->createUser("mecano{$i}@example.test");
            $membership = new Membership();
            $this->club->addMembership($membership);
            $mechanic->addMembership($membership);

            $task = $this->createTask($glider, "Visite {$i}", $i <= 3 ? 'open' : 'closed');
            for ($j = 1; $j <= 5; ++$j) {
                $subTask = $this->createSubTask($task, "Étape {$j}", $i <= 3 ? 'open' : 'closed');
                $contribution = (new Contribution())->setMembership($membership)->setTimeSpent('1.50');
                $subTask->addContribution($contribution);
                $this->entityManager()->persist($contribution);
            }
        }

        $history = new Anomaly();
        $history->setClub($this->club);
        $history->setEquipment($glider);
        $history->setTitle('Verrière fêlée');
        $history->setDescription('Fêlure au coin avant gauche.');
        $history->setReportedBy($this->manager);
        $this->entityManager()->persist($history);
        $this->entityManager()->flush();

        // La première requête partage le gestionnaire d'entités du scénario, déjà rempli
        $this->client->request('GET', $this->anomalyUrl('/link'));

        $baseline = $this->linkPageQueryCount($this->anomaly);
        $withHistory = $this->linkPageQueryCount($history);

        $this->assertSelectorTextContains('details', 'Visite 23');
        $this->assertSame($baseline, $withHistory);
    }

    /**
     * Task::getAnomalies() parcourt les sous-tâches : sans préchargement, la fiche
     * tâche interrogeait la table de liaison une fois par sous-tâche.
     */
    public function testTaskPageLoadsAnomaliesInOneQuery(): void
    {
        $this->scenario();
        $this->anomaly->addSubTask($this->tyreSubTask);
        $this->entityManager()->flush();

        $taskUrl = $this->host . '/tasks/' . $this->visit->getId();
        // La première requête partage le gestionnaire d'entités du scénario, déjà rempli
        $this->client->request('GET', $taskUrl);

        $this->client->enableProfiler();
        $this->client->request('GET', $taskUrl);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('a[href$="/anomalies/' . $this->anomaly->getId() . '"]', 'Pneu usé');

        $queries = $this->client->getProfile()->getCollector('db')->getQueries()['default'];
        $anomalyQueries = array_filter($queries, static fn (array $query): bool => str_contains($query['sql'], 'anomaly_sub_task'));
        $this->assertCount(1, $anomalyQueries);
    }

    private function linkPageQueryCount(Anomaly $anomaly): int
    {
        $this->client->enableProfiler();
        $this->client->request('GET', $this->host . '/anomalies/' . $anomaly->getId() . '/link');
        $this->assertResponseIsSuccessful();

        return $this->client->getProfile()->getCollector('db')->getQueryCount();
    }
}
