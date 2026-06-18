<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

/**
 * Ссылка записи на занятие: своё null-object VO модуля Posts поверх UUID v7.
 * Модуля занятий ещё нет, поэтому на уровне БД это nullable uuid без FK.
 */
final readonly class PostLesson implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string|null $lessonId,
    ) {}

    public static function none(): self
    {
        return new self(lessonId: null);
    }

    public static function pointingTo(string $lessonId): self
    {
        if (!AbstractUuidV7Id::isUuidV7($lessonId)) {
            throw new InvalidDomainValueException('Ссылка на занятие должна быть UUID v7.');
        }

        return new self(lessonId: \strtolower($lessonId));
    }

    public function value(): string|null
    {
        return $this->lessonId;
    }

    public function isEmpty(): bool
    {
        return $this->lessonId === null;
    }

    public function equals(self $lesson): bool
    {
        return $this->lessonId === $lesson->lessonId;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->lessonId ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->lessonId;
    }
}
