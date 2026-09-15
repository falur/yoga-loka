<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Public\Dto;

/**
 * Конверт очереди: в задачу уходит не полный payload события, а только идентификатор записи
 * outbox и её тип. Сам payload остаётся в таблице outbox_events и читается загрузчиком.
 */
final readonly class OutboxEnvelopeDto
{
    public function __construct(
        public string $outboxEventId,
        public string $outboxEventType,
    ) {}
}
