<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Configuration;

use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueSerializer;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueStatusInterceptor;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use App\Shared\Infrastructure\Spiral\Configuration\Cycle\CycleConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Database\DatabaseConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Spiral\Configuration\Exception\ConfigMappingException;
use App\Shared\Infrastructure\Spiral\Configuration\Queue\QueueConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageConfig;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\NormalizerBuilder;
use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\ORM\Collection\IlluminateCollectionFactory;
use Cycle\ORM\Mapper\Mapper;
use Cycle\ORM\Options;
use Cycle\ORM\Relation\Embedded;
use Cycle\ORM\Select\Loader\EmbeddedLoader;
use Cycle\Schema\Generator\GenerateRelations;
use Cycle\Schema\Generator\ResetTables;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Queue\Driver\SyncDriver;
use Spiral\Queue\Interceptor\Consume\ErrorHandlerInterceptor;
use Spiral\Queue\Interceptor\Consume\RetryPolicyInterceptor;
use Spiral\RoadRunner\Jobs\Queue\AMQP\ExchangeType;
use Spiral\RoadRunner\Jobs\Queue\AMQPCreateInfo;
use Spiral\RoadRunner\Jobs\Queue\MemoryCreateInfo;

final class ComplexConfigMapperTest extends TestCase
{
    public function testHydratesDatabaseConfigFromArray(): void
    {
        $config = $this->mapperFor(DatabaseConfig::configName(), [
            'logger' => [
                'default' => null,
                'drivers' => ['runtime' => 'stdout'],
            ],
            'default' => 'default',
            'aliases' => ['read' => 'default'],
            'databases' => [
                'default' => ['driver' => 'sqlite'],
            ],
            'drivers' => [
                'sqlite' => new SQLiteDriverConfig(),
            ],
        ])->map(section: DatabaseConfig::configName(), targetClass: DatabaseConfig::class);

        self::assertNull($config->logger->default);
        self::assertSame('stdout', $config->logger->drivers['runtime']);
        self::assertSame('default', $config->default);
        self::assertSame('default', $config->aliases['read']);
        self::assertSame('sqlite', $config->databases['default']->driver);
        self::assertInstanceOf(SQLiteDriverConfig::class, $config->drivers['sqlite']);
    }

    public function testHydratesCycleConfigFromArray(): void
    {
        $collectionFactory = new IlluminateCollectionFactory();
        $options = new Options();

        $config = $this->mapperFor(CycleConfig::configName(), [
            'schema' => [
                'cache' => true,
                'defaults' => [
                    'mapper' => Mapper::class,
                ],
                'collections' => [
                    'default' => 'illuminate',
                    'factories' => [
                        'illuminate' => $collectionFactory,
                    ],
                ],
                'generators' => [
                    ResetTables::class,
                ],
            ],
            'warmup' => false,
            'options' => $options,
            'customRelations' => [
                'embedded' => [
                    'loader' => EmbeddedLoader::class,
                    'relation' => Embedded::class,
                ],
            ],
        ])->map(section: CycleConfig::configName(), targetClass: CycleConfig::class);

        self::assertTrue($config->schema->cache);
        self::assertSame(Mapper::class, $config->schema->defaults['mapper']);
        self::assertSame('illuminate', $config->schema->collections->default);
        self::assertSame($collectionFactory, $config->schema->collections->factories['illuminate']->factory);
        self::assertSame([ResetTables::class], $config->schema->generators);
        self::assertFalse($config->warmup);
        self::assertSame($options, $config->options);
        self::assertSame(EmbeddedLoader::class, $config->customRelations['embedded']->loader);
        self::assertSame(Embedded::class, $config->customRelations['embedded']->relation);
    }

    public function testHydratesCycleConfigWithGroupedGeneratorsFromArray(): void
    {
        $config = $this->mapperFor(CycleConfig::configName(), [
            'schema' => [
                'cache' => true,
                'defaults' => [
                    'mapper' => Mapper::class,
                ],
                'collections' => [
                    'default' => 'illuminate',
                    'factories' => [
                        'illuminate' => new IlluminateCollectionFactory(),
                    ],
                ],
                'generators' => [
                    'sync' => [
                        ResetTables::class,
                        GenerateRelations::class,
                    ],
                ],
            ],
            'warmup' => false,
            'options' => null,
            'customRelations' => [],
        ])->map(section: CycleConfig::configName(), targetClass: CycleConfig::class);

        self::assertSame([ResetTables::class, GenerateRelations::class], $config->schema->generators['sync']->classes);
    }

