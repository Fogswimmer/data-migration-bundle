<?php

namespace Grokhotov\DataMigration\Tests\Functional;

use Grokhotov\DataMigration\Command\DataMigrationCommand;
use Grokhotov\DataMigration\DataMigrationBundle;
use Grokhotov\DataMigration\DataMigrationService;
use Grokhotov\DataMigration\DataSource\DataSourceFactory;
use Grokhotov\DataMigration\DependencyInjection\DataMigrationExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

final class ContainerTest extends TestCase
{
    public function testGetContainerExtension(): void
    {
        $bundle = new DataMigrationBundle();
        $this->assertInstanceOf(DataMigrationExtension::class, $bundle->getContainerExtension());
    }

    public function testExtensionLoadsServices(): void
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => __DIR__,
        ]));

        $extension = new DataMigrationExtension();
        $extension->load([], $container);

        $this->assertTrue($container->hasParameter('data_migration.config'));
    }

    public function testExtensionLoadsWithConfiguration(): void
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => __DIR__,
        ]));

        $config = [
            'data_migration' => [
                'data_source' => [
                    'type' => 'database',
                    'connection' => 'default',
                ],
                'tables' => [
                    'App\\Entity\\Test' => [
                        'source' => 'test_table',
                        'map' => ['field' => 'column'],
                    ],
                ],
            ],
        ];

        $extension = new DataMigrationExtension();
        $extension->load($config, $container);

        $this->assertTrue($container->hasParameter('data_migration.config'));
        $loadedConfig = $container->getParameter('data_migration.config');

        $this->assertIsArray($loadedConfig);
        $this->assertArrayHasKey('data_source', $loadedConfig);
        $this->assertArrayHasKey('tables', $loadedConfig);
        $this->assertEquals('database', $loadedConfig['data_source']['type']);
    }

    public function testServicesAreRegistered(): void
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => __DIR__,
        ]));

        $extension = new DataMigrationExtension();
        $extension->load([], $container);

        $this->assertTrue(
            $container->hasDefinition(DataMigrationService::class)
            || $container->hasAlias(DataMigrationService::class)
        );

        $this->assertTrue(
            $container->hasDefinition(DataSourceFactory::class)
            || $container->hasAlias(DataSourceFactory::class)
        );

        $this->assertTrue(
            $container->hasDefinition(DataMigrationCommand::class)
            || $container->hasAlias(DataMigrationCommand::class)
        );
    }

    public function testDefaultConfiguration(): void
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => __DIR__,
        ]));

        $extension = new DataMigrationExtension();
        $extension->load([], $container);

        $config = $container->getParameter('data_migration.config');

        $this->assertArrayHasKey('data_source', $config);
        $this->assertEquals('default', $config['data_source']['connection']);
        $this->assertNull($config['data_source']['type']);
    }

    public function testTransformNodeConfiguration(): void
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => __DIR__,
        ]));

        $config = [
            'data_migration' => [
                'data_source' => [
                    'type' => 'database',
                ],
                'tables' => [
                    'App\\Entity\\Test' => [
                        'source' => 'test_table',
                        'map' => ['field' => 'column'],
                        'transform' => [
                            'field' => ['strip_tags'],
                        ],
                    ],
                ],
            ],
        ];

        $extension = new DataMigrationExtension();
        $extension->load($config, $container);

        $loadedConfig = $container->getParameter('data_migration.config');

        $this->assertArrayHasKey('transform', $loadedConfig['tables']['App\\Entity\\Test']);
        $this->assertEquals(
            ['strip_tags'],
            $loadedConfig['tables']['App\\Entity\\Test']['transform']['field']
        );
    }
}
