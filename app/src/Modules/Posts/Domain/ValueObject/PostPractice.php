<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

/**
 * Ссылка записи на практику: своё null-object VO модуля Posts поверх UUID v7.
 * Модуля практик ещё нет, поэтому на уровне БД это nullable uuid без FK.
 */
final readonly class PostPractice implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string|null $practiceId,
    ) {}

    public static function none(): self
    {
        return new self(practiceId: null);
    }

    public static function pointingTo(string $practiceId): self
    {
        if (!AbstractUuidV7Id::isUuidV7($practiceId)) {
            throw new InvalidDomainValueException('Ссылка на практику должна быть UUID v7.');
        }

        return new self(practiceId: \strtolower($practiceId));
    }

    public function value(): string|null
    {
        return $this->practiceId;
    }

    public function isEmpty(): bool
    {
        return $this->practiceId === null;
    }

    public function equals(self $practice): bool
    {
        return $this->practiceId === $practice->practiceId;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->practiceId ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->practiceId;
    }
}
