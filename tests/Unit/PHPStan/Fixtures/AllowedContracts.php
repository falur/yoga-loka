<?php

declare(strict_types=1);

namespace Tests\Unit\PHPStan\Fixtures;

/**
 * @phpstan-type LocaleList list<string>
 * @property list<string> $names
 * @method list<string> names(list<int> $ids)
 * @template T of \Stringable
 */
final class AllowedContracts
{
    /**
     * @param array<int, string> $items
     * @return list<string>
     */
    public function normalize(array $items): array
    {
        return \array_values($items);
    }

    public function nativeMixed(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function explicitMixed($value)
    {
        return $value;
    }

    /**
     * @param array<string, mixed> $items
     * @param callable(mixed): void $callback
     */
    public function explicitMixedInGeneric(array $items, callable $callback): void {}
}
