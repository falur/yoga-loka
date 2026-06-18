<?php

declare(strict_types=1);

namespace App\Modules\Tags\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class TagText implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 50;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $text = \mb_strtolower(\trim($value));

        if ($text === '' || \mb_strlen($text) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Тег имеет неверную длину.');
        }

        if (\preg_match(pattern: '/^[\p{L}\p{N}_-]+$/u', subject: $text) !== 1) {
            throw new InvalidDomainValueException('Тег может содержать только буквы, цифры, дефис и подчёркивание.');
        }

        return new self(value: $text);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $text): bool
    {
        return $this->value === $text->value;
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
