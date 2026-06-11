<?php

declare(strict_types=1);

namespace Tests\Feature\Shared\Infrastructure\Cache;

use App\Shared\Infrastructure\Cache\RedisCacheStorage;
use PHPUnit\Framework\TestCase;

final class RedisCacheStorageTest extends TestCase
{
    public function testStoresReadsAndDeletesValuesAgainstRedis(): void
    {
        // DSN берётся строго из окружения (phpunit.xml задаёт REDIS_DSN). Молчаливый фолбэк
        // на литерал убран: без переменной тест должен явно падать, а не идти мимо
        // тестового Redis или против непредусмотренного инстанса, маскируя проблему конфигурации.
        $redisDsn = \getenv('REDIS_DSN');
        if (!\is_string($redisDsn) || $redisDsn === '') {
            self::fail('REDIS_DSN не задан в окружении теста.');
        }

        $cache = new RedisCacheStorage(
            dsn: $redisDsn,
            namespace: 'yoga_loka_test_cache',
        );
        $cache->clear();

        self::assertTrue($cache->set(key: 'single', value: 'value'));
        self::assertSame('value', $cache->get(key: 'single'));
        self::assertTrue($cache->has(key: 'single'));

        self::assertTrue($cache->delete(key: 'single'));
        self::assertFalse($cache->has(key: 'single'));
        self::assertSame('fallback', $cache->get(key: 'single', default: 'fallback'));

        self::assertTrue($cache->setMultiple(values: ['alpha' => 1, 'beta' => 2]));
        self::assertSame(
            ['alpha' => 1, 'beta' => 2],
            \iterator_to_array($cache->getMultiple(keys: ['alpha', 'beta'])),
        );
        self::assertTrue($cache->deleteMultiple(keys: ['alpha', 'beta']));
        self::assertFalse($cache->has(key: 'alpha'));

        self::assertTrue($cache->clear());
    }
}
