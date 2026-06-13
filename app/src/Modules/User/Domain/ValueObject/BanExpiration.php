<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

final readonly class BanExpiration implements \Stringable, \JsonSerializable
{
    private function __construct(
        private \DateTimeImmutable|null $expiresAt,
    ) {}

    public static function permanent(): self
    {
        return new self(expiresAt: null);
    }

    public static function until(\DateTimeImmutable $expiresAt): self
    {
        return new self(expiresAt: $expiresAt);
    }

    public function value(): \DateTimeImmutable|null
    {
        return $this->expiresAt;
    }

    public function isPermanent(): bool
    {
        return $this->expiresAt === null;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= $now;
    }

    public function equals(self $expiration): bool
    {
        return $this->expiresAt?->format('U.u') === $expiration->expiresAt?->format('U.u');
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->expiresAt?->format(\DateTimeInterface::ATOM) ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->expiresAt?->format(\DateTimeInterface::ATOM);
    }
}
