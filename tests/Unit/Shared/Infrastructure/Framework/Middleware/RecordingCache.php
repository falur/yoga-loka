<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Framework\Middleware;

use DateInterval;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Управляемый временем PSR-16 кэш для тестов rate limit: TTL истекает по общим часам MockClock,
 * а TTL счётчика (ключи без суффикса `:reset`) записываются для проверки фиксированного окна.
 */
final class RecordingCache implements CacheInterface
{
    /**
     * @var array<string, array{value: mixed, expiresAt: int}>
     */
    private array $entries = [];

    /**
     * @var list<int>
     */
    private array $counterTtls = [];

    public function __construct(private readonly ClockInterface $clock) {}

    /**
     * @return list<int>
     */
    public function counterTtls(): array
    {
        return $this->counterTtls;
    }

    #[\Override]
    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->entries[$key] ?? null;

        if ($entry === null || $entry['expiresAt'] <= $this->now()) {
            unset($this->entries[$key]);

            return $default;
        }

        return $entry['value'];
    }

    #[\Override]
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        $seconds = \is_int($ttl) ? $ttl : 0;

        if (!\str_ends_with($key, ':reset')) {
            $this->counterTtls[] = $seconds;
        }

        $this->entries[$key] = ['value' => $value, 'expiresAt' => $this->now() + $seconds];

        return true;
    }

    #[\Override]
    public function delete(string $key): bool
    {
        unset($this->entries[$key]);

        return true;
    }

    #[\Override]
    public function clear(): bool
    {
        $this->entries = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     * @return iterable<string, mixed>
     */
    #[\Override]
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    #[\Override]
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param iterable<string> $keys
     */
    #[\Override]
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    #[\Override]
    public function has(string $key): bool
    {
        return $this->get($key, null) !== null;
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }
}
