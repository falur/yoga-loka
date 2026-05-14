<?php

declare(strict_types=1);

namespace Tests\Unit\PHPStan\Fixtures;

/**
 * @template T of array<string, mixed>
 * @phpstan-type Payload array{foo: string}
 * @property array<string, array<int, string>> $items
 * @method array{0: string, 1: int} makeTuple(callable(mixed): void $callback)
 */
final class ForbiddenContracts
{
    public function nativeMixed(mixed $value): void {}

    /**
     * @return mixed
     */
    public function phpDocReturn(): string
    {
        return '';
    }

    /**
     * @param callable(mixed): void $callback
     */
    public function callableMixed(callable $callback): void {}

    /**
     * @throws array{message: string}
     */
    public function shapeThrows(): void {}

    /**
     * @param array<string, array<int, string>> $items
     */
    public function nestedParam(array $items): void {}

    /**
     * @param-out array<string, mixed> $value
     */
    public function paramOut(string &$value): void {}

    public function varDoc(): void
    {
        /** @var list<array<string, int>> $items */
        $items = [];
    }
}
