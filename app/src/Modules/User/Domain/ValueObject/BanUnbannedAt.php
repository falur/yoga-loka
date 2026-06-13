<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

final readonly class BanUnbannedAt implements \Stringable, \JsonSerializable
{
    private function __construct(
        private \DateTimeImmutable|null $unbannedAt,
    ) {}

    public static function notUnbanned(): self
    {
        return new self(unbannedAt: null);
    }

    public static function at(\DateTimeImmutable $unbannedAt): self
    {
        return new self(unbannedAt: $unbannedAt);
    }

    public function value(): \DateTimeImmutable|null
    {
        return $this->unbannedAt;
    }

    public function isUnbanned(): bool
    {
        return $this->unbannedAt !== null;
    }

    public function equals(self $unbannedAt): bool
    {
        return $this->unbannedAt?->format('U.u') === $unbannedAt->unbannedAt?->format('U.u');
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->unbannedAt?->format(\DateTimeInterface::ATOM) ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->unbannedAt?->format(\DateTimeInterface::ATOM);
    }
}
