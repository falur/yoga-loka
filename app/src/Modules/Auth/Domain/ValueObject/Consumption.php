<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Trait\ComparesDateTimeToMicroseconds;

/**
 * Одноразовость записи (кода/талона): null-object для nullable-колонки consumed_at. notConsumed()
 * — запись ещё не использована (в БД NULL), at() — момент гашения. Избегает голого ?DateTime
 * в домене (rules.md «Явные типы вместо null»).
 */
final readonly class Consumption implements \Stringable, \JsonSerializable
{
    use ComparesDateTimeToMicroseconds;

    private function __construct(
        private \DateTimeImmutable|null $consumedAt,
    ) {}

    public static function notConsumed(): self
    {
        return new self(consumedAt: null);
    }

    public static function at(\DateTimeImmutable $consumedAt): self
    {
        return new self(consumedAt: $consumedAt);
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function value(): \DateTimeImmutable|null
    {
        return $this->consumedAt;
    }

    public function equals(self $other): bool
    {
        if ($this->consumedAt === null || $other->consumedAt === null) {
            return $this->consumedAt === $other->consumedAt;
        }

        return self::dateTimeEqualsToMicroseconds(value: $this->consumedAt, other: $other->consumedAt);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->consumedAt?->format(\DateTimeInterface::ATOM) ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->consumedAt?->format(\DateTimeInterface::ATOM);
    }
}
