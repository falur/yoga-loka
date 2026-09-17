<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Relay;

use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;
use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;
use App\Modules\Outbox\Infrastructure\Exception\OutboxRelayStoppedException;
use App\Modules\Outbox\Infrastructure\Spiral\Configuration\OutboxConfig;
use Psr\Log\LoggerInterface;

final readonly class OutboxRelayWorker implements OutboxRelayWorkerContract
{
    public function __construct(
        private OutboxRelayContract $outboxRelay,
        private OutboxRelayLoopControlContract $outboxRelayLoopControl,
        private OutboxRelaySleeperContract $outboxRelaySleeper,
        private LoggerInterface $logger,
        private OutboxConfig $outboxConfig,
    ) {}

    #[\Override]
    public function runOnce(OutboxRelayBatchSize $outboxRelayBatchSize): int
    {
        return $this->outboxRelay->relay(outboxRelayBatchSize: $outboxRelayBatchSize);
    }

    #[\Override]
    public function runLoop(OutboxRelayBatchSize $outboxRelayBatchSize, OutboxRelaySleepSeconds $outboxRelaySleepSeconds): never
    {
        $retryAttempts = 0;

        while ($this->outboxRelayLoopControl->shouldContinue()) {
            try {
                $publishedCount = $this->outboxRelay->relay(outboxRelayBatchSize: $outboxRelayBatchSize);
                $retryAttempts = 0;

                if ($publishedCount === 0) {
                    // Пауза на пустой очереди. Минимум секунды гарантирует VO
                    // OutboxRelaySleepSeconds, поэтому busy-spin здесь невозможен.
                    $this->outboxRelaySleeper->sleep($outboxRelaySleepSeconds);
                }
            } catch (\Throwable $exception) {
                $retryAttempts++;
                $retryDelaySeconds = $this->calculateRetryDelaySeconds(
                    outboxRelaySleepSeconds: $outboxRelaySleepSeconds,
                    retryAttempts: $retryAttempts,
                );

                $this->logger->warning(message: 'Outbox relay поймал ошибку в цикле и продолжает через паузу.', context: [
                    'errorClass' => $exception::class,
                    'errorMessage' => $exception->getMessage(),
                    'outboxBatchSize' => $outboxRelayBatchSize->value(),
                    'retryAttempts' => $retryAttempts,
                    'retryDelaySeconds' => $retryDelaySeconds->value(),
                ]);

                if ($retryAttempts >= $this->outboxConfig->maxConsecutiveRelayFailures) {
                    $this->logger->error(message: 'Outbox relay остановлен после серии ошибок в постоянном режиме.', context: [
                        'errorClass' => $exception::class,
                        'errorMessage' => $exception->getMessage(),
                        'retryAttempts' => $retryAttempts,
                        'outboxBatchSize' => $outboxRelayBatchSize->value(),
                    ]);

                    throw $exception;
                }

                $this->outboxRelaySleeper->sleep($retryDelaySeconds);
            }
        }

        throw OutboxRelayStoppedException::loopStoppedByExternalControl();
    }

    private function calculateRetryDelaySeconds(
        OutboxRelaySleepSeconds $outboxRelaySleepSeconds,
        int $retryAttempts,
    ): OutboxRelaySleepSeconds {
        $baseDelay = \max($this->outboxConfig->baseRelayRetryDelaySeconds, $outboxRelaySleepSeconds->value());

        $retryDelayMultiplier = $retryAttempts ** 2 - $retryAttempts + 1;

        return OutboxRelaySleepSeconds::fromInt(
            \min($this->outboxConfig->maxRelayRetryDelaySeconds, $baseDelay * $retryDelayMultiplier),
        );
    }
}
