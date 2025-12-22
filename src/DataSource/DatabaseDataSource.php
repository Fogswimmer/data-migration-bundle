<?php

namespace Grokhotov\DataMigration\DataSource;

use Doctrine\DBAL\Connection;
use Grokhotov\DataMigration\Contract\AdvancedQueryDataSourceInterface;

final class DatabaseDataSource implements AdvancedQueryDataSourceInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function fetchAll(string $resource, array $criteria = []): iterable
    {
        [$where, $params] = $this->buildWhere($criteria);

        $sql = \sprintf(
            'SELECT * FROM %s%s',
            $resource,
            $where,
        );

        return $this->connection->fetchAllAssociative($sql, $params);
    }

    public function fetchColumn(
        string $resource,
        string $column,
        array $criteria = [],
    ): array {
        [$where, $params] = $this->buildWhere($criteria);

        $sql = \sprintf(
            'SELECT %s FROM %s%s',
            $column,
            $resource,
            $where,
        );

        return $this->connection->fetchFirstColumn($sql, $params);
    }

    public function fetchOne(
        string $resource,
        string $column,
        array $criteria = [],
    ): mixed {
        [$where, $params] = $this->buildWhere($criteria);

        $sql = \sprintf(
            'SELECT %s FROM %s%s LIMIT 1',
            $column,
            $resource,
            $where,
        );

        return $this->connection->fetchOne($sql, $params);
    }

    public function fetchAllByQuery(string $sql, array $params = []): iterable
    {
        return $this->connection->fetchAllAssociative($sql, $params);
    }

    private function buildWhere(array $criteria): array
    {
        if (!$criteria) {
            return ['', []];
        }

        $where = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            $where[] = "$field = :$field";
            $params[$field] = $value;
        }

        return [' WHERE '.implode(' AND ', $where), $params];
    }
}
