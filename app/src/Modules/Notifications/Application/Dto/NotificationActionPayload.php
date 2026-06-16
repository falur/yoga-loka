<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

/**
 * Примитивное представление перехода (deep-link) в payload outbox-сообщений и команд.
 * Публичный конструктор из двух строк — чтобы ValinorOutboxMessageSerializer восстанавливал его
 * без приватных фабрик. null на месте этого DTO означает «перехода нет».
 */
final readonly class NotificationActionPayload
{
    public function __construct(
        public string $actionType,
        public string $actionId,
    ) {}
}
