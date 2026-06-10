<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Cache\CacheConfig as AppCacheConfig;
use App\Shared\Infrastructure\Configuration\Cache\CacheStorageConfig;
use App\Shared\Infrastructure\Configuration\OpenApi\OpenApiConfig;
use Spiral\Cache\Config\CacheConfig as SpiralCacheConfig;
use Tests\TestCase;

final class CacheConfigBindingTest extends TestCase
{
    public function testCacheConfigIsRegisteredAsSingleton(): void
    {
        $container = $this->getContainer();

        $firstConfig = $container->get(AppCacheConfig::class);
        $secondConfig = $container->get(AppCacheConfig::class);

        self::assertSame($firstConfig, $secondConfig);
        self::assertSame('local', $firstConfig->default);
        self::assertArrayHasKey('rr-local', $firstConfig->storages);
        self::assertInstanceOf(CacheStorageConfig::class, $firstConfig->storages['rr-local']);
        self::assertSame('roadrunner', $firstConfig->storages['rr-local']->type);
        self::assertSame('local', $firstConfig->storages['rr-local']->driver);
    }

    public function testOpenApiConfigIsRegisteredAsSingleton(): void
    {
        $container = $this->getContainer();

        $firstConfig = $container->get(OpenApiConfig::class);
        $secondConfig = $container->get(OpenApiConfig::class);

        self::assertSame($firstConfig, $secondConfig);
        self::assertSame('/api/v1', $firstConfig->routePrefix);
        self::assertSame('public/openapi/openapi.yml', $firstConfig->outputFile);
    }

    public function testSpiralCacheConfigStillUsesNativeArrayConfig(): void
    {
        $spiralConfig = $this->getContainer()->get(SpiralCacheConfig::class);
        $rawConfig = $spiralConfig->toArray();

        self::assertSame('local', $spiralConfig->getDefaultStorage());
        self::assertSame('roadrunner', $rawConfig['storages']['rr-local']['type']);
    }
}
