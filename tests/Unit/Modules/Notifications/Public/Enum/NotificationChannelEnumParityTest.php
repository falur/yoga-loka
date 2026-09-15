<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Public\Enum;

use App\Modules\Notifications\Domain\Enum\NotificationChannel as DomainNotificationChannel;
use App\Modules\Notifications\Public\Enum\NotificationChannel;
use PHPUnit\Framework\TestCase;

/**
 * Публичный enum канала доставки — дубликат доменного (домен не вправе зависеть от Public).
 * Преобразование между ними делается по строковому значению, поэтому расхождение набора вариантов
 * или их значений сломало бы решение о каналах в рантайме. Эта проверка держит дубликат синхронным.
 */
final class NotificationChannelEnumParityTest extends TestCase
{
    public function testChannelMatchesDomainEnum(): void
    {
        self::assertSame(
            self::values(DomainNotificationChannel::cases()),
            self::values(NotificationChannel::cases()),
        );
    }

    /**
     * @param list<\BackedEnum> $cases
     *
     * @return array<string, string>
     */
    private static function values(array $cases): array
    {
        $values = [];

        foreach ($cases as $case) {
            $values[$case->name] = (string) $case->value;
        }

        return $values;
    }
}