    public function testThrowsSafeExceptionForInvalidCycleCollectionFactory(): void
    {
        try {
            $this->mapperFor(CycleConfig::configName(), [
                'schema' => [
                    'cache' => true,
                    'defaults' => [
                        'mapper' => Mapper::class,
                    ],
                    'collections' => [
                        'default' => 'illuminate',
                        'factories' => [
                            'illuminate' => new \stdClass(),
                        ],
                    ],
                    'generators' => null,
                ],
                'warmup' => false,
                'options' => null,
                'customRelations' => [],
            ])->map(section: CycleConfig::configName(), targetClass: CycleConfig::class);
        } catch (ConfigMappingException $exception) {
            $message = $exception->getMessage();

            self::assertStringContainsString('раздел конфигурации `cycle`', $message);
            self::assertStringContainsString(CycleConfig::class, $message);
            self::assertStringContainsString('schema.collections.factories.illuminate', $message);
            self::assertStringContainsString('CollectionFactoryInterface', $message);
            self::assertStringContainsString('stdClass', $message);

            return;
        }

        self::fail('Ожидалось безопасное исключение маппинга.');
    }

    public function testHydratesQueueConfigFromArray(): void
    {
        $memoryConnector = new MemoryCreateInfo('local');
        $rabbitMqConnector = new AMQPCreateInfo(
            name: 'rabbitmq',
            prefetch: 100,
            queue: 'yoga_loka_jobs',
            exchange: 'yoga_loka_jobs',
            exchangeType: ExchangeType::Direct,
            routingKey: 'yoga_loka_jobs',
            requeueOnFail: false,
            durable: true,
            exchangeDurable: true,
        );

        $config = $this->mapperFor(QueueConfig::configName(), [
            'default' => 'rabbitmq',
            'aliases' => ['mail' => 'rabbitmq'],
            'connections' => [
                'sync' => ['driver' => 'sync'],
                'in-memory' => [
                    'driver' => 'roadrunner',
                    'pipeline' => 'memory',
                ],
                'rabbitmq' => [
                    'driver' => 'roadrunner',
                    'pipeline' => 'rabbitmq',
                ],
            ],
            'registry' => [
                'handlers' => [OutboxDebugLogJob::class => OutboxDebugLogJob::class],
                'serializers' => [OutboxDebugLogJob::class => OutboxQueueSerializer::class],
            ],
            'driverAliases' => ['sync' => SyncDriver::class],
            'interceptors' => [
                'push' => [],
                'consume' => [
                    ErrorHandlerInterceptor::class,
                    OutboxQueueStatusInterceptor::class,
                    RetryPolicyInterceptor::class,
                ],
            ],
            'pipelines' => [
                'memory' => [
                    'connector' => $memoryConnector,
                    'consume' => true,
                ],
                'rabbitmq' => [
                    'connector' => $rabbitMqConnector,
                    'consume' => true,
                ],
            ],
            'defaultSerializer' => 'json',
        ])->map(section: QueueConfig::configName(), targetClass: QueueConfig::class);

        self::assertSame('rabbitmq', $config->default);
        self::assertSame('rabbitmq', $config->aliases['mail']);
        self::assertSame('sync', $config->connections['sync']->driver);
        self::assertSame('memory', $config->connections['in-memory']->pipeline);
        self::assertSame('rabbitmq', $config->connections['rabbitmq']->pipeline);
        self::assertSame(OutboxDebugLogJob::class, $config->registry->handlers[OutboxDebugLogJob::class]);
        self::assertSame(OutboxQueueSerializer::class, $config->registry->serializers[OutboxDebugLogJob::class]);
        self::assertSame(SyncDriver::class, $config->driverAliases['sync']);
        self::assertSame(
            [
                ErrorHandlerInterceptor::class,
                OutboxQueueStatusInterceptor::class,
                RetryPolicyInterceptor::class,
            ],
            $config->interceptors->consume,
        );
        self::assertSame($memoryConnector, $config->pipelines['memory']->connector);
        self::assertSame($rabbitMqConnector, $config->pipelines['rabbitmq']->connector);
        self::assertTrue($config->pipelines['memory']->consume);
        self::assertTrue($config->pipelines['rabbitmq']->consume);
        self::assertSame('json', $config->defaultSerializer);
    }

