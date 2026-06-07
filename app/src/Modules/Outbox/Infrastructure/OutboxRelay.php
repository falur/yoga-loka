<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure;

use App\Modules\Outbox\Domain\Collection\OutboxEventCollection;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\Enum\OutboxEventStatus;
use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Repository\InvalidOutboxPendingRow;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use App\Shared\Infrastructure\Configuration\Outbox\OutboxConfig;
use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\Exception\TypecastException;
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
            $claimedOutboxEvents = $this->fetchPendingForRelay(
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
                $this->outboxEventRepository->save($claimedOutboxEvent);

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

    private function fetchPendingForRelay(
        OutboxRelayBatchSize $outboxRelayBatchSize,
        \DateTimeImmutable $now,
    ): OutboxEventCollection {
        try {
            return $this->outboxEventRepository->findPendingForRelay(
                outboxRelayBatchSize: $outboxRelayBatchSize,
                now: $now,
            );
        } catch (TypecastException $exception) {
            // Повреждённые данные в outbox — реальная проблема инфраструктуры данных,
            // поэтому вход в recovery логируется на уровне WARN с первопричиной.
            $this->logger->warning(message: 'Outbox relay вошёл в восстановление после ошибки гидрации pending-событий.', context: [
                'errorClass' => $exception::class,
                'errorMessage' => $exception->getMessage(),
            ]);

            $failedRowsCount = $this->markInvalidPendingRowsAsFailed(
                outboxRelayBatchSize: $outboxRelayBatchSize,
                now: $now,
            );

            // Если ни одной повреждённой строки не нашлось, парсер сырых строк не распознал
            // первопричину. Повторная выборка упадёт той же ошибкой, поэтому пробрасываем
            // исходное исключение, чтобы не потерять первопричину инцидента.
            if ($failedRowsCount === 0) {
                throw $exception;
            }

            // Повторная выборка не захватит помеченные failed строки: они выпадают из
            // pending/publishing, поэтому relay не зацикливается на повреждённых данных.
            return $this->outboxEventRepository->findPendingForRelay(
                outboxRelayBatchSize: $outboxRelayBatchSize,
                now: $now,
            );
        }
    }

    private function markInvalidPendingRowsAsFailed(
        OutboxRelayBatchSize $outboxRelayBatchSize,
        \DateTimeImmutable $now,
    ): int {
        $failedRowsCount = 0;

        foreach ($this->outboxEventRepository->pendingForRelayRows(outboxRelayBatchSize: $outboxRelayBatchSize, now: $now) as $pendingRow) {
            if (!$pendingRow instanceof InvalidOutboxPendingRow) {
                continue;
            }

            $markedRowsCount = $this->outboxEventRepository->markRowFailedById(
                outboxEventId: $pendingRow->rawOutboxEventId,
                lastError: $pendingRow->lastError,
                now: $now,
            );

            // Прогресс считаем только по факту затронутой строки, а не по факту распознанной.
            // Если CAS не затронул строку (id не совпал или гонка статуса увела её из
            // pending/publishing), пометить failed не удалось — это не прогресс, иначе recovery
            // ложно решит, что вычистил данные, и зациклится на повторной падающей выборке.
            if ($markedRowsCount === 0) {
                // Не смогли пометить распознанную битую строку — это реальная проблема
                // инфраструктуры данных, по правилу логирования уровень WARN. Инцидент должен
                // быть виден, а не молча копиться до остановки цикла.
                $this->logger->warning(message: 'Outbox relay не смог пометить повреждённую строку failed.', context: [
                    'outboxId' => $pendingRow->rawOutboxEventId,
                    'lastError' => $pendingRow->lastError->value(),
                ]);

                continue;
            }

            $failedRowsCount++;

            // Пометка строки failed из-за повреждённых данных — реальная проблема
            // инфраструктуры данных, по правилу логирования это уровень WARN.
            $this->logger->warning(message: 'Outbox relay пометил повреждённую строку failed.', context: [
                'outboxId' => $pendingRow->rawOutboxEventId,
                'lastError' => $pendingRow->lastError->value(),
            ]);
        }

        return $failedRowsCount;
    }

    private function publish(StoredOutboxEvent $storedOutboxEvent, \DateTimeImmutable $now): bool
    {
        try {
            $outboxJobClass = $this->outboxQueuePublisher->publish($storedOutboxEvent);

            if ($this->outboxEventRepository->markQueuedIfPublishing(outboxEventId: $storedOutboxEvent->id, now: $now)) {
                $this->logger->debug(message: 'Outbox relay поставил событие в очередь.', context: [
                    'outboxId' => $storedOutboxEvent->id->value(),
                    'outboxType' => $storedOutboxEvent->type->value(),
                    'jobClass' => $outboxJobClass,
                ]);

                return true;
            }

            $this->logger->debug(message: 'Outbox relay не стал менять статус после push.', context: [
                'outboxId' => $storedOutboxEvent->id->value(),
                'outboxType' => $storedOutboxEvent->type->value(),
            ]);

            return true;
        } catch (\Throwable $exception) {
            if ($this->syncJobAlreadyRecordedFailure($storedOutboxEvent)) {
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
            $this->outboxEventRepository->save($storedOutboxEvent);
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

    private function syncJobAlreadyRecordedFailure(StoredOutboxEvent $storedOutboxEvent): bool
    {
        // Sync-драйвер выполняет Job прямо в момент push, поэтому общий queue interceptor мог
        // уже записать статус (handled/failed/queued), пока ORM identity map держит старую
        // publishing-Entity. Тогда перезаписывать статус ошибкой push нельзя — нужно свежее
        // чтение из БД. Ранний возврат гарантирует, что этот дополнительный SELECT на горячем
        // пути выполняется ТОЛЬКО для sync-драйвера; в проде очередь async (RabbitMQ), и проверка
        // отсекается здесь без запроса. Не выносить findFreshStatusById за пределы sync-гарда.
        if (!$this->outboxQueuePublisher->usesSyncConnection()) {
            return false;
        }

        return $this->outboxEventRepository->findFreshStatusById($storedOutboxEvent->id) !== OutboxEventStatus::Publishing;
    }

    private function outboxMaxAttempts(): OutboxMaxAttempts
    {
        return OutboxMaxAttempts::fromInt($this->outboxConfig->maxAttempts);
    }
}
