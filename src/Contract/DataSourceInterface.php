<?php

namespace Grokhotov\DataMigration\Contract;

interface DataSourceInterface
{
    /**
     * @return iterable<array<string, mixed>>
     */
    public function fetchAll(string $resource, array $criteria = []): iterable;
}
