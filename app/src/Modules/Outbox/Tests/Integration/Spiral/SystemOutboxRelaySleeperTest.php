<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Tests\Integration\Spiral;

use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;
use App\Modules\Outbox\Infrastructure\Relay\SystemOutboxRelaySleeper;
use PHPUnit\Framework\TestCase;

final class SystemOutboxRelaySleeperTest extends TestCase
{
    public function testSleepBlocksForRequestedSeconds(): void
    {
        // VO гарантирует минимум 1с; замены sleep в продакшене нет, поэтому реальный
        // sleep(1) в одном изолированном Unit-тесте приемлем (без размножения по suite).
        $startedAt = \microtime(true);
        new SystemOutboxRelaySleeper()->sleep(OutboxRelaySleepSeconds::fromInt(1));
        $elapsedSeconds = \microtime(true) - $startedAt;

        self::assertGreaterThanOrEqual(0.9, $elapsedSeconds);
    }
}
