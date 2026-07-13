<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

/**
 * Примитивное представление автора-инициатора (снимок профиля) в payload outbox-сообщений и команд.
 * Публичный конструктор — чтобы ValinorOutboxMessageSerializer восстанавливал его без приватных
 * фабрик. null на месте этого DTO означает «автора нет» (системное уведомление). avatarMediaId = null,
 * если у автора нет аватара; иначе — id медиа-аватара, по которому потребитель соберёт полный MediaView
 * на границе показа. Снимок нужен, чтобы клиент показал автора без отдельного запроса к профилю.
 */
final readonly class NotificationActorPayload
{
    public function __construct(
        public string $id,
        public string $name,
        public string|null $avatarMediaId,
    ) {}
}
