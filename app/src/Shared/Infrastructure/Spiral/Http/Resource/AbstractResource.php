<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Http\Resource;

abstract readonly class AbstractResource implements \JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        return \array_map(
            static fn(mixed $value): mixed => $value instanceof \DateTimeInterface
                ? $value->format(\DateTimeInterface::ATOM)
                : $value,
            \get_object_vars($this),
        );
    }
}
