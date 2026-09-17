<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * SHA-256 хэш высокоэнтропийного токена (access/refresh). В БД хранится только хэш,
 * сам токен у клиента. fromRawToken считает хэш по сырому токену, fromString восстанавливает
 * уже известный хэш (в т.ч. при гидрации из БД в AuthTokenMapper::toDomain()).
 */
final readonly class TokenHash implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromRawToken(string $rawToken): self
    {
        if ($rawToken === '') {
            throw new InvalidDomainValueException('Токен не может быть пустым.');
        }

        return new self(value: \hash(algo: 'sha256', data: $rawToken));
    }

    public static function fromString(string $value): self
    {
        if ($value === '') {
            throw new InvalidDomainValueException('Хэш токена не может быть пустым.');
        }

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $tokenHash): bool
    {
        return \hash_equals(known_string: $this->value, user_string: $tokenHash->value);
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
