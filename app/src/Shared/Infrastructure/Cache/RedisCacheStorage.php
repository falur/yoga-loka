<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Cache;

use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class RedisCacheStorage implements CacheInterface
{
    private readonly CacheInterface $cache;

    public function __construct(
        string $dsn = 'redis://redis:6379/0',
        string $namespace = 'yoga_loka_cache',
        int $defaultLifetime = 0,
    ) {
        $this->cache = new Psr16Cache(
            new RedisAdapter(
                redis: RedisAdapter::createConnection(dsn: $dsn),
                namespace: $namespace,
                defaultLifetime: $defaultLifetime,
            ),
        );
    }

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get(key: $key, default: $default);
    }

    #[\Override]
    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        return $this->cache->set(key: $key, value: $value, ttl: $ttl);
    }

    #[\Override]
    public function delete(string $key): bool
    {
        return $this->cache->delete($key);
    }

    #[\Override]
    public function clear(): bool
    {
        return $this->cache->clear();
    }

    /**
     * @param iterable<string> $keys
     */
    #[\Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->cache->getMultiple(keys: $keys, default: $default);
    }

    /**
     * @param iterable<string, mixed> $values
     */
    #[\Override]
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
    {
        return $this->cache->setMultiple(values: $values, ttl: $ttl);
    }

    #[\Override]
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->cache->deleteMultiple($keys);
    }

    #[\Override]
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }
}