    public function testHydratesStorageConfigFromArray(): void
    {
        $config = $this->mapperFor(StorageConfig::configName(), [
            'default' => 's3-test',
            'servers' => [
                'local' => [
                    'adapter' => 'local',
                    'directory' => '/app/public/uploads',
                    'visibility' => [
                        'public' => ['file' => 0o644, 'dir' => 0o755],
                        'private' => ['file' => 0o600, 'dir' => 0o700],
                        'default' => 'public',
                    ],
                ],
                's3' => [
                    'adapter' => 's3',
                    'region' => 'us-east-1',
                    'version' => 'latest',
                    'bucket' => 'yoga-loka',
                    'key' => 'secret-key',
                    'secret' => 'secret-password',
                    'token' => null,
                    'expires' => null,
                    'visibility' => 'public',
                    'prefix' => '',
                    'endpoint' => 'http://minio:9000',
                    'options' => [
                        'use_path_style_endpoint' => true,
                    ],
                ],
            ],
            'buckets' => [
                'default' => ['server' => 'local'],
                's3-test' => [
                    'server' => 's3',
                    'bucket' => 'yoga-loka-test',
                ],
            ],
        ])->map(section: StorageConfig::configName(), targetClass: StorageConfig::class);

        self::assertSame('s3-test', $config->default);
        self::assertSame('local', $config->servers['local']->adapter);
        self::assertSame('/app/public/uploads', $config->servers['local']->directory);
        self::assertSame(0o644, $config->servers['local']->visibility->public->file);
        self::assertSame(0o700, $config->servers['local']->visibility->private->dir);
        self::assertSame('public', $config->servers['local']->visibility->default);
        self::assertSame('s3', $config->servers['s3']->adapter);
        self::assertSame('secret-key', $config->servers['s3']->key);
        self::assertSame('secret-password', $config->servers['s3']->secret);
        self::assertTrue($config->servers['s3']->options->usePathStyleEndpoint);
        self::assertSame('local', $config->buckets['default']->server);
        self::assertSame('yoga-loka-test', $config->buckets['s3-test']->bucket);
    }

    /**
     * @param class-string $targetClass
     * @param array<array-key, mixed> $config
     */
    #[DataProvider('invalidRootConfigProvider')]
    public function testThrowsSafeExceptionForInvalidRootConfig(
        string $section,
        string $targetClass,
        array $config,
        string $path,
    ): void {
        try {
            $this->mapperFor($section, $config)->map(section: $section, targetClass: $targetClass);
        } catch (ConfigMappingException $exception) {
            self::assertStringContainsString("раздел конфигурации `{$section}`", $exception->getMessage());
            self::assertStringContainsString($targetClass, $exception->getMessage());
            self::assertStringContainsString($path, $exception->getMessage());
            self::assertStringNotContainsString('secret-key', $exception->getMessage());
            self::assertStringNotContainsString('secret-password', $exception->getMessage());
            self::assertStringNotContainsString('secret-token', $exception->getMessage());

            return;
        }

        self::fail('Ожидалось безопасное исключение маппинга.');
    }

    public static function invalidRootConfigProvider(): iterable
    {
        yield 'database' => [
            DatabaseConfig::configName(),
            DatabaseConfig::class,
            [
                'logger' => ['default' => null, 'drivers' => []],
                'default' => [],
                'aliases' => [],
                'databases' => ['default' => ['driver' => 'sqlite']],
                'drivers' => ['sqlite' => new SQLiteDriverConfig()],
            ],
            'default',
        ];

        yield 'cycle' => [
            CycleConfig::configName(),
            CycleConfig::class,
            [
                'schema' => [
                    'cache' => ['secret-password'],
                    'defaults' => [],
                    'collections' => [
                        'default' => 'illuminate',
                        'factories' => ['illuminate' => new IlluminateCollectionFactory()],
                    ],
                    'generators' => null,
                ],
                'warmup' => false,
                'options' => null,
                'customRelations' => [],
            ],
            'schema.cache',
        ];

        yield 'queue' => [
            QueueConfig::configName(),
            QueueConfig::class,
            [
                'default' => [],
                'aliases' => [],
                'connections' => ['sync' => ['driver' => 'sync']],
                'registry' => ['handlers' => [], 'serializers' => []],
                'driverAliases' => ['sync' => SyncDriver::class],
                'interceptors' => [],
                'pipelines' => [],
                'defaultSerializer' => 'json',
            ],
            'default',
        ];

        yield 'storage' => [
            StorageConfig::configName(),
            StorageConfig::class,
            [
                'default' => 's3-test',
                'servers' => [
                    's3' => [
                        'adapter' => 's3',
                        'key' => 'secret-key',
                        'secret' => ['secret-password'],
                        'token' => 'secret-token',
                    ],
                ],
                'buckets' => ['s3-test' => ['server' => 's3']],
            ],
            'servers.s3.secret',
        ];
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function mapperFor(string $section, array $config): ConfigMapper
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->willReturnMap([[$section, $config]]);

        return new ConfigMapper(
            configurator: $configurator,
            mapper: new MapperBuilder()
                ->configureWith(new ConvertKeysToCamelCase())
                ->allowPermissiveTypes()
                ->allowScalarValueCasting()
                ->mapper(),
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );
    }
}
