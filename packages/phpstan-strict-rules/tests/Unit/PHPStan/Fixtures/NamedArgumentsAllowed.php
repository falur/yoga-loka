<?php

declare(strict_types=1);

namespace GianTiaga\PhpStanStrictRules\Tests\Unit\PHPStan\Fixtures;

use PHPUnit\Framework\Assert;

#[NamedArgumentsAttribute(path: '/allowed', name: 'allowed')]
final class NamedArgumentsAllowed
{
    public function run(): void
    {
        one('value');
        two(first: 'a', second: 'b');

        $this->method('value');
        $this->method(first: 'a', second: 'b');
        $this?->method(first: 'a', second: 'b');
        self::staticMethod(first: 'a', second: 'b');
        new NamedArgumentsDependency(first: 'a', second: 'b');

        $function = static fn(string $first, string $second): string => $first . $second;
        $function(first: 'a', second: 'b');

        \sprintf(...['%s %s', 'a', 'b']);
        \sprintf('%s %s', 'a', 'b');
        two('a', ...['b']);
        variadicAllowed('a', 'b');
        variadicAllowed('a', 'b', ...['c']);
        Assert::assertSame('a', 'a');
    }

    private static function staticMethod(string $first, string $second): string
    {
        return $first . $second;
    }

    private function method(string $first, string $second = ''): string
    {
        return $first . $second;
    }
}

final class NamedArgumentsDependency
{
    public function __construct(string $first, string $second) {}
}

#[\Attribute]
final class NamedArgumentsAttribute
{
    public function __construct(string $path, string $name) {}
}

function one(string $value): string
{
    return $value;
}

function two(string $first, string $second): string
{
    return $first . $second;
}

function variadicAllowed(string $first, string $second, string ...$rest): string
{
    return $first . $second . \implode(separator: '', array: $rest);
}
