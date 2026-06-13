<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class UserName implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 100;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $name = self::normalize($value);

        if ($name === '' || \mb_strlen($name) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Имя имеет неверную длину.');
        }

        if (\preg_match(pattern: "/^[\\p{L}\\p{M}' -]+$/u", subject: $name) !== 1) {
            throw new InvalidDomainValueException('Имя содержит недопустимые символы.');
        }

        return new self(value: $name);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $name): bool
    {
        return $this->value === $name->value;
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

    private static function normalize(string $value): string
    {
        // Форма нормализации фиксируется явно (FORM_C). Имена параметров расходятся
        // между нативным ext-intl ($string/$form) и стабом
        // symfony/polyfill-intl-normalizer ($s/$form), поэтому аргументы позиционные,
        // а named-arguments-правило для этой строки погашено в phpstan.neon.
        $normalized = \Normalizer::normalize(\trim($value), \Normalizer::FORM_C);

        if (!\is_string($normalized)) {
            throw new InvalidDomainValueException('Имя не удалось нормализовать.');
        }

        return \preg_replace(pattern: '/\s+/u', replacement: ' ', subject: $normalized) ?? '';
    }
}
