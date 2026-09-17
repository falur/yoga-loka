<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

/**
 * Публикация realtime-сообщения в канал Centrifugo. Реализация — в Infrastructure.
 */
interface CentrifugoServiceContract
{
    public function publish(string $channel, RealtimeNotificationPayload $payload): void;
}
