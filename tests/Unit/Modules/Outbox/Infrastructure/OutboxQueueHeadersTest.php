<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueueHeaders;
use PHPUnit\Framework\TestCase;

final class OutboxQueueHeadersTest extends TestCase
{
    public function testNormalizesStringHeader(): void
    {
        $outboxQueueHeaders = OutboxQueueHeaders::fromHeaders([
            OutboxQueueHeaders::OUTBOX_ID => 'event-id',
            OutboxQueueHeaders::OUTBOX_TYPE => 'event-type',
        ]);

        self::assertSame('event-id', $outboxQueueHeaders->outboxId);
        self::assertSame('event-type', $outboxQueueHeaders->outboxType);
    }

    public function testNormalizesHeaderLines(): void
    {
        $outboxQueueHeaders = OutboxQueueHeaders::fromHeaders([
            OutboxQueueHeaders::OUTBOX_ID => ['first', '', 'second'],
            OutboxQueueHeaders::OUTBOX_TYPE => ['event-type'],
        ]);

        self::assertSame('first,second', $outboxQueueHeaders->outboxId);
        self::assertSame('event-type', $outboxQueueHeaders->outboxType);
    }

    public function testSkipsEmptyAndMissingHeaders(): void
    {
        $outboxQueueHeaders = OutboxQueueHeaders::fromHeaders([
            OutboxQueueHeaders::OUTBOX_ID => [],
        ]);

        self::assertNull($outboxQueueHeaders->outboxId);
        self::assertNull($outboxQueueHeaders->outboxType);
    }
}
