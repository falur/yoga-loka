<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\ValueObject\UserId;

final readonly class BanUnbannedBy implements \Stringable, \JsonSerializable
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

    public function equals(self $unbannedBy): bool
    {
        return $this->value() === $unbannedBy->value();
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
