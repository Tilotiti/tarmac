<?php

namespace App\Command;

use App\Entity\Club;
use App\Entity\Contribution;
use App\Entity\Membership;
use App\Entity\Specialisation;
use App\Entity\SubTask;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande temporaire pour peupler le carnet mécano : spécialisations sur sous-tâches,
 * validation aléatoire de sous-tâches (completedBy), et contributions pour un membre.
 *
 * À supprimer ou désactiver après les tests.
 */
#[AsCommand(
    name: 'app:fixtures:seed-logbook',
    description: '[Temporaire] Ajoute aléatoirement des spécialisations aux sous-tâches, valide des sous-tâches et crée des contributions pour tester le carnet mécano',
)]
class SeedLogbookFixturesCommand extends Command
{
    private const DEFAULT_CONTRIBUTIONS_COUNT = 40;
    private const DEFAULT_SUBTASKS_WITH_SPEC_PERCENT = 50;
    private const DEFAULT_CLOSED_SUBTASKS_PERCENT = 35;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('club', 'c', InputOption::VALUE_REQUIRED, 'Sous-domaine du club (ex: cvve, demo)', 'cvve')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email du membre pour lequel créer des contributions (défaut: premier manager du club)', null)
            ->addOption('contributions', null, InputOption::VALUE_REQUIRED, 'Nombre de contributions à créer', (string) self::DEFAULT_CONTRIBUTIONS_COUNT)
            ->addOption('specialisations-percent', null, InputOption::VALUE_REQUIRED, 'Pourcentage de sous-tâches qui recevront des spécialisations (0-100)', (string) self::DEFAULT_SUBTASKS_WITH_SPEC_PERCENT)
            ->addOption('closed-percent', null, InputOption::VALUE_REQUIRED, 'Pourcentage de sous-tâches à marquer comme clôturées avec signataire (0-100)', (string) self::DEFAULT_CLOSED_SUBTASKS_PERCENT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $subdomain = $input->getOption('club');
        $email = $input->getOption('email');
        $contributionsCount = (int) $input->getOption('contributions');
        $specPercent = (int) $input->getOption('specialisations-percent');
        $closedPercent = (int) $input->getOption('closed-percent');

        $club = $this->entityManager->getRepository(Club::class)->findOneBy(['subdomain' => $subdomain]);
        if (!$club) {
            $io->error(sprintf('Club avec sous-domaine "%s" introuvable.', $subdomain));

            return Command::FAILURE;
        }

        $subTasks = $this->entityManager->getRepository(SubTask::class)
            ->createQueryBuilder('s')
            ->join('s.task', 't')
            ->where('t.club = :club')
            ->setParameter('club', $club)
            ->getQuery()
            ->getResult();

        if (empty($subTasks)) {
            $io->warning('Aucune sous-tâche trouvée pour ce club.');

            return Command::SUCCESS;
        }

        $specialisations = $this->entityManager->getRepository(Specialisation::class)->findBy(
            ['club' => $club],
            ['name' => 'ASC']
        );

        $memberships = $this->entityManager->getRepository(Membership::class)->findBy(
            ['club' => $club],
            ['id' => 'ASC']
        );

        if (empty($memberships)) {
            $io->warning('Aucun membre dans ce club.');

            return Command::SUCCESS;
        }

        $targetMembership = null;
        if ($email !== null && $email !== '') {
            $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user) {
                $io->error(sprintf('Utilisateur avec email "%s" introuvable.', $email));

                return Command::FAILURE;
            }
            foreach ($memberships as $m) {
                if ($m->getUser() === $user) {
                    $targetMembership = $m;
                    break;
                }
            }
            if (!$targetMembership) {
                $io->error(sprintf('L\'utilisateur "%s" n\'est pas membre du club "%s".', $email, $subdomain));

                return Command::FAILURE;
            }
        } else {
            foreach ($memberships as $m) {
                if ($m->isManager()) {
                    $targetMembership = $m;
                    break;
                }
            }
            $targetMembership = $targetMembership ?? $memberships[0];
        }

