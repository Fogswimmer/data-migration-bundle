<?php

namespace Fogswimmer\DataMigration\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;

#[AsCommand(
    name: 'data-migration:init',
    description: 'Initializes data migration config and directories',
)]
final class InitDataMigrationCommand
{
    public function __invoke(): int
    {
        $configPath = 'config/data_migration.yaml';

        if (!file_exists($configPath)) {
            file_put_contents(
                $configPath,
                <<<YAML
                data_migration:
                    data_source:
                        type: database
                        connection: old

                    tables: { }
                YAML,
            );
        }

        @mkdir('src/DataMigration/Transformers', 0777, true);
        @mkdir('src/DataMigration/PostProcessor', 0777, true);

        return Command::SUCCESS;
    }
}
