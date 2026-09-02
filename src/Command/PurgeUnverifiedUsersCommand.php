<?php

namespace App\Command;

use App\Repository\ResetPasswordRequestRepository;
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
        private readonly ResetPasswordRequestRepository $resetPasswordRequestRepository,
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
        $dryRun = (bool) $input->getOption('dry-run');

        foreach ($users as $user) {
            $io->text(sprintf('%s (créé le %s)', $user->getEmail(), $user->getCreatedAt()?->format('d/m/Y')));
        }

        $count = count($users);

        if (!$dryRun) {
            try {
                foreach ($users as $user) {
                    // reset_password_request.user_id n'a pas de ON DELETE : purger d'abord les
                    // demandes de réinitialisation, sinon le remove() du user échoue en base.
                    $this->resetPasswordRequestRepository->removeRequests($user);
                    $this->entityManager->remove($user);
                }
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                $io->error(sprintf('Purge interrompue : %s', $e->getMessage()));

                return Command::FAILURE;
            }
        }

        $io->success(sprintf('%d compte%s %s.', $count, $count > 1 ? 's' : '', $dryRun ? 'à supprimer' : 'supprimé(s)'));

        return Command::SUCCESS;
    }
}