        $io->title('Seed carnet mécano — ' . $club->getName());

        // 1) Spécialisations sur un % des sous-tâches
        $specCount = (int) ceil(count($subTasks) * $specPercent / 100);
        if ($specCount > 0 && !empty($specialisations)) {
            $shuffled = $subTasks;
            shuffle($shuffled);
            $toAssign = array_slice($shuffled, 0, $specCount);
            $assigned = 0;
            $specIndices = array_keys($specialisations);
            foreach ($toAssign as $subTask) {
                $numSpecs = random_int(1, min(2, count($specialisations)));
                shuffle($specIndices);
                $picked = array_slice($specIndices, 0, $numSpecs);
                foreach ($picked as $idx) {
                    $spec = $specialisations[$idx];
                    if (!$subTask->getSpecialisations()->contains($spec)) {
                        $subTask->addSpecialisation($spec);
                        $assigned++;
                    }
                }
            }
            $io->success(sprintf('Spécialisations ajoutées sur ~%d sous-tâches (%d liaisons).', count($toAssign), $assigned));
        } else {
            $io->note('Aucune spécialisation en base ou pourcentage à 0 : rien à faire pour les spécialisations.');
        }

        // 2) Clôturer aléatoirement des sous-tâches (doneBy + completedBy)
        $closedCount = (int) ceil(count($subTasks) * $closedPercent / 100);
        if ($closedCount > 0) {
            $shuffled = $subTasks;
            shuffle($shuffled);
            $toClose = array_slice($shuffled, 0, $closedCount);
            $users = array_map(fn (Membership $m) => $m->getUser(), $memberships);
            foreach ($toClose as $subTask) {
                if ($subTask->getStatus() === 'open' || $subTask->getStatus() === 'done') {
                    $subTask->setStatus('closed');
                    $signer = $users[array_rand($users)];
                    $subTask->setDoneBy($signer);
                    $subTask->setDoneAt(new \DateTimeImmutable('-' . random_int(1, 180) . ' days'));
                    $subTask->setCompletedBy($signer);
                }
            }
            $io->success(sprintf('Sous-tâches marquées clôturées avec signataire : %d.', count($toClose)));
        }

        $this->entityManager->flush();

        // 3) Contributions pour le membre cible
        $existingByKey = [];
        $repo = $this->entityManager->getRepository(Contribution::class);
        foreach ($subTasks as $st) {
            $c = $repo->findOneBySubTaskAndMembership($st, $targetMembership);
            if ($c) {
                $existingByKey[$st->getId() . '_' . $targetMembership->getId()] = true;
            }
        }

        $shuffled = $subTasks;
        shuffle($shuffled);
        $created = 0;
        $now = new \DateTimeImmutable();
        for ($i = 0; $i < $contributionsCount; $i++) {
            $subTask = $shuffled[$i % count($shuffled)];
            $key = $subTask->getId() . '_' . $targetMembership->getId();
            if (isset($existingByKey[$key])) {
                continue;
            }
            $contribution = new Contribution();
            $contribution->setSubTask($subTask);
            $contribution->setMembership($targetMembership);
            $contribution->setTimeSpent((string) round(0.5 + (mt_rand() / mt_getrandmax()) * 7.5, 2));
            $daysAgo = random_int(1, 365);
            $contribution->setCreatedAt($now->modify('-' . $daysAgo . ' days'));
            $this->entityManager->persist($contribution);
            $existingByKey[$key] = true;
            $created++;
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Création de %d contribution(s) pour %s (%s).',
            $created,
            $targetMembership->getUser()->getFullName(),
            $targetMembership->getUser()->getEmail()
        ));

        $io->note([
            'Commande temporaire : à supprimer ou désactiver après les tests.',
            'Carnet mécano : ' . $subdomain . '.tarmac.wip/members/member/' . $targetMembership->getId() . '/logbook',
        ]);

        return Command::SUCCESS;
    }
}
