<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\Cache\CacheAliasConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Cache\CacheConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Cache\CacheStorageConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Exception\ConfigMappingException;
use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
use CuyZ\Valinor\Normalizer\Normalizer;
use CuyZ\Valinor\NormalizerBuilder;
use PHPUnit\Framework\TestCase;
use Spiral\Config\ConfiguratorInterface;

final class ConfigMapperTest extends TestCase
{
    public function testHydratesCacheConfig(): void
    {
        $mapper = $this->mapperFor([
            'default' => 'rr-local',
            'aliases' => [
                'blog-data' => 'rr-local',
                'user-data' => [
                    'storage' => 'rr-local',
                    'prefix' => 'user_',
                ],
            ],
            'storages' => [
                'rr-local' => [
                    'type' => 'roadrunner',
                    'driver' => 'local',
                ],
                'file' => [
                    'type' => 'file',
                    'path' => '/tmp/cache',
                ],
            ],
            'typeAliases' => [
                'file' => CacheStorageConfig::class,
            ],
        ]);

        $config = $mapper->map(section: CacheConfig::configName(), targetClass: CacheConfig::class);

        self::assertSame('rr-local', $config->default);
        self::assertSame('rr-local', $config->aliases['blog-data']);
        self::assertInstanceOf(CacheAliasConfig::class, $config->aliases['user-data']);
        self::assertSame('user_', $config->aliases['user-data']->prefix);
        self::assertSame('roadrunner', $config->storages['rr-local']->type);
        self::assertSame('local', $config->storages['rr-local']->driver);
        self::assertSame('/tmp/cache', $config->storages['file']->path);
        self::assertSame(CacheStorageConfig::class, $config->typeAliases['file']);
    }

    public function testThrowsReadableExceptionForInvalidConfig(): void
    {
        $mapper = $this->mapperFor([
            'default' => [],
            'aliases' => [],
            'storages' => [],
            'typeAliases' => [],
        ]);

        $this->expectException(ConfigMappingException::class);
        $this->expectExceptionMessage('Не удалось преобразовать раздел конфигурации `cache`');
        $this->expectExceptionMessage(CacheConfig::class);

        $mapper->map(section: CacheConfig::configName(), targetClass: CacheConfig::class);
    }

    public function testThrowsSafeExceptionWithoutSecretValue(): void
    {
        $mapper = $this->mapperForSection(
            section: 'secret',
            config: [
                'dsn' => 'smtp://secret-user:secret-password@mailpit:1025',
            ],
        );

        try {
            $mapper->map(section: 'secret', targetClass: ConfigMapperSecretProbe::class);
        } catch (ConfigMappingException $exception) {
            $message = $exception->getMessage();

            self::assertStringContainsString('Не удалось преобразовать раздел конфигурации `secret`', $message);
            self::assertStringContainsString(ConfigMapperSecretProbe::class, $message);
            self::assertStringContainsString('dsn', $message);
            self::assertStringContainsString('ожидалось `int`', $message);
            self::assertStringContainsString('получено `string`', $message);
            self::assertStringNotContainsString('smtp://secret-user:secret-password@mailpit:1025', $message);
            self::assertStringNotContainsString('secret-password', $message);

            return;
        }

        self::fail('Ожидалось безопасное исключение маппинга.');
    }

    public function testConvertsSnakeCaseKeysToCamelCase(): void
    {
        $mapper = $this->mapperForSection(
            section: 'storage',
            config: [
                'use_path_style_endpoint' => true,
            ],
        );

        $config = $mapper->map(section: 'storage', targetClass: ConfigMapperSnakeCaseProbe::class);

        self::assertTrue($config->usePathStyleEndpoint);
    }

    public function testNormalizesCacheConfig(): void
    {
        $mapper = $this->mapperFor([]);
        $config = new CacheConfig(
            default: 'local',
            aliases: [],
            storages: [
                'local' => new CacheStorageConfig(type: 'array'),
            ],
            typeAliases: [],
        );

        $normalized = $mapper->normalize($config);

        self::assertSame('local', $normalized['default']);
        self::assertArrayHasKey('aliases', $normalized);
        self::assertArrayHasKey('storages', $normalized);
        self::assertArrayHasKey('typeAliases', $normalized);
        self::assertSame('array', $normalized['storages']['local']['type']);
    }

    public function testMapRethrowsUnexpectedError(): void
    {
        $configurator = $this->createStub(ConfiguratorInterface::class);
        $configurator->method('getConfig')->willReturn([]);

        $treeMapper = $this->createStub(TreeMapper::class);
        $treeMapper->method('map')->willThrowException(new \RuntimeException('Непредвиденная ошибка маппинга.'));

        $mapper = new ConfigMapper(
            configurator: $configurator,
            mapper: $treeMapper,
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Непредвиденная ошибка маппинга.');

        $mapper->map(section: 'any', targetClass: ConfigMapperSnakeCaseProbe::class);
    }

    public function testNormalizeRejectsNonArrayResult(): void
    {
        $mapper = $this->mapperWithNormalizer(new class implements Normalizer {
            public function normalize(mixed $value): mixed
            {
                return 'not-an-array';
            }
        });

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('должен возвращать массив для объекта');

        $mapper->normalize(new \stdClass());
    }

    public function testNormalizeRejectsNonStringKeys(): void
    {
        $mapper = $this->mapperWithNormalizer(new class implements Normalizer {
            public function normalize(mixed $value): mixed
            {
                return [0 => 'value'];
            }
        });

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('массив со строковыми ключами');

        $mapper->normalize(new \stdClass());
    }

    private function mapperWithNormalizer(Normalizer $normalizer): ConfigMapper
    {
        return new ConfigMapper(
            configurator: $this->createStub(ConfiguratorInterface::class),
            mapper: $this->createStub(TreeMapper::class),
            normalizer: $normalizer,
        );
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        return $this->mapperForSection(section: CacheConfig::configName(), config: $config);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function mapperForSection(string $section, array $config): ConfigMapper
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

final readonly class ConfigMapperSecretProbe
{
    public function __construct(
        public int $dsn,
    ) {}
}

final readonly class ConfigMapperSnakeCaseProbe
{
    public function __construct(
        public bool $usePathStyleEndpoint,
    ) {}
}
