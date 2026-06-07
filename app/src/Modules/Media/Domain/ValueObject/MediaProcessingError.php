<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class MediaProcessingError implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 2000;

    private function __construct(
        private string $value,
    ) {}

    public static function none(): self
    {
        return new self(value: '');
    }

    public static function fromString(string $value): self
    {
        $value = \trim($value);

        if ($value === '' || \mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Ошибка обработки имеет неверную длину.');
        }

        if (
            \preg_match(
                pattern: '/(?:uploads|images|videos)\/[0-9a-f]{2}\/[0-9a-f-]{36}\/\S+/i',
                subject: $value,
            ) === 1
            || \preg_match(pattern: '/\betag\b/i', subject: $value) === 1
        ) {
            throw new InvalidDomainValueException('Ошибка обработки не должна содержать технические данные файла.');
        }

        return new self(value: $value);
    }

    public function value(): string|null
    {
        return $this->value === '' ? null : $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
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
