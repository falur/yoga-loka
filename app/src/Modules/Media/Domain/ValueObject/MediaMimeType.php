<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class MediaMimeType implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $value = \trim($value);

        if ($value === '' || \strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('MIME type файла имеет неверную длину.');
        }

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Базовый MIME без параметров: нижний регистр, отброшена часть после `;`, обрезаны пробелы
     * (`text/markdown;charset=utf-8` -> `text/markdown`). Хранимое значение (`value()`) не меняется.
     *
     * Значение нормализуется только для сопоставления MIME и не является готовым Content-Type.
     * По нему сравнивают со списком разрешённых документов в `MediaTypeResolver` и проверяют
     * `MediaMimeTypeCollection::containsMimeType()`. Префиксная классификация image/video/audio в
     * резолвере и `equals()` намеренно работают по исходному `value()`, чтобы не менять поведение
     * этих типов, поэтому нормализация на них не распространяется.
     */
    public function baseValue(): string
    {
        return \strtolower(\trim(\explode(separator: ';', string: $this->value)[0]));
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
