<?php

namespace Fogswimmer\DataMigration\DataSource;

use Doctrine\Persistence\ManagerRegistry;
use Fogswimmer\DataMigration\Contract\DataSourceInterface;

final class DataSourceFactory
{
    public function __construct(
        private ManagerRegistry $doctrine,
    ) {}

    public function create(array $config): DataSourceInterface
    {
        return match ($config['type']) {
            'database' => new DatabaseDataSource(
                $this->doctrine->getConnection($config['connection'] ?? 'default'),
            ),
            'json' => new JsonDataSource($config['path']),
            default => throw new \RuntimeException('Unknown data source type'),
        };
    }
}
