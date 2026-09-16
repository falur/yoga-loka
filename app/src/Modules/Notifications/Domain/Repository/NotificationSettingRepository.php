<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationSettingCollection;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Хранение персональных настроек «вид × канал». Корень агрегата — NotificationSetting,
 * внутренних сущностей у него нет.
 */
interface NotificationSettingRepository
{
    /**
     * Настройки пользователя по одному виду уведомления — по строке на канал, у которого есть
     * персональное значение.
     */
    public function findForUserAndType(UserId $userId, NotificationTypeCode $type): NotificationSettingCollection;

    /**
     * Все персональные настройки пользователя.
     */
    public function findForUser(UserId $userId): NotificationSettingCollection;

    /**
     * Персональная настройка одной пары «вид × канал». Значения по умолчанию накладывает сценарий.
     */
    public function findOneForUserTypeChannel(
        UserId $userId,
        NotificationTypeCode $type,
        NotificationChannel $channel,
    ): NotificationSetting|null;

    /**
     * Сохраняет набор настроек одним прогоном на весь набор, а не по прогону на настройку:
     * правка нескольких пар «вид × канал» уходит в базу целиком или не уходит вовсе.
     */
    public function saveAll(NotificationSettingCollection $notificationSettings): void;
}
