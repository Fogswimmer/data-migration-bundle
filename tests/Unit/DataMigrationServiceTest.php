<?php

namespace Fogswimmer\DataMigration\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Fogswimmer\DataMigration\Contract\DataMigrationPostProcessorInterface;
use Fogswimmer\DataMigration\Contract\DataMigrationTransformerInterface;
use Fogswimmer\DataMigration\Contract\DataSourceInterface;
use Fogswimmer\DataMigration\DataMigrationService;
use Fogswimmer\DataMigration\Helpers\IdMappingStore;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessor;

final class DataMigrationServiceTest extends TestCase
{
    private DataMigrationService $service;
    private EntityManagerInterface|MockObject $em;
    private PropertyAccessor $propertyAccessor;
    private IdMappingStore $idMappingStore;
    private int $autoIncrementId = 1;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->propertyAccessor = new PropertyAccessor();
        $this->idMappingStore = new IdMappingStore();
        $this->autoIncrementId = 1;
    }

    private function setupEntityManager(array &$persistedEntities): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([]);
        /* @var EntityManagerInterface|MockObject  * */
        $this->em->method('getRepository')->willReturn($repository);

        $autoIncrementId = &$this->autoIncrementId;

        $this->em->method('persist')->willReturnCallback(
            function ($entity) use (&$persistedEntities, &$autoIncrementId) {
                $reflection = new \ReflectionClass($entity);
                $property = $reflection->getProperty('id');
                $property->setAccessible(true);
                $property->setValue($entity, $autoIncrementId++);

                $persistedEntities[] = $entity;
            }
        );
    }

    public function testSimpleMapping(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'name' => 'name',
                'email' => 'email',
            ],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertCount(2, $persistedEntities);
        $this->assertEquals('John', $persistedEntities[0]->name);
        $this->assertEquals('john@example.com', $persistedEntities[0]->email);
        $this->assertNotNull($persistedEntities[0]->getId());
    }

    public function testMultiColumnMapping(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'fullName' => ['first_name', 'last_name'],
            ],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertEquals('John Doe', $persistedEntities[0]->fullName);
    }

    public function testPhpFunctionTransformation(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'description' => '<p>Hello <b>World</b></p>'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'posts',
            'map' => [
                'description' => 'description',
            ],
            'transform' => [
                'description' => ['strip_tags'],
            ],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertEquals('Hello World', $persistedEntities[0]->description);
    }

    public function testCustomTransformer(): void
    {
        $transformer = new class implements DataMigrationTransformerInterface {
            public function getName(): string
            {
                return 'uppercase';
            }

            public function transform(mixed $value, mixed $params = null): mixed
            {
                return strtoupper($value);
            }
        };

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'john'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [$transformer],
            [],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'name' => 'name',
            ],
            'transform' => [
                'name' => ['uppercase'],
            ],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertEquals('JOHN', $persistedEntities[0]->name);
    }

    public function testTransformerWithParameters(): void
    {
        $transformer = new class implements DataMigrationTransformerInterface {
            public function getName(): string
            {
                return 'calc_percent';
            }

            public function transform(mixed $value, mixed $params = null): mixed
            {
                return round(($value / $params) * 100);
            }
        };

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'score' => 4],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [$transformer],
            [],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'ratings',
            'map' => [
                'percentage' => 'score',
            ],
            'transform' => [
                'percentage' => [['calc_percent' => 5]],
            ],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertEquals(80, $persistedEntities[0]->percentage);
    }

    public function testMultipleTransformations(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'text' => '  <p>hello</p>  '],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'posts',
            'map' => [
                'text' => 'text',
            ],
            'transform' => [
                'text' => ['strip_tags', 'trim', 'strtoupper'],
            ],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertEquals('HELLO', $persistedEntities[0]->text);
    }

    public function testThrowsExceptionForUnknownTransformation(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'test'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'name' => 'name',
            ],
            'transform' => [
                'name' => ['unknown_function'],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown transformation: unknown_function');

        $this->service->migrate($dataSource, TestEntity::class, $config);
    }

    public function testThrowsExceptionWhenNoDataFound(): void
    {
        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn([]);
        $this->em->method('getRepository')->willReturn($repository);

        $this->service = new DataMigrationService(
            [],
            [],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => ['name' => 'name'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No data found in source table: users');

        $this->service->migrate($dataSource, TestEntity::class, $config);
    }

    public function testSimplePostProcessor(): void
    {
        $postProcessor = new class implements DataMigrationPostProcessorInterface {
            public bool $wasCalled = false;
            public ?array $receivedOldRow = null;
            public ?object $receivedEntity = null;

            public function getName(): string
            {
                return 'test_processor';
            }

            public function process(
                array $oldRow,
                object $entity,
                DataSourceInterface $dataSource,
                mixed $params = null,
            ): void {
                $this->wasCalled = true;
                $this->receivedOldRow = $oldRow;
                $this->receivedEntity = $entity;
            }
        };

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'John'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [$postProcessor],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'name' => 'name',
            ],
            'post_process' => ['test_processor'],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertTrue($postProcessor->wasCalled);
        $this->assertNotNull($postProcessor->receivedOldRow);
        $this->assertNotNull($postProcessor->receivedEntity);
        $this->assertEquals(['id' => 1, 'name' => 'John'], $postProcessor->receivedOldRow);
        $this->assertInstanceOf(TestEntity::class, $postProcessor->receivedEntity);
    }

    public function testPostProcessorWithParameters(): void
    {
        $postProcessor = new class implements DataMigrationPostProcessorInterface {
            public mixed $receivedParams = null;

            public function getName(): string
            {
                return 'processor_with_params';
            }

            public function process(
                array $oldRow,
                object $entity,
                DataSourceInterface $dataSource,
                mixed $params = null,
            ): void {
                $this->receivedParams = $params;
            }
        };

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'John'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [$postProcessor],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'name' => 'name',
            ],
            'post_process' => [
                ['processor_with_params' => ['table' => 'media', 'type' => 'image']],
            ],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertEquals(['table' => 'media', 'type' => 'image'], $postProcessor->receivedParams);
    }

    public function testMultiplePostProcessors(): void
    {
        $processor1 = new class implements DataMigrationPostProcessorInterface {
            public bool $wasCalled = false;

            public function getName(): string
            {
                return 'first_processor';
            }

            public function process(
                array $oldRow,
                object $entity,
                DataSourceInterface $dataSource,
                mixed $params = null,
            ): void {
                $this->wasCalled = true;
            }
        };

        $processor2 = new class implements DataMigrationPostProcessorInterface {
            public bool $wasCalled = false;

            public function getName(): string
            {
                return 'second_processor';
            }

            public function process(
                array $oldRow,
                object $entity,
                DataSourceInterface $dataSource,
                mixed $params = null,
            ): void {
                $this->wasCalled = true;
            }
        };

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'John'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [$processor1, $processor2],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'name' => 'name',
            ],
            'post_process' => ['first_processor', 'second_processor'],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertTrue($processor1->wasCalled);
        $this->assertTrue($processor2->wasCalled);
    }

    public function testPostProcessorAccessToDataSource(): void
    {
        $postProcessor = new class implements DataMigrationPostProcessorInterface {
            public ?DataSourceInterface $receivedDataSource = null;

            public function getName(): string
            {
                return 'data_source_processor';
            }

            public function process(
                array $oldRow,
                object $entity,
                DataSourceInterface $dataSource,
                mixed $params = null,
            ): void {
                $this->receivedDataSource = $dataSource;

                $relatedData = $dataSource->fetchAll('related_table');
            }
        };

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturnCallback(function ($table) {
            if ('users' === $table) {
                return [['id' => 1, 'name' => 'John']];
            }
            if ('related_table' === $table) {
                return [['id' => 1, 'data' => 'related']];
            }

            return [];
        });

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [$postProcessor],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'name' => 'name',
            ],
            'post_process' => ['data_source_processor'],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertNotNull($postProcessor->receivedDataSource);
        $this->assertSame($dataSource, $postProcessor->receivedDataSource);
    }

    public function testPostProcessorOnlyCalledWhenConfigured(): void
    {
        $postProcessor = new class implements DataMigrationPostProcessorInterface {
            public bool $wasCalled = false;

            public function getName(): string
            {
                return 'optional_processor';
            }

            public function process(
                array $oldRow,
                object $entity,
                DataSourceInterface $dataSource,
                mixed $params = null,
            ): void {
                $this->wasCalled = true;
            }
        };

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'John'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [$postProcessor],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'name' => 'name',
            ],
            // No post_process
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertFalse($postProcessor->wasCalled);
    }

    public function testPostProcessorCalledForEachEntity(): void
    {
        $postProcessor = new class implements DataMigrationPostProcessorInterface {
            public int $callCount = 0;
            public array $processedNames = [];

            public function getName(): string
            {
                return 'counting_processor';
            }

            public function process(
                array $oldRow,
                object $entity,
                DataSourceInterface $dataSource,
                mixed $params = null,
            ): void {
                ++$this->callCount;
                $this->processedNames[] = $entity->name;
            }
        };

        $dataSource = $this->createMock(DataSourceInterface::class);
        $dataSource->method('fetchAll')->willReturn([
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
            ['id' => 3, 'name' => 'Bob'],
        ]);

        $persistedEntities = [];
        $this->setupEntityManager($persistedEntities);

        $this->service = new DataMigrationService(
            [],
            [$postProcessor],
            $this->em,
            $this->propertyAccessor,
            $this->idMappingStore
        );

        $config = [
            'source' => 'users',
            'map' => [
                'name' => 'name',
            ],
            'post_process' => ['counting_processor'],
        ];

        $this->service->migrate($dataSource, TestEntity::class, $config);

        $this->assertEquals(3, $postProcessor->callCount);
        $this->assertEquals(['John', 'Jane', 'Bob'], $postProcessor->processedNames);
    }
}

class TestEntity
{
    private ?int $id = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $fullName = null;
    public ?string $description = null;
    public ?string $text = null;
    public ?int $percentage = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
