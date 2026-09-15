<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Client;

/**
 * Presence-статистика канала Centrifugo: число активных подключений (num_clients).
 * Для решения «онлайн ли пользователь» нужно только num_clients, поэтому num_users из ответа
 * не хранится (мёртвый код). Это не VO — equals()/JsonSerializable не нужны, по образцу FcmPushResult.
 */
final readonly class CentrifugoPresenceStats
{
    public function __construct(
        public int $numClients,
    ) {}
}
