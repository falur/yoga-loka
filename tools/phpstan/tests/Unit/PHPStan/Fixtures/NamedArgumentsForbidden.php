<?php

declare(strict_types=1);

namespace Tools\PHPStan\Tests\Unit\PHPStan\Fixtures;

#[NamedArgumentsForbiddenAttribute('/forbidden', 'forbidden')]
final class NamedArgumentsForbidden
{
    /**
     * @return list<string>
     */
    public function run(?self $maybeSelf): array
    {
        $results = [];
        $results[] = forbiddenTwo('a', 'b');
        $results[] = forbiddenTwo('a', second: 'b');
        $results[] = $this->method('a', second: 'b');
        $results[] = $maybeSelf?->method('a', 'b') ?? '';
        $results[] = self::staticMethod('a', 'b');
        $dependency = new NamedArgumentsForbiddenDependency('a', 'b');
        $results[] = $dependency->value();

        $function = static fn(string $first, string $second): string => $first . $second;
        $results[] = $function('a', 'b');

        $results[] = \sprintf('%s %s', ...['a', 'b']);
        $results[] = forbiddenVariadic('a', second: 'b');

        return $results;
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

final class NamedArgumentsForbiddenDependency
{
    public function __construct(
        private readonly string $first,
        private readonly string $second,
    ) {}

    public function value(): string
    {
        return $this->first . $this->second;
    }
}

#[\Attribute]
final class NamedArgumentsForbiddenAttribute
{
    public function __construct(
        public readonly string $path,
        public readonly string $name,
    ) {}
}

function forbiddenTwo(string $first, string $second): string
{
    return $first . $second;
}

function forbiddenVariadic(string $first, string $second, string ...$rest): string
{
    return $first . $second . \implode(separator: '', array: $rest);
}
