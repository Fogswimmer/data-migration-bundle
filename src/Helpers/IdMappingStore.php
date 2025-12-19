<?php

namespace Fogswimmer\DataMigration\Helpers;

class IdMappingStore
{
    private array $map = [];

    public function add(string $entityClass, int|string $oldId, int|string $newId): void
    {
        $this->map[$entityClass][$oldId] = $newId;
    }

    public function get(string $entityClass, int|string $oldId): int|string|null
    {
        return $this->map[$entityClass][$oldId] ?? null;
    }

    public function has(string $entityClass, int|string $oldId): bool
    {
        return isset($this->map[$entityClass][$oldId]);
    }
}
