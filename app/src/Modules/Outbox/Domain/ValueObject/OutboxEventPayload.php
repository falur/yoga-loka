<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class OutboxEventPayload implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromJson(string $value): self
    {
        if (!\json_validate($value)) {
            throw new InvalidDomainValueException('Payload outbox-сообщения должен быть валидным JSON.');
        }

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
