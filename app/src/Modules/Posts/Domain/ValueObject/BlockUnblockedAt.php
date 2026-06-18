<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

/**
 * Момент разблокировки записи: null-object VO поверх nullable datetime (образец BanUnbannedAt).
 * notUnblocked() — блокировка активна.
 */
final readonly class BlockUnblockedAt implements \Stringable, \JsonSerializable
{
    private function __construct(
        private \DateTimeImmutable|null $unblockedAt,
    ) {}

    public static function notUnblocked(): self
    {
        return new self(unblockedAt: null);
    }

    public static function at(\DateTimeImmutable $unblockedAt): self
    {
        return new self(unblockedAt: $unblockedAt);
    }

    public function value(): \DateTimeImmutable|null
    {
        return $this->unblockedAt;
    }

    public function isUnblocked(): bool
    {
        return $this->unblockedAt !== null;
    }

    public function equals(self $other): bool
    {
        return $this->unblockedAt?->format('U.u') === $other->unblockedAt?->format('U.u');
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->unblockedAt?->format(\DateTimeInterface::ATOM) ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->unblockedAt?->format(\DateTimeInterface::ATOM);
    }
}
