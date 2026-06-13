<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class UserSpiritualName implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 100;

    private function __construct(
        private string|null $value,
    ) {}

    public static function none(): self
    {
        return new self(value: null);
    }

    public static function fromString(string $value): self
    {
        $spiritualName = self::normalize($value);

        if ($spiritualName === '' || \mb_strlen($spiritualName) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Духовное имя имеет неверную длину.');
        }

        if (\preg_match(pattern: "/^[\\p{L}\\p{M}' -]+$/u", subject: $spiritualName) !== 1) {
            throw new InvalidDomainValueException('Духовное имя содержит недопустимые символы.');
        }

        return new self(value: $spiritualName);
    }

    public function value(): string|null
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === null;
    }

    public function equals(self $spiritualName): bool
    {
        return $this->value === $spiritualName->value;
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

    private static function normalize(string $value): string
    {
        // Форма нормализации фиксируется явно (FORM_C). Имена параметров расходятся
        // между нативным ext-intl ($string/$form) и стабом
        // symfony/polyfill-intl-normalizer ($s/$form), поэтому аргументы позиционные,
        // а named-arguments-правило для этой строки погашено в phpstan.neon.
        $normalized = \Normalizer::normalize(\trim($value), \Normalizer::FORM_C);

        if (!\is_string($normalized)) {
            throw new InvalidDomainValueException('Духовное имя не удалось нормализовать.');
        }

        return \preg_replace(pattern: '/\s+/u', replacement: ' ', subject: $normalized) ?? '';
    }
}
