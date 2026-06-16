<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Состояние прочтения уведомления как явный тип вместо ?DateTimeImmutable (rules.md:21, 36).
 * Одно опциональное значение -> один VO с приватным nullable, как MediaExpiration. Backing-колонка
 * read_at (nullable) гидрируется отдельным typecast: NULL -> unread(), дата -> readAt().
 */
final readonly class NotificationReadState
{
    private function __construct(
        private \DateTimeImmutable|null $readAt,
    ) {}

    public static function unread(): self
    {
        return new self(readAt: null);
    }

    public static function readAt(\DateTimeImmutable $readAt): self
    {
        return new self(readAt: $readAt);
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    /**
     * Значение для записи в колонку read_at: дата прочтения или null для непрочитанного.
     */
    public function value(): \DateTimeImmutable|null
    {
        return $this->readAt;
    }

    public function markedAt(): \DateTimeImmutable
    {
        return $this->readAt
            ?? throw new InvalidDomainValueException('Непрочитанное уведомление не имеет даты прочтения.');
    }

    public function equals(self $other): bool
    {
        return $this->readAt?->getTimestamp() === $other->readAt?->getTimestamp();
    }
}
