<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

/**
 * Момент мягкого удаления комментария: null-object VO поверх nullable datetime (образец BanUnbannedAt).
 * Источник истины для Comment::isDeleted().
 */
final readonly class CommentDeletedAt implements \Stringable, \JsonSerializable
{
    private function __construct(
        private \DateTimeImmutable|null $deletedAt,
    ) {}

    public static function notDeleted(): self
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

    public function equals(self $other): bool
    {
        return $this->deletedAt?->format('U.u') === $other->deletedAt?->format('U.u');
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
