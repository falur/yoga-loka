<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class UserBio implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 500;

    private function __construct(
        private string|null $value,
    ) {}

    public static function none(): self
    {
        return new self(value: null);
    }

    public static function fromString(string $value): self
    {
        $bio = \trim($value);

        if ($bio === '' || \mb_strlen($bio) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Описание имеет неверную длину.');
        }

        if (\preg_match(pattern: '/(?!\n)\p{Cc}/u', subject: $bio) === 1) {
            throw new InvalidDomainValueException('Описание содержит управляющие символы.');
        }

        return new self(value: $bio);
    }

    public function value(): string|null
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === null;
    }

    public function equals(self $bio): bool
    {
        return $this->value === $bio->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->value;
    }
}
