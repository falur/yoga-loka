<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Dto;

/**
 * Примитивное представление перехода (deep-link) в payload интеграционных событий и команд.
 * Публичный конструктор из двух строк — чтобы ValinorOutboxMessageSerializer восстанавливал его
 * без приватных фабрик. null на месте этого DTO означает «перехода нет».
 */
final readonly class NotificationActionDto
{
    public function __construct(
        public string $actionType,
        public string $actionId,
    ) {}
}
