<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Email модуля Auth. Нормализация ОБЯЗАНА быть побайтово идентична
 * App\Modules\User\Domain\ValueObject\Email (mb_strtolower + trim, FILTER_VALIDATE_EMAIL,
 * длина ≤ 254), чтобы ключи по email в auth_* и users совпадали. Эквивалентность
 * нормализации покрыта тестом-инвариантом. Собственный VO нужен, потому что arch.md
 * запрещает зависимость Domain одного модуля от Domain другого.
 */
final readonly class EmailAddress implements \Stringable, \JsonSerializable
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
