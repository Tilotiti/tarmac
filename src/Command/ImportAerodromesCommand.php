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

        try {
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
        } finally {
            if ($temporary !== null) {
                unlink($temporary);
            }
        }

        $io->success(sprintf('%d aérodromes créés, %d mis à jour.', $created, $updated));

        return Command::SUCCESS;
    }
}
