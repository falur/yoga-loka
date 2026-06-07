<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class OutboxEventType implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        if ($value === '') {
            throw new InvalidDomainValueException('Тип outbox-сообщения не может быть пустым.');
        }

        if (!\class_exists($value)) {
            throw new InvalidDomainValueException('Тип outbox-сообщения должен ссылаться на существующий класс.');
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
