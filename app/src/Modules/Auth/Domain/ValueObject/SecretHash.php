<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Хэш низкоэнтропийного секрета (кода входа / талона регистрации). Хранит уже посчитанную
 * HMAC-строку: само вычисление HMAC делает инфраструктурный SecretHasherContract, а VO лишь
 * переносит результат в домен и из/в БД. Сам секрет в открытом виде в домен не попадает.
 */
final readonly class SecretHash implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        if ($value === '') {
            throw new InvalidDomainValueException('Хэш секрета не может быть пустым.');
        }

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $secretHash): bool
    {
        return \hash_equals(known_string: $this->value, user_string: $secretHash->value);
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
