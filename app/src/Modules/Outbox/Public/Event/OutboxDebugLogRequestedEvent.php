<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Public\Event;

use App\Modules\Outbox\Public\Contract\IntegrationEvent;

/**
 * Интеграционное событие диагностики outbox: запрошена отладочная запись в журнал. Payload —
 * только примитивы и DateTimeImmutable, чтобы сериализатор восстановил событие без кастомных фабрик.
 */
final readonly class OutboxDebugLogRequestedEvent implements IntegrationEvent
{
    public function __construct(
        public string $text,
        public \DateTimeImmutable $createdAt,
    ) {}
}
