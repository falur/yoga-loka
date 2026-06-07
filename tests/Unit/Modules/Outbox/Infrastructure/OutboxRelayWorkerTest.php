<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;
use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;
use App\Modules\Outbox\Infrastructure\OutboxRelayStoppedException;
use App\Modules\Outbox\Infrastructure\OutboxRelayWorker;
use App\Shared\Infrastructure\Configuration\Outbox\OutboxConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class OutboxRelayWorkerTest extends TestCase
{
    public function testRunLoopContinuesAfterSingleRelayFailure(): void
    {
        $calls = 0;

        $outboxRelay = $this->createMock(OutboxRelayContract::class);
        $outboxRelay->expects(self::exactly(2))
            ->method('relay')
            ->with(self::isInstanceOf(OutboxRelayBatchSize::class))
            ->willReturnCallback(function () use (&$calls): int {
                $calls++;

                if ($calls === 1) {
                    throw new \RuntimeException('Временная ошибка relay для проверки устойчивости цикла');
                }

                return 0;
            });

        $logger = new RecordingLogger();
        $recordingSleeper = new RecordingOutboxRelaySleeper();
        $outboxRelayWorker = new OutboxRelayWorker(
            outboxRelay: $outboxRelay,
            outboxRelayLoopControl: new FixedOutboxRelayLoopControl(iterations: 2),
            outboxRelaySleeper: $recordingSleeper,
            logger: $logger,
            outboxConfig: self::outboxConfig(),
        );

        try {
            $outboxRelayWorker->runLoop(
                outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(3),
                outboxRelaySleepSeconds: OutboxRelaySleepSeconds::fromInt(1),
            );
            self::fail('Ожидалось завершение по лимиту итераций цикла');
        } catch (OutboxRelayStoppedException $exception) {
            self::assertSame('Outbox relay loop остановлен внешним контролем.', $exception->getMessage());
            self::assertSame(2, $calls);
            self::assertSame(1, $logger->warningCount);
        }
    }

    public function testRunLoopStopsAfterTooManyConsecutiveRelayFailures(): void
    {
        $outboxRelay = $this->createMock(OutboxRelayContract::class);
        $outboxRelay->expects(self::exactly(10))
            ->method('relay')
            ->willThrowException(new \RuntimeException('Постоянная ошибка relay.'));

        $logger = new RecordingLogger();
        $recordingSleeper = new RecordingOutboxRelaySleeper();
        $outboxRelayWorker = new OutboxRelayWorker(
            outboxRelay: $outboxRelay,
            outboxRelayLoopControl: new FixedOutboxRelayLoopControl(iterations: 10),
            outboxRelaySleeper: $recordingSleeper,
            logger: $logger,
            outboxConfig: self::outboxConfig(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Постоянная ошибка relay.');

        try {
            $outboxRelayWorker->runLoop(
                outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(3),
                outboxRelaySleepSeconds: OutboxRelaySleepSeconds::fromInt(1),
            );
        } finally {
            self::assertSame(10, $logger->warningCount);
            self::assertSame(1, $logger->errorCount);
        }
    }

    public function testRunLoopSleepsAfterRelayFailureWhenSleepIsEnabled(): void
    {
        $calls = 0;

        $outboxRelay = $this->createMock(OutboxRelayContract::class);
        $outboxRelay->expects(self::exactly(2))
            ->method('relay')
            ->willReturnCallback(function () use (&$calls): int {
                $calls++;

                if ($calls === 1) {
                    throw new \RuntimeException('Временная ошибка relay с паузой.');
                }

                return 1;
            });

        $logger = new RecordingLogger();
        $recordingSleeper = new RecordingOutboxRelaySleeper();
        $outboxRelayWorker = new OutboxRelayWorker(
            outboxRelay: $outboxRelay,
            outboxRelayLoopControl: new FixedOutboxRelayLoopControl(iterations: 2),
            outboxRelaySleeper: $recordingSleeper,
            logger: $logger,
            outboxConfig: self::outboxConfig(),
        );

        try {
            $outboxRelayWorker->runLoop(
                outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(3),
                outboxRelaySleepSeconds: OutboxRelaySleepSeconds::fromInt(1),
            );
            self::fail('Ожидалось завершение по лимиту итераций цикла');
        } catch (OutboxRelayStoppedException $exception) {
            self::assertSame('Outbox relay loop остановлен внешним контролем.', $exception->getMessage());
            self::assertSame(2, $calls);
            self::assertSame(1, $logger->warningCount);
            // После ошибки relay цикл делает паузу через sleeper, не busy-spin.
            self::assertContains(1, $recordingSleeper->sleptSeconds);
        }
    }

    public function testRunLoopStopsAfterConfiguredConsecutiveRelayFailures(): void
    {
        $outboxRelay = $this->createMock(OutboxRelayContract::class);
        $outboxRelay->expects(self::exactly(3))
            ->method('relay')
            ->willThrowException(new \RuntimeException('Постоянная ошибка relay с настроенным порогом.'));

        $logger = new RecordingLogger();
        $recordingSleeper = new RecordingOutboxRelaySleeper();
        $outboxRelayWorker = new OutboxRelayWorker(
            outboxRelay: $outboxRelay,
            outboxRelayLoopControl: new FixedOutboxRelayLoopControl(iterations: 3),
            outboxRelaySleeper: $recordingSleeper,
            logger: $logger,
            outboxConfig: self::outboxConfig(maxConsecutiveRelayFailures: 3),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Постоянная ошибка relay с настроенным порогом.');

        try {
            $outboxRelayWorker->runLoop(
                outboxRelayBatchSize: OutboxRelayBatchSize::fromInt(3),
                outboxRelaySleepSeconds: OutboxRelaySleepSeconds::fromInt(1),
            );
        } finally {
            self::assertSame(3, $logger->warningCount);
            self::assertSame(1, $logger->errorCount);
        }
    }

    private static function outboxConfig(
        int $maxConsecutiveRelayFailures = 10,
        int $baseRelayRetryDelaySeconds = 1,
        int $maxRelayRetryDelaySeconds = 30,
    ): OutboxConfig {
        return new OutboxConfig(
            maxAttempts: 100,
            maxConsecutiveRelayFailures: $maxConsecutiveRelayFailures,
            baseRelayRetryDelaySeconds: $baseRelayRetryDelaySeconds,
            maxRelayRetryDelaySeconds: $maxRelayRetryDelaySeconds,
            claimTimeoutSeconds: 60,
            publishRetryDelaySeconds: 60,
        );
    }
}

final class RecordingOutboxRelaySleeper implements OutboxRelaySleeperContract
{
    /**
     * @var list<int>
     */
    public array $sleptSeconds = [];

    #[\Override]
    public function sleep(OutboxRelaySleepSeconds $seconds): void
    {
        $this->sleptSeconds[] = $seconds->value();
    }
}

final class FixedOutboxRelayLoopControl implements OutboxRelayLoopControlContract
{
    private int $currentIteration = 0;

    public function __construct(
        private readonly int $iterations,
    ) {}

    #[\Override]
    public function shouldContinue(): bool
    {
        $this->currentIteration++;

        return $this->currentIteration <= $this->iterations;
    }
}

final class RecordingLogger extends AbstractLogger
{
    public int $warningCount = 0;
    public int $errorCount = 0;
    public int $debugCount = 0;

    /**
     * @var list<array<string, mixed>>
     */
    private array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];

        match ((string) $level) {
            'warning' => $this->warningCount++,
            'error' => $this->errorCount++,
            'debug' => $this->debugCount++,
            default => null,
        };
    }
}
