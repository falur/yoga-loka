<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Dto;

/**
 * Примитивное представление автора-инициатора (снимок профиля) в payload интеграционных событий и
 * команд. Публичный конструктор — чтобы сериализатор пакета outbox восстанавливал его без
 * приватных фабрик. null на месте этого DTO означает «автора нет» (системное уведомление).
 * avatarMediaId = null, если у автора нет аватара; иначе — id медиа-аватара, по которому
 * потребитель соберёт полное медиа (MediaDto) на границе показа. Снимок нужен, чтобы клиент показал
 * автора без отдельного запроса к профилю.
 */
final readonly class NotificationActorDto
{
    public function __construct(
        public string $id,
        public string $name,
        public string|null $avatarMediaId,
    ) {}
}
