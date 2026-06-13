<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class Email implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 254;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $email = \mb_strtolower(\trim($value));

        if (
            $email === ''
            || \mb_strlen($email) > self::MAX_LENGTH
            || !\filter_var(value: $email, filter: FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidDomainValueException('Email имеет неверный формат.');
        }

        return new self(value: $email);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $email): bool
    {
        return $this->value === $email->value;
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
