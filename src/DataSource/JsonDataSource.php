<?php

namespace Fogswimmer\DataMigration\DataSource;

use Fogswimmer\DataMigration\Contract\DataSourceInterface;

final class JsonDataSource implements DataSourceInterface
{
    public function __construct(private string $path) {}

    public function fetchAll(string $source, array $criteria = []): iterable
    {
        $data = json_decode(file_get_contents($this->path), true);

        return $source ? ($data[$source] ?? []) : $data;
    }
}
