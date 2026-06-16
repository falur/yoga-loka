<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\Trait\ComparesDateTimeToMicroseconds;

/**
 * Технический момент времени перехода outbox-события (queued/handled/failed) как явный тип вместо
 * ?DateTimeImmutable (rules.md:21). Одно опциональное значение -> один VO с приватным nullable,
 * как MediaExpiration. Колонка nullable; NULL <-> none(), дата <-> fromDateTime().
 */
final readonly class OutboxEventDate implements \JsonSerializable, \Stringable
{
    use ComparesDateTimeToMicroseconds;

    private function __construct(
        private \DateTimeImmutable|null $value,
    ) {}

    public static function none(): self
    {
        return new self(value: null);
    }

    public static function fromDateTime(\DateTimeImmutable $value): self
    {
        return new self(value: $value);
    }

    public function isEmpty(): bool
    {
        return $this->value === null;
    }

    public function value(): \DateTimeImmutable|null
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        if ($this->value === null || $other->value === null) {
            return $this->value === null && $other->value === null;
        }

        return self::dateTimeEqualsToMicroseconds(value: $this->value, other: $other->value);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value?->format(\DateTimeInterface::ATOM) ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value?->format(\DateTimeInterface::ATOM) ?? '';
    }
}
