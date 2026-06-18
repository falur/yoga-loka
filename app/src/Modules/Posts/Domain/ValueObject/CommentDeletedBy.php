<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\ValueObject\UserId;

/**
 * Кто удалил комментарий (автор или администратор): null-object VO поверх nullable uuid (образец BanUnbannedBy).
 */
final readonly class CommentDeletedBy implements \Stringable, \JsonSerializable
{
    private function __construct(
        private UserId|null $userId,
    ) {}

    public static function none(): self
    {
        return new self(userId: null);
    }

    public static function by(UserId $userId): self
    {
        return new self(userId: $userId);
    }

    public function value(): string|null
    {
        return $this->userId?->value();
    }

    public function isEmpty(): bool
    {
        return $this->userId === null;
    }

    public function equals(self $deletedBy): bool
    {
        return $this->value() === $deletedBy->value();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value() ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->value();
    }
}
