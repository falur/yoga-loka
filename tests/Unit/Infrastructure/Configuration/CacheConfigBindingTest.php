<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Configuration;

use App\Infrastructure\Configuration\Cache\CacheConfig as AppCacheConfig;
use App\Infrastructure\Configuration\Cache\CacheStorageConfig;
use Spiral\Cache\Config\CacheConfig as SpiralCacheConfig;
use Tests\TestCase;

final class CacheConfigBindingTest extends TestCase
{
    public function testCacheConfigIsRegisteredAsSingleton(): void
    {
        $container = $this->getContainer();

        $firstConfig = $container->get(AppCacheConfig::class);
        $secondConfig = $container->get(AppCacheConfig::class);

        $this->assertSame($firstConfig, $secondConfig);
        $this->assertSame('local', $firstConfig->default);
        $this->assertArrayHasKey('rr-local', $firstConfig->storages);
        $this->assertInstanceOf(CacheStorageConfig::class, $firstConfig->storages['rr-local']);
        $this->assertSame('roadrunner', $firstConfig->storages['rr-local']->type);
        $this->assertSame('local', $firstConfig->storages['rr-local']->driver);
    }

    public function testSpiralCacheConfigStillUsesNativeArrayConfig(): void
    {
        $spiralConfig = $this->getContainer()->get(SpiralCacheConfig::class);
        $rawConfig = $spiralConfig->toArray();

        $this->assertSame('local', $spiralConfig->getDefaultStorage());
        $this->assertSame('roadrunner', $rawConfig['storages']['rr-local']['type']);
    }
}
