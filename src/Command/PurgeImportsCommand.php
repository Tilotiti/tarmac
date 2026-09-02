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
