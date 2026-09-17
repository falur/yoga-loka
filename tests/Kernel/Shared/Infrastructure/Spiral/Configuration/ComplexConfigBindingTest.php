<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Spiral\Configuration;

use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueStatusInterceptor;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use App\Shared\Infrastructure\Spiral\Configuration\Cycle\CycleConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Cycle\CycleCollectionFactoryConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Cycle\CycleCollectionsConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Cycle\CycleCustomRelationConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Cycle\CycleSchemaGeneratorGroupConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Cycle\CycleSchemaConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Database\DatabaseConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Database\DatabaseConnectionConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Database\DatabaseLoggerConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Queue\QueueConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Queue\QueueConnectionConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Queue\QueueInterceptorsConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Queue\QueuePipelineConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Queue\QueueRegistryConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageBucketConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageLocalVisibilityConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageS3OptionsConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageServerConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageVisibilityModeConfig;
use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;
use Cycle\Database\Config\DatabaseConfig as CycleDatabaseConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Cycle\Config\CycleConfig as SpiralCycleConfig;
use Spiral\Queue\Config\QueueConfig as SpiralQueueConfig;
use Spiral\Queue\QueueRegistry;
use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
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
        self::assertSame('roadrunner', $queueConfig->connections['rabbitmq']->driver);
        self::assertSame('rabbitmq', $queueConfig->connections['rabbitmq']->pipeline);
        self::assertSame('json', $queueConfig->defaultSerializer);
        // Пары «outbox-событие -> Job» не лежат в секции конфигурации: OutboxJobRegistry::register()
        // кладёт handler и сериализатор прямо в реестр очереди Spiral, поэтому статический список в
        // queue.php пуст, а фактическая регистрация проверяется через сам реестр.
        self::assertSame([], $queueConfig->registry->handlers);
        self::assertSame([], $queueConfig->registry->serializers);
        $queueRegistry = $container->get(QueueRegistry::class);
        self::assertInstanceOf(OutboxDebugLogJob::class, $queueRegistry->getHandler(OutboxDebugLogJob::class));
        self::assertInstanceOf(OutboxQueueSerializer::class, $queueRegistry->getSerializer(OutboxDebugLogJob::class));
        self::assertContains(OutboxQueueStatusInterceptor::class, $queueConfig->interceptors->consume);
        self::assertArrayHasKey('memory', $queueConfig->pipelines);
        self::assertArrayHasKey('rabbitmq', $queueConfig->pipelines);
        self::assertInstanceOf(AMQPCreateInfo::class, $queueConfig->pipelines['rabbitmq']->connector);
        self::assertSame('yoga_loka_jobs', $queueConfig->pipelines['rabbitmq']->connector->queue);
        self::assertSame('yoga_loka_jobs', $queueConfig->pipelines['rabbitmq']->connector->exchange);
        self::assertSame('yoga_loka_jobs', $queueConfig->pipelines['rabbitmq']->connector->routingKey);
        self::assertSame(100, $queueConfig->pipelines['rabbitmq']->connector->prefetch);
        self::assertTrue($queueConfig->pipelines['rabbitmq']->connector->durable);
        self::assertTrue($queueConfig->pipelines['rabbitmq']->connector->exchangeDurable);
        self::assertFalse($queueConfig->pipelines['rabbitmq']->connector->requeueOnFail);

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
