<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\ValueObject\UserId;

final readonly class ReservedNicknameHolder implements \Stringable, \JsonSerializable
{
    private function __construct(
        private UserId|null $userId,
    ) {}

    public static function unassigned(): self
    {
        return new self(userId: null);
    }

    public static function assignedTo(UserId $userId): self
    {
        return new self(userId: $userId);
    }

    public function value(): string|null
    {
        return $this->userId?->value();
    }

    public function isUnassigned(): bool
    {
        return $this->userId === null;
    }

    public function isAssignedTo(UserId $userId): bool
    {
        return $this->userId?->equals($userId) ?? false;
    }

    public function equals(self $holder): bool
    {
        return $this->value() === $holder->value();
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
