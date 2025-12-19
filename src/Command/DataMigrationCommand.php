<?php

namespace Fogswimmer\DataMigration\Command;

use Doctrine\Persistence\ManagerRegistry;
use Fogswimmer\DataMigration\DataMigrationService;
use Fogswimmer\DataMigration\DataSource\DataSourceFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'data-migration:migrate',
    description: 'Migrates data from old database to new database with transformation and post-processing.',
)]
class DataMigrationCommand
{
    private array $config;

    public function __construct(
        private DataMigrationService $dataMigrationService,
        private ManagerRegistry $doctrine,
        private DataSourceFactory $dataSourceFactory,
        private ParameterBagInterface $parameterBag,
    ) {
        $this->config = $this->parameterBag->get('data_migration.config');
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Starting Data Migration');

        try {
            if (empty($this->config['data_source']['type'])) {
                throw new \RuntimeException('Missing "data_source.type" in data_migration configuration');
            }

            if (empty($this->config['tables'])) {
                throw new \RuntimeException('No tables configured for data migration');
            }

            $dataSourceType = $this->config['data_source']['type'];

            $tables = $this->config['tables'];

            $io->progressStart(\count($tables));

            foreach ($tables as $entityClass => $tableConfig) {
                $io->text(\sprintf('Processing %s', $entityClass));

                $sourceConfig = ['type' => $dataSourceType];

                if (\in_array($dataSourceType, ['json', 'csv'], true)) {
                    if (!isset($tableConfig['source_path'])) {
                        throw new \RuntimeException(\sprintf('Missing "source_path" for %s with %s data source', $entityClass, $dataSourceType));
                    }
                    $sourceConfig['path'] = $this->resolvePath($tableConfig['source_path']);
                } elseif ('database' === $dataSourceType) {
                    $sourceConfig['connection'] = $this->config['data_source']['connection'] ?? null;
                    if (null === $sourceConfig['connection']) {
                        throw new \RuntimeException(\sprintf('Missing "connection" for %s with %s data source', $entityClass, $dataSourceType));
                    }
                }

                $dataSource = $this->dataSourceFactory->create($sourceConfig);

                $this->dataMigrationService->migrate(
                    $dataSource,
                    $entityClass,
                    $tableConfig,
                );

                $io->progressAdvance();
            }

            $io->progressFinish();
            $io->success('Data Migration finished successfully!');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            $io->text($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function resolvePath(string $path): string
    {
        return preg_replace_callback(
            '/%([^%]+)%/',
            fn($matches) => $this->parameterBag->get($matches[1]),
            $path,
        );
    }
}
