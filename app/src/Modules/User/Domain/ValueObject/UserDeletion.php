<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

final readonly class UserDeletion implements \Stringable, \JsonSerializable
{
    private function __construct(
        private \DateTimeImmutable|null $deletedAt,
    ) {}

    public static function active(): self
    {
        return new self(deletedAt: null);
    }

    public static function at(\DateTimeImmutable $deletedAt): self
    {
        return new self(deletedAt: $deletedAt);
    }

    public function value(): \DateTimeImmutable|null
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function equals(self $deletion): bool
    {
        return $this->deletedAt?->format('U.u') === $deletion->deletedAt?->format('U.u');
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->deletedAt?->format(\DateTimeInterface::ATOM) ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->deletedAt?->format(\DateTimeInterface::ATOM);
    }
}
