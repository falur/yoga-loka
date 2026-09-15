<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Entity;

use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Domain\ValueObject\NotificationSettingId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast\NotificationSettingStatusTypecast;
use App\Modules\Notifications\Repository\NotificationSettingRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

/**
 * Пользовательская настройка «вид × канал». Статус хранится в boolean-колонке enabled через
 * отдельный typecast.
 */
#[Entity(
    role: 'notificationSetting',
    table: 'notification_settings',
    repository: NotificationSettingRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class NotificationSetting
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: NotificationSettingId::class)]
    public private(set) NotificationSettingId $id;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'string(255)', typecast: NotificationTypeCode::class)]
    public private(set) NotificationTypeCode $type;

    #[Column(type: 'string(32)', typecast: NotificationChannel::class)]
    public private(set) NotificationChannel $channel;

    #[Column(type: 'boolean', name: 'enabled', typecast: NotificationSettingStatusTypecast::class)]
    public private(set) NotificationSettingStatus $status;

    public static function create(
        UserId $userId,
        NotificationTypeCode $type,
        NotificationChannel $channel,
        NotificationSettingStatus $status,
    ): self {
        $setting = new self();
        $setting->id = NotificationSettingId::generate();
        $setting->userId = $userId;
        $setting->type = $type;
        $setting->channel = $channel;
        $setting->status = $status;
        $setting->initializeTimestamps();

        return $setting;
    }

    public function enable(): void
    {
        $this->status = NotificationSettingStatus::Enabled;
        $this->touch();
    }

    public function disable(): void
    {
        $this->status = NotificationSettingStatus::Disabled;
        $this->touch();
    }

    public function isEnabled(): bool
    {
        return $this->status === NotificationSettingStatus::Enabled;
    }
}
