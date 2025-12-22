<?php

namespace Grokhotov\DataMigration;

use Doctrine\ORM\EntityManagerInterface;
use Grokhotov\DataMigration\Contract\DataMigrationPostProcessorInterface;
use Grokhotov\DataMigration\Contract\DataMigrationTransformerInterface;
use Grokhotov\DataMigration\Contract\DataSourceInterface;
use Grokhotov\DataMigration\Helpers\IdMappingStore;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

class DataMigrationService
{
    /**
     * @param iterable<DataMigrationTransformerInterface>   $transformers
     * @param iterable<DataMigrationPostProcessorInterface> $postProcessors
     */
    public function __construct(
        #[AutowireIterator('grokhotov.migration.transformer')]
        private iterable $transformers,
        #[AutowireIterator('grokhotov.migration.post_processor')]
        private iterable $postProcessors,
        private EntityManagerInterface $em,
        private PropertyAccessorInterface $propertyAccessor,
        private IdMappingStore $idMappingStore,
    ) {
    }

    public function migrate(
        DataSourceInterface $dataSource,
        string $entityClass,
        array $config,
    ): void {
        $sourceTable = $config['source'];
        $columnMap = $config['map'];
        $transformations = $config['transform'] ?? [];
        $postProcessors = $config['post_process'] ?? [];

        try {
            $sourceData = $dataSource->fetchAll($sourceTable);
        } catch (\Exception $e) {
            throw new \RuntimeException(\sprintf('Error fetching data from source table: %s', $e->getMessage()));
        }

        if (empty($sourceData)) {
            throw new \RuntimeException(\sprintf('No data found in source table: %s', $sourceTable));
        }

        $this->clearData($entityClass);

        foreach ($sourceData as $oldRow) {
            $entity = new $entityClass();

            foreach ($columnMap as $entityProperty => $oldColumn) {
                $value = $this->extractValue($oldRow, $oldColumn);

                if (isset($transformations[$entityProperty])) {
                    $value = $this->applyTransformation($value, $transformations[$entityProperty]);
                }

                $this->propertyAccessor->setValue($entity, $entityProperty, $value);
            }

            $this->em->persist($entity);
            $this->em->flush();

            $oldId = $oldRow['id'] ?? null;
            if (null !== $oldId) {
                $this->idMappingStore->add($entityClass, $oldId, $entity->getId());
            }

            if (!empty($postProcessors)) {
                $this->applyPostProcessors($oldRow, $entity, $dataSource, $postProcessors);
            }

            $this->em->clear();
        }

        $this->idMappingStore->add($entityClass, $oldRow['id'], $entity->getId());

        $this->em->flush();
        $this->em->clear();
    }

    private function extractValue(array $row, string|array $column): mixed
    {
        if (\is_array($column)) {
            $parts = [];
            foreach ($column as $col) {
                $parts[] = $row[$col] ?? null;
            }

            return trim(implode(' ', array_filter($parts)));
        }

        return $row[$column] ?? null;
    }

    private function applyTransformation(mixed $value, array $transformations): mixed
    {
        foreach ($transformations as $transformation) {
            if (\is_string($transformation)) {
                if ($transformer = $this->getTransformer($transformation)) {
                    $value = $transformer->transform($value);
                    continue;
                }

                $value = $this->applyPhpFunctionTransformation($value, $transformation);
                continue;
            }

            if (\is_array($transformation)) {
                $name = key($transformation);
                $param = $transformation[$name] ?? null;

                if ($transformer = $this->getTransformer($name)) {
                    $value = $transformer->transform($value, $param);
                    continue;
                }

                $value = $this->applyPhpFunctionTransformation($value, $name, $param);
                continue;
            }
        }

        return $value;
    }

    private function applyPhpFunctionTransformation(mixed $value, string $name, mixed $param = null): mixed
    {
        if (function_exists($name)) {
            return null !== $param ? $name($value, $param) : $name($value);
        }

        throw new \RuntimeException("Unknown transformation: $name");
    }

    private function applyPostProcessors(
        array $oldRow,
        object $entity,
        DataSourceInterface $dataSource,
        array $postProcessors,
    ): void {
        foreach ($postProcessors as $postProcessor) {
            if (\is_string($postProcessor)) {
                if ($processor = $this->getPostProcessor($postProcessor)) {
                    $processor->process($oldRow, $entity, $dataSource);
                }
                continue;
            }

            if (\is_array($postProcessor)) {
                $name = key($postProcessor);
                $params = $postProcessor[$name] ?? null;

                if ($processor = $this->getPostProcessor($name)) {
                    $processor->process($oldRow, $entity, $dataSource, $params);
                }
            }
        }
    }

    private function getTransformer(string $name): ?DataMigrationTransformerInterface
    {
        foreach ($this->transformers as $transformer) {
            if ($transformer->getName() === $name) {
                return $transformer;
            }
        }

        return null;
    }

    private function getPostProcessor(string $name): ?DataMigrationPostProcessorInterface
    {
        foreach ($this->postProcessors as $postProcessor) {
            if ($postProcessor->getName() === $name) {
                return $postProcessor;
            }
        }

        return null;
    }

    private function clearData(string $entityClass): void
    {
        $entityRepository = $this->em->getRepository($entityClass);
        $items = $entityRepository->findAll();

        foreach ($items as $item) {
            $this->em->remove($item);
        }

        $this->em->flush();
    }
}
