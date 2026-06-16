<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Готовый текст уведомления на языке получателя. Хранится в колонке text без верхней границы
 * длины; формирует модуль-источник, Notifications не переводит.
 */
final readonly class NotificationBody implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $value = \trim($value);

        if ($value === '') {
            throw new InvalidDomainValueException('Текст уведомления не может быть пустым.');
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
