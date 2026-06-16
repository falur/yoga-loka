<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

/**
 * Null-object для отсутствующего User-Agent (в БД — NULL). Чтение наружу даёт null, чтобы
 * отсутствие значения было явным.
 */
final readonly class UnknownUserAgent extends UserAgent
{
    #[\Override]
    public function toNullableString(): string|null
    {
        return null;
    }

    #[\Override]
    public function equals(UserAgent $other): bool
    {
        return $other instanceof self;
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return null;
    }
}
