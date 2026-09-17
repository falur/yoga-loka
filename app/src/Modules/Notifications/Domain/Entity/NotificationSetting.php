<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Entity;

use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Domain\ValueObject\NotificationSettingId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Пользовательская настройка «вид × канал».
 */
final class NotificationSetting
{
    use HasTimestamps;

    public private(set) NotificationSettingId $id;

    public private(set) UserId $userId;

    public private(set) NotificationTypeCode $type;

    public private(set) NotificationChannel $channel;

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

    public static function restore(
        NotificationSettingId $id,
        UserId $userId,
        NotificationTypeCode $type,
        NotificationChannel $channel,
        NotificationSettingStatus $status,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $setting = new self();
        $setting->id = $id;
        $setting->userId = $userId;
        $setting->type = $type;
        $setting->channel = $channel;
        $setting->status = $status;
        $setting->createdAt = $createdAt;
        $setting->updatedAt = $updatedAt;

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
