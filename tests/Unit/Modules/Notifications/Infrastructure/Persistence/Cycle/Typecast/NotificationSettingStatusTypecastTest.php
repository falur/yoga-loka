<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast\NotificationSettingStatusTypecast;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * NotificationSettingStatusTypecast — второй (наряду с NotificationActorTypecast) из пяти
 * typecast-классов Notifications, отнесённый ко второй категории «Правила переноса значений
 * колонок»: несмотря на то, что колонка enabled хранит один boolean-примитив, PostgreSQL отдаёт
 * его по-разному в зависимости от драйвера (bool/0-1/'t'-'f'), а встроенный typecast: 'bool'
 * Cycle делает наивный `(bool) $value` (для строки 'f' это ошибочно даёт true) — таким образом
 * это «формат, который нельзя однозначно выразить типом колонки» (typecast.md), а не простой
 * вызов фабрики VO. Класс и его проверки остаются без изменений.
 */
final class NotificationSettingStatusTypecastTest extends TestCase
{
    /**
     * @param bool|int|string|null $databaseValue
     */
    #[DataProvider('settingStatusProvider')]
    public function testNormalizesDatabaseValue(
        bool|int|string|null $databaseValue,
        NotificationSettingStatus $expected,
    ): void {
        self::assertSame($expected, NotificationSettingStatusTypecast::castDatabaseValue($databaseValue));
    }

    public function testUncastsToBool(): void
    {
        self::assertTrue(NotificationSettingStatusTypecast::uncastValue(NotificationSettingStatus::Enabled));
        self::assertFalse(NotificationSettingStatusTypecast::uncastValue(NotificationSettingStatus::Disabled));
        self::assertFalse(NotificationSettingStatusTypecast::uncastValue(null));
    }

    /**
     * @return iterable<string, array{bool|int|string|null, NotificationSettingStatus}>
     */
    public static function settingStatusProvider(): iterable
    {
        yield 'bool true' => [true, NotificationSettingStatus::Enabled];
        yield 'bool false' => [false, NotificationSettingStatus::Disabled];
        yield 'int 1' => [1, NotificationSettingStatus::Enabled];
        yield 'int 0' => [0, NotificationSettingStatus::Disabled];
        yield 'string t' => ['t', NotificationSettingStatus::Enabled];
        yield 'string true' => ['true', NotificationSettingStatus::Enabled];
        yield 'string f' => ['f', NotificationSettingStatus::Disabled];
        yield 'null' => [null, NotificationSettingStatus::Disabled];
    }
}
