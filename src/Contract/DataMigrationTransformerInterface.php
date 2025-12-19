<?php

namespace Fogswimmer\DataMigration\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('fogswimmer.migration.transformer')]
interface DataMigrationTransformerInterface
{
    public function getName(): string;

    public function transform(mixed $value, array $options = []): mixed;
}
