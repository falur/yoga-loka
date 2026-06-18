<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class CommentText implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 2000;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $text = \trim($value);

        if ($text === '' || \mb_strlen($text) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Текст комментария имеет неверную длину.');
        }

        if (\preg_match(pattern: '/(?!\n)\p{Cc}/u', subject: $text) === 1) {
            throw new InvalidDomainValueException('Текст комментария содержит управляющие символы.');
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
