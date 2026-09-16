<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Relay;

use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Infrastructure\Spiral\Queue\OutboxQueuePublisher;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use App\Shared\Infrastructure\Spiral\Configuration\Outbox\OutboxConfig;
use Cycle\Database\DatabaseInterface;
use Psr\Log\LoggerInterface;

final readonly class OutboxRelay implements OutboxRelayContract
{
    // Колонка available_at несёт два разных смысла времени в зависимости от статуса:
    // для publishing — это таймаут аренды захвата (claim-lease, OutboxConfig::claimTimeoutSeconds),
    // после которого упавший процесс отпускает событие обратно в выборку; для pending после
    // неудачного push — backoff до следующей попытки (OutboxConfig::publishRetryDelaySeconds).
    // Дефолты намеренно совпадают (60s), но это разные домены времени и независимые настройки.
    // Если в будущем понадобится разный таймаут — развести через отдельную колонку claimed_until.
    public function __construct(
        private StoredOutboxEventRepository $storedOutboxEventRepository,
        private OutboxQueuePublisher $outboxQueuePublisher,
        private OutboxConfig $outboxConfig,
        private DatabaseInterface $database,
        private LoggerInterface $logger,
    ) {}

    public function relay(OutboxRelayBatchSize $outboxRelayBatchSize, \DateTimeImmutable|null $now = null): int
    {
        $now ??= new \DateTimeImmutable();

        $this->logger->debug(message: 'Outbox relay начал обработку пачки.', context: [
            'batchSize' => $outboxRelayBatchSize->value(),
            'relayAt' => $now->format(\DateTimeInterface::ATOM),
        ]);

        $claimedOutboxEvents = $this->claimForRelay(
            outboxRelayBatchSize: $outboxRelayBatchSize,
            claimUntil: $now->modify(\sprintf('+%d seconds', $this->outboxConfig->claimTimeoutSeconds)),
            now: $now,
        );

        if ($claimedOutboxEvents->isEmpty()) {
            $this->logger->debug(message: 'Outbox relay не нашёл pending-события.');

            return 0;
        }

        $publishedCount = 0;

        foreach ($claimedOutboxEvents as $storedOutboxEvent) {
            if ($this->publish(storedOutboxEvent: $storedOutboxEvent, now: $now)) {
                $publishedCount++;
            }
        }

        return $publishedCount;
    }

    private function claimForRelay(
        OutboxRelayBatchSize $outboxRelayBatchSize,
        \DateTimeImmutable $claimUntil,
        \DateTimeImmutable $now,
    ): OutboxEventCollection {
        return $this->database->transaction(function () use (
            $outboxRelayBatchSize,
            $claimUntil,
            $now,
        ): OutboxEventCollection {
            $claimedOutboxEvents = $this->storedOutboxEventRepository->findPendingForRelay(
                outboxRelayBatchSize: $outboxRelayBatchSize,
                now: $now,
            );

            if ($claimedOutboxEvents->isEmpty()) {
                return $claimedOutboxEvents;
            }

            foreach ($claimedOutboxEvents as $claimedOutboxEvent) {
                $claimedOutboxEvent->claimForPublishing(
                    outboxMaxAttempts: $this->outboxMaxAttempts(),
                    availableAt: $claimUntil,
                    now: $now,
                );

                // Повторный захват по истёкшей claim-аренде, исчерпавший лимит попыток,
                // переводится в failed прямо здесь — нарушение инварианта доставки, ERROR.
                if ($claimedOutboxEvent->isFinal()) {
                    $this->logger->error(message: 'Outbox relay перевёл застрявшее в publishing событие в failed после исчерпания попыток захвата.', context: [
                        'outboxId' => $claimedOutboxEvent->id->value(),
                        'outboxType' => $claimedOutboxEvent->type->value(),
                        'attempts' => $claimedOutboxEvent->attempts->value(),
                    ]);
                }
            }

            // Захват всей пачки фиксируется одним прогоном внутри этой транзакции.
            $this->storedOutboxEventRepository->saveAll($claimedOutboxEvents);

            // Исчерпавшие лимит захвата события уже в failed: на публикацию их не отдаём.
            return $claimedOutboxEvents->reject(
                static fn(StoredOutboxEvent $claimedOutboxEvent): bool => $claimedOutboxEvent->isFinal(),
            );
        });
    }

    private function publish(StoredOutboxEvent $storedOutboxEvent, \DateTimeImmutable $now): bool
    {
        try {
            $outboxJobClass = $this->outboxQueuePublisher->publish($storedOutboxEvent);

            // Sync-Job исполняется в момент push в том же процессе: interceptor успевает увести
            // событие из publishing (handled/failed/queued) через свой собственный findById().
            // StoredOutboxEvent — чистая доменная сущность без Cycle-разметки, поэтому Mapper
            // отдаёт interceptor-у отдельный объект, а не тот же $storedOutboxEvent, что держит
            // relay (в отличие от прежнего Cycle identity map). Перечитываем состояние из
            // репозитория и переводим в queued только если событие всё ещё publishing, иначе
            // оставляем выставленный sync-Job статус нетронутым.
            $currentStoredOutboxEvent = $this->storedOutboxEventRepository->findById($storedOutboxEvent->id)
                ?? $storedOutboxEvent;

            if ($currentStoredOutboxEvent->status !== OutboxEventStatus::Publishing) {
                $this->logger->debug(message: 'Outbox relay не стал менять статус после push.', context: [
                    'outboxId' => $currentStoredOutboxEvent->id->value(),
                    'outboxType' => $currentStoredOutboxEvent->type->value(),
                ]);

                return true;
            }

            $currentStoredOutboxEvent->markQueued($now);
            $this->storedOutboxEventRepository->save($currentStoredOutboxEvent);

            $this->logger->debug(message: 'Outbox relay поставил событие в очередь.', context: [
                'outboxId' => $currentStoredOutboxEvent->id->value(),
                'outboxType' => $currentStoredOutboxEvent->type->value(),
                'jobClass' => $outboxJobClass,
            ]);

            return true;
        } catch (\Throwable $exception) {
            // Тот же sync-сценарий, что и на успешной ветке: interceptor мог уже зафиксировать
            // исход Job через свой собственный findById() (failed/queued/handled) и пробросить
            // исключение дальше. Перечитываем состояние из репозитория, чтобы не перезаписать
            // его статус ошибкой push.
            $currentStoredOutboxEvent = $this->storedOutboxEventRepository->findById($storedOutboxEvent->id)
                ?? $storedOutboxEvent;

            if ($currentStoredOutboxEvent->status !== OutboxEventStatus::Publishing) {
                $this->logger->debug(message: 'Outbox relay не стал записывать ошибку публикации после sync Job.', context: [
                    'outboxId' => $currentStoredOutboxEvent->id->value(),
                    'outboxType' => $currentStoredOutboxEvent->type->value(),
                    'errorClass' => $exception::class,
                ]);

                return false;
            }

            $currentStoredOutboxEvent->recordPublishFailure(
                lastError: OutboxLastError::fromThrowable($exception),
                outboxMaxAttempts: $this->outboxMaxAttempts(),
                availableAt: $now->modify(\sprintf('+%d seconds', $this->outboxConfig->publishRetryDelaySeconds)),
                now: $now,
            );
            $this->storedOutboxEventRepository->save($currentStoredOutboxEvent);

            $publishFailureContext = [
                'outboxId' => $currentStoredOutboxEvent->id->value(),
                'outboxType' => $currentStoredOutboxEvent->type->value(),
                'status' => $currentStoredOutboxEvent->status->value,
                'errorClass' => $exception::class,
            ];

            // Уровень лога — по фактическому переходу статуса. Переход в Failed (исчерпаны
            // попытки) — нарушение инварианта доставки, поэтому ERROR. Возврат в Pending —
            // реальная инфраструктурная ошибка push с повтором, поэтому WARN.
            if ($currentStoredOutboxEvent->isFinal()) {
                $this->logger->error(message: 'Outbox relay окончательно перевёл событие в failed после ошибки push.', context: $publishFailureContext);

                return false;
            }

            $this->logger->warning(message: 'Outbox relay не смог поставить событие в очередь.', context: $publishFailureContext);

            return false;
        }
    }

    private function outboxMaxAttempts(): OutboxMaxAttempts
    {
        return OutboxMaxAttempts::fromInt($this->outboxConfig->maxAttempts);
    }
}
