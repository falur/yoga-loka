<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class UserNickname implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $nickname = \mb_strtolower(\trim($value));

        if (\preg_match(pattern: '/^[a-z0-9](?:[a-z0-9._-]{1,28})[a-z0-9]$/', subject: $nickname) !== 1) {
            throw new InvalidDomainValueException('Никнейм имеет неверный формат.');
        }

        if (\str_contains(haystack: $nickname, needle: '..')) {
            throw new InvalidDomainValueException('Никнейм не должен содержать две точки подряд.');
        }

        return new self(value: $nickname);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $nickname): bool
    {
        return $this->value === $nickname->value;
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
