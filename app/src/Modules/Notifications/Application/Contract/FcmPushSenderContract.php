<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

/**
 * Отправка push на набор токенов устройства. Реализация — в Infrastructure (FCM).
 */
interface FcmPushSenderContract
{
    /**
     * @param list<string> $tokens
     */
    public function send(NotificationPush $push, array $tokens): FcmPushResult;
}
