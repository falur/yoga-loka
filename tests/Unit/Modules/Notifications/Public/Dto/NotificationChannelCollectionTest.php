<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Public\Dto;

use App\Modules\Notifications\Public\Dto\NotificationChannelCollection;
use App\Modules\Notifications\Public\Enum\NotificationChannel;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use PHPUnit\Framework\TestCase;

/**
 * Набор каналов по умолчанию — публичная форма, в которой определение вида объявляет свои каналы.
 * Ядро уведомлений спрашивает у него ровно одно: включён ли канал.
 */
final class NotificationChannelCollectionTest extends TestCase
{
    public function testReportsEnabledChannels(): void
    {
        $defaults = NotificationChannelCollection::of(NotificationChannel::Database, NotificationChannel::Push);

        self::assertTrue($defaults->includes(NotificationChannel::Database));
        self::assertTrue($defaults->includes(NotificationChannel::Push));
    }

    public function testReportsChannelOutsideSetAsDisabled(): void
    {
        $defaults = NotificationChannelCollection::of(NotificationChannel::Database, NotificationChannel::Push);

        self::assertFalse($defaults->includes(NotificationChannel::Realtime));
    }

    public function testRejectsChannelListedTwice(): void
    {
        // Канал, названный в определении вида дважды, — ошибка декларации: набор не схлопывает дубль
        // молча, а падает на построении.
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Канал по умолчанию указан повторно.');

        NotificationChannelCollection::of(
            NotificationChannel::Database,
            NotificationChannel::Push,
            NotificationChannel::Database,
        );
    }

    public function testEmptySetEnablesNoChannel(): void
    {
        $defaults = NotificationChannelCollection::of();

        self::assertCount(0, $defaults);

        foreach (NotificationChannel::cases() as $channel) {
            self::assertFalse($defaults->includes($channel));
        }
    }
}
