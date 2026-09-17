<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Columns\NotificationSettingColumns;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository\CycleNotificationSettingRepository;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast\NotificationSettingStatusTypecast;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Пользовательская настройка «вид × канал». Статус хранится в boolean-колонке enabled через
 * отдельный Typecast (NotificationSettingStatusTypecast) — колонка не сводится к одному
 * примитиву: PostgreSQL отдаёт boolean по-разному в зависимости от драйвера (bool/0-1/'t'-'f'),
 * а встроенный typecast: 'bool' Cycle делает наивный `(bool) $value` (для строки 'f' это даёт
 * true), поэтому нормализация остаётся явным Typecast-классом (правило переноса значений колонок
 * волны E: «формат, который нельзя однозначно выразить типом колонки»).
 */
#[Entity(
    role: 'notificationSetting',
    table: NotificationSettingColumns::TABLE,
    repository: CycleNotificationSettingRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class CycleNotificationSettingEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: NotificationSettingColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: NotificationSettingColumns::USER_ID)]
    public string $userId;

    #[Column(type: 'string(255)', name: NotificationSettingColumns::TYPE)]
    public string $type;

    #[Column(type: 'string(32)', name: NotificationSettingColumns::CHANNEL, typecast: NotificationChannel::class)]
    public NotificationChannel $channel;

    #[Column(type: 'boolean', name: NotificationSettingColumns::ENABLED, typecast: NotificationSettingStatusTypecast::class)]
    public NotificationSettingStatus $status;
}
