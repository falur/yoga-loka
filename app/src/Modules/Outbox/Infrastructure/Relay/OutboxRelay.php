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
use App\Modules\Outbox\Infrastructure\Queue\OutboxQueuePublisher;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use App\Shared\Infrastructure\Configuration\Outbox\OutboxConfig;
use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
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
        private OutboxEventRepository $outboxEventRepository,
        private OutboxQueuePublisher $outboxQueuePublisher,
        private OutboxConfig $outboxConfig,
        private DatabaseInterface $database,
        private EntityManagerInterface $entityManager,
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
            $claimedOutboxEvents = $this->outboxEventRepository->findPendingForRelay(
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
                $this->entityManager->persist($claimedOutboxEvent);

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

            $this->entityManager->run();

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

            // Sync-Job исполняется в момент push в том же процессе и через тот же identity map,
            // поэтому interceptor успевает увести нашу же Entity из publishing (handled/failed/
            // queued). Переводим в queued только если событие всё ещё publishing, иначе оставляем
            // выставленный sync-Job статус нетронутым.
            if ($storedOutboxEvent->status !== OutboxEventStatus::Publishing) {
                $this->logger->debug(message: 'Outbox relay не стал менять статус после push.', context: [
                    'outboxId' => $storedOutboxEvent->id->value(),
                    'outboxType' => $storedOutboxEvent->type->value(),
                ]);

                return true;
            }

            $storedOutboxEvent->markQueued($now);
            $this->entityManager->persist($storedOutboxEvent);
            $this->entityManager->run();

            $this->logger->debug(message: 'Outbox relay поставил событие в очередь.', context: [
                'outboxId' => $storedOutboxEvent->id->value(),
                'outboxType' => $storedOutboxEvent->type->value(),
                'jobClass' => $outboxJobClass,
            ]);

            return true;
        } catch (\Throwable $exception) {
            // Тот же sync-сценарий, что и на успешной ветке: interceptor мог уже зафиксировать
            // исход Job на нашей Entity (failed/queued/handled) и пробросить исключение дальше.
            // Тогда не перезаписываем его статус ошибкой push.
            if ($storedOutboxEvent->status !== OutboxEventStatus::Publishing) {
                $this->logger->debug(message: 'Outbox relay не стал записывать ошибку публикации после sync Job.', context: [
                    'outboxId' => $storedOutboxEvent->id->value(),
                    'outboxType' => $storedOutboxEvent->type->value(),
                    'errorClass' => $exception::class,
                ]);

                return false;
            }

            $storedOutboxEvent->recordPublishFailure(
                lastError: OutboxLastError::fromThrowable($exception),
                outboxMaxAttempts: $this->outboxMaxAttempts(),
                availableAt: $now->modify(\sprintf('+%d seconds', $this->outboxConfig->publishRetryDelaySeconds)),
                now: $now,
            );
            $this->entityManager->persist($storedOutboxEvent);
            $this->entityManager->run();

            $publishFailureContext = [
                'outboxId' => $storedOutboxEvent->id->value(),
                'outboxType' => $storedOutboxEvent->type->value(),
                'status' => $storedOutboxEvent->status->value,
                'errorClass' => $exception::class,
            ];

            // Уровень лога — по фактическому переходу статуса. Переход в Failed (исчерпаны
            // попытки) — нарушение инварианта доставки, поэтому ERROR. Возврат в Pending —
            // реальная инфраструктурная ошибка push с повтором, поэтому WARN.
            if ($storedOutboxEvent->isFinal()) {
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
