<?php

namespace Fogswimmer\DataMigration\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('fogswimmer.migration.post_processor')]
interface DataMigrationPostProcessorInterface
{
    public function getName(): string;

    public function process(
        array $oldRow,
        object $entity,
        DataSourceInterface $source,
        ?array $params = null,
    ): void;
}
