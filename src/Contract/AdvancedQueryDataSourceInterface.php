<?php

namespace Grokhotov\DataMigration\Contract;

interface AdvancedQueryDataSourceInterface extends DataSourceInterface
{
    public function fetchColumn(
        string $resource,
        string $column,
        array $criteria = [],
    ): array;

    public function fetchOne(
        string $resource,
        string $column,
        array $criteria = [],
    ): mixed;

    public function fetchAllByQuery(
        string $sql,
        array $params = [],
    ): iterable;
}
