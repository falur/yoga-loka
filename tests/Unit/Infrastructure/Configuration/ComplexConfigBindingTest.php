<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Configuration;

use App\Infrastructure\Configuration\Cycle\CycleConfig;
use App\Infrastructure\Configuration\Cycle\CycleCollectionFactoryConfig;
use App\Infrastructure\Configuration\Cycle\CycleCollectionsConfig;
use App\Infrastructure\Configuration\Cycle\CycleCustomRelationConfig;
use App\Infrastructure\Configuration\Cycle\CycleSchemaGeneratorGroupConfig;
use App\Infrastructure\Configuration\Cycle\CycleSchemaConfig;
use App\Infrastructure\Configuration\Database\DatabaseConfig;
use App\Infrastructure\Configuration\Database\DatabaseConnectionConfig;
use App\Infrastructure\Configuration\Database\DatabaseLoggerConfig;
use App\Infrastructure\Configuration\Queue\QueueConfig;
use App\Infrastructure\Configuration\Queue\QueueConnectionConfig;
use App\Infrastructure\Configuration\Queue\QueueInterceptorsConfig;
use App\Infrastructure\Configuration\Queue\QueuePipelineConfig;
use App\Infrastructure\Configuration\Queue\QueueRegistryConfig;
use App\Infrastructure\Configuration\Storage\StorageBucketConfig;
use App\Infrastructure\Configuration\Storage\StorageConfig;
use App\Infrastructure\Configuration\Storage\StorageLocalVisibilityConfig;
use App\Infrastructure\Configuration\Storage\StorageS3OptionsConfig;
use App\Infrastructure\Configuration\Storage\StorageServerConfig;
use App\Infrastructure\Configuration\Storage\StorageVisibilityModeConfig;
use App\Infrastructure\Configuration\TypedConfig;
use Cycle\Database\Config\DatabaseConfig as CycleDatabaseConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Cycle\Config\CycleConfig as SpiralCycleConfig;
use Spiral\Queue\Config\QueueConfig as SpiralQueueConfig;
use Spiral\Storage\Config\StorageConfig as SpiralStorageConfig;
use Tests\TestCase;

final class ComplexConfigBindingTest extends TestCase
{
    /**
     * @param class-string $configClass
     */
    #[DataProvider('complexRootConfigProvider')]
    public function testComplexConfigIsRegisteredAsSingleton(string $configClass): void
    {
        $container = $this->getContainer();

        self::assertSame($container->get($configClass), $container->get($configClass));
    }

    public function testComplexConfigValuesComeFromRealConfigurator(): void
    {
        $container = $this->getContainer();

        $databaseConfig = $container->get(DatabaseConfig::class);
        $cycleConfig = $container->get(CycleConfig::class);
        $queueConfig = $container->get(QueueConfig::class);
        $storageConfig = $container->get(StorageConfig::class);

        self::assertNull($databaseConfig->logger->default);
        self::assertSame('default', $databaseConfig->default);
        self::assertSame('pgsql', $databaseConfig->databases['default']->driver);
        self::assertArrayHasKey('pgsql', $databaseConfig->drivers);

        self::assertIsBool($cycleConfig->schema->cache);
        self::assertSame($container->get(SpiralCycleConfig::class)->cacheSchema(), $cycleConfig->schema->cache);
        self::assertSame('illuminate', $cycleConfig->schema->collections->default);
        self::assertArrayHasKey('illuminate', $cycleConfig->schema->collections->factories);
        self::assertNull($cycleConfig->schema->generators);
        self::assertNull($cycleConfig->options);

        self::assertSame($container->get(SpiralQueueConfig::class)->getDefaultDriver(), $queueConfig->default);
        self::assertSame('roadrunner', $queueConfig->connections['in-memory']->driver);
        self::assertSame('memory', $queueConfig->connections['in-memory']->pipeline);
        self::assertSame('json', $queueConfig->defaultSerializer);
        self::assertArrayHasKey('memory', $queueConfig->pipelines);

        self::assertSame('s3-test', $storageConfig->default);
        self::assertSame('local', $storageConfig->servers['local']->adapter);
        self::assertSame('s3', $storageConfig->servers['s3']->adapter);
        self::assertTrue($storageConfig->servers['s3']->options->usePathStyleEndpoint);
        self::assertSame('s3', $storageConfig->buckets['s3-test']->server);
    }

    public function testNestedComplexConfigDtosAreNotRootTypedConfigs(): void
    {
        self::assertFalse(\is_subclass_of(DatabaseConnectionConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(DatabaseLoggerConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(CycleCollectionFactoryConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(CycleCollectionsConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(CycleCustomRelationConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(CycleSchemaGeneratorGroupConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(CycleSchemaConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(QueueConnectionConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(QueueInterceptorsConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(QueuePipelineConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(QueueRegistryConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(StorageBucketConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(StorageLocalVisibilityConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(StorageS3OptionsConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(StorageServerConfig::class, TypedConfig::class));
        self::assertFalse(\is_subclass_of(StorageVisibilityModeConfig::class, TypedConfig::class));
    }

    public function testNativeFrameworkConfigsStillWorkForComplexSections(): void
    {
        $container = $this->getContainer();

        self::assertSame('default', $container->get(CycleDatabaseConfig::class)->getDefaultDatabase());
        self::assertIsBool($container->get(SpiralCycleConfig::class)->cacheSchema());
        self::assertSame($container->get(QueueConfig::class)->default, $container->get(SpiralQueueConfig::class)->getDefaultDriver());
        self::assertSame('s3-test', $container->get(SpiralStorageConfig::class)->getDefaultBucket());
    }

    public static function complexRootConfigProvider(): iterable
    {
        yield DatabaseConfig::class => [DatabaseConfig::class];
        yield CycleConfig::class => [CycleConfig::class];
        yield QueueConfig::class => [QueueConfig::class];
        yield StorageConfig::class => [StorageConfig::class];
    }
}
