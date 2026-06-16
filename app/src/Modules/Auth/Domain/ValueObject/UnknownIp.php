<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

/**
 * Null-object для отсутствующего/невалидного IP-адреса (в БД — NULL). Чтение наружу даёт null,
 * чтобы отсутствие значения было явным.
 */
final readonly class UnknownIp extends Ip
{
    #[\Override]
    public function toNullableString(): string|null
    {
        return null;
    }

    #[\Override]
    public function equals(Ip $other): bool
    {
        return $other instanceof self;
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return null;
    }
}
