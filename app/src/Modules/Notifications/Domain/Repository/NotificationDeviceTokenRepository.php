<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationDeviceTokenCollection;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Хранение push-токенов устройств. Корень агрегата — NotificationDeviceToken, внутренних
 * сущностей у него нет.
 */
interface NotificationDeviceTokenRepository
{
    /**
     * Все токены получателя, новые сверху, — адресаты одной push-отправки.
     */
    public function findAllForUser(UserId $userId): NotificationDeviceTokenCollection;

    /**
     * Токен по его значению без учёта владельца: значение уникально на всю таблицу, поэтому так
     * находится устройство, сменившее аккаунт.
     */
    public function findByToken(DeviceToken $token): NotificationDeviceToken|null;

    /**
     * Токен по значению в пределах владельца. Чужой токен даёт null.
     */
    public function findByTokenForUser(DeviceToken $token, UserId $userId): NotificationDeviceToken|null;

    /**
     * Сохраняет токен своим прогоном: вместе с ним в базу уходит всё, что уже поставлено
     * в текущую запись.
     */
    public function save(NotificationDeviceToken $notificationDeviceToken): void;

    /**
     * Удаляет один токен своим прогоном.
     */
    public function delete(NotificationDeviceToken $notificationDeviceToken): void;

    /**
     * Удаляет набор токенов одним прогоном на весь набор, а не по прогону на токен.
     */
    public function deleteAll(NotificationDeviceTokenCollection $notificationDeviceTokens): void;
}
