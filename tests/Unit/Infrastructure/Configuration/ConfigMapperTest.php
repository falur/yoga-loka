<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Configuration;

use App\Infrastructure\Configuration\Cache\CacheAliasConfig;
use App\Infrastructure\Configuration\Cache\CacheConfig;
use App\Infrastructure\Configuration\Cache\CacheStorageConfig;
use App\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Infrastructure\Configuration\Mapping\ConfigMappingException;
use CuyZ\Valinor\MapperBuilder;
use CuyZ\Valinor\Normalizer\Format;
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
            'default' => 123,
            'aliases' => [],
            'storages' => [],
            'typeAliases' => [],
        ]);

        $this->expectException(ConfigMappingException::class);
        $this->expectExceptionMessage('Не удалось преобразовать раздел конфигурации `cache`');
        $this->expectExceptionMessage(CacheConfig::class);

        $mapper->map(section: CacheConfig::configName(), targetClass: CacheConfig::class);
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

    /**
     * @param array<array-key, mixed> $config
     */
    private function mapperFor(array $config): ConfigMapper
    {
        $configurator = $this->createMock(ConfiguratorInterface::class);
        $configurator
            ->method('getConfig')
            ->with(CacheConfig::configName())
            ->willReturn($config);

        return new ConfigMapper(
            configurator: $configurator,
            mapper: new MapperBuilder()->mapper(),
            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
        );
    }
}
