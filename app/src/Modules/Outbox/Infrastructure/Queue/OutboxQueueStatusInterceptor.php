<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Queue;

use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxLastError;
use App\Modules\Outbox\Domain\ValueObject\OutboxMaxAttempts;
use App\Modules\Outbox\Repository\OutboxEventRepository;
use App\Shared\Infrastructure\Configuration\Outbox\OutboxConfig;
use Cycle\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Core\CoreInterceptorInterface;
use Spiral\Core\CoreInterface;
use Spiral\Queue\Exception\RetryException;

final readonly class OutboxQueueStatusInterceptor implements CoreInterceptorInterface
{
    public function __construct(
        private OutboxEventRepository $outboxEventRepository,
        private EntityManagerInterface $entityManager,
        private OutboxConfig $outboxConfig,
        private LoggerInterface $logger,
        private OutboxQueueSerializer $outboxQueueSerializer,
    ) {}

    /**
     * @param array<int|string, mixed> $parameters
     */
    #[\Override]
    public function process(string $controller, string $action, array $parameters, CoreInterface $core): mixed
    {
        $outboxEventId = $this->outboxEventIdFromParameters($parameters);

        if ($outboxEventId === null) {
            $this->logger->debug(message: 'Outbox interceptor пропустил обычную задачу без outboxId.', context: [
                'jobClass' => $controller,
            ]);

            return $core->callAction(controller: $controller, action: $action, parameters: $parameters);
        }

        $storedOutboxEvent = $this->outboxEventRepository->findById($outboxEventId);

        if ($storedOutboxEvent === null) {
            $this->logger->warning(message: 'Outbox interceptor не нашёл событие для задачи и не запустил Job.', context: [
                'outboxId' => $outboxEventId->value(),
                'jobClass' => $controller,
            ]);

            return null;
        }

        if ($storedOutboxEvent->isFinal()) {
            $this->logger->debug(message: 'Outbox interceptor пропустил дубль уже финального события.', context: [
                'outboxId' => $storedOutboxEvent->id->value(),
                'outboxType' => $storedOutboxEvent->type->value(),
                'status' => $storedOutboxEvent->status->value,
            ]);

            return null;
        }

        try {
            $result = $core->callAction(controller: $controller, action: $action, parameters: $parameters);
        } catch (\Throwable $exception) {
            $this->recordJobFailure(storedOutboxEvent: $storedOutboxEvent, exception: $exception);

            throw $exception;
        }

        if ($this->eventBecameFinalDuringJob($storedOutboxEvent)) {
            return $result;
        }

        $now = new \DateTimeImmutable();
        $storedOutboxEvent->markHandled($now);
        $this->entityManager->persist($storedOutboxEvent);
        $this->entityManager->run();

        $this->logger->debug(message: 'Outbox interceptor поставил handled.', context: [
            'outboxId' => $storedOutboxEvent->id->value(),
            'outboxType' => $storedOutboxEvent->type->value(),
        ]);

        return $result;
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    private function outboxEventIdFromParameters(array $parameters): OutboxEventId|null
    {
        $outboxQueueHeaders = $this->outboxQueueHeadersFromParameters($parameters);
        $outboxQueueEnvelope = $this->outboxQueueEnvelopeFromParameters($parameters);

        if (
            $outboxQueueHeaders->outboxId !== null
            && $outboxQueueEnvelope !== null
            && (
                $outboxQueueHeaders->outboxId !== $outboxQueueEnvelope->outboxEventId->value()
                || (
                    $outboxQueueHeaders->outboxType !== null
                    && $outboxQueueHeaders->outboxType !== $outboxQueueEnvelope->outboxEventType->value()
                )
            )
        ) {
            $this->logger->warning(message: 'Outbox interceptor обнаружил несовпадение outboxId в headers и payload.', context: [
                'headerOutboxId' => $outboxQueueHeaders->outboxId,
                'payloadOutboxId' => $outboxQueueEnvelope->outboxEventId->value(),
                'headerOutboxType' => $outboxQueueHeaders->outboxType,
                'payloadOutboxType' => $outboxQueueEnvelope->outboxEventType->value(),
            ]);

            throw new \UnexpectedValueException('Outbox interceptor получил разные outbox-данные в headers и payload.');
        }

        if ($outboxQueueHeaders->outboxId !== null) {
            return OutboxEventId::fromString($outboxQueueHeaders->outboxId);
        }

        return $outboxQueueEnvelope?->outboxEventId;
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    private function outboxQueueHeadersFromParameters(array $parameters): OutboxQueueHeaders
    {
        if (!\array_key_exists(key: 'headers', array: $parameters) || !\is_array($parameters['headers'])) {
            return new OutboxQueueHeaders(
                outboxId: null,
                outboxType: null,
            );
        }

        return OutboxQueueHeaders::fromHeaders($parameters['headers']);
    }

    /**
     * @param array<int|string, mixed> $parameters
     */
    private function outboxQueueEnvelopeFromParameters(array $parameters): OutboxQueueEnvelope|null
    {
        if (!\array_key_exists(key: 'payload', array: $parameters)) {
            return null;
        }

        $payload = $parameters['payload'];

        if ($payload instanceof OutboxQueueEnvelope) {
            return $payload;
        }

        if (!\is_array($payload)) {
            return null;
        }

        if (!\array_key_exists(key: OutboxQueueHeaders::OUTBOX_ID, array: $payload)
            || !\array_key_exists(key: OutboxQueueHeaders::OUTBOX_TYPE, array: $payload)) {
            $this->logger->warning(message: 'Outbox interceptor получил payload-массив без обоих outbox-ключей и не считает его outbox-задачей.', context: [
                'hasOutboxId' => \array_key_exists(key: OutboxQueueHeaders::OUTBOX_ID, array: $payload),
                'hasOutboxType' => \array_key_exists(key: OutboxQueueHeaders::OUTBOX_TYPE, array: $payload),
            ]);

            return null;
        }

        if (!\is_string($payload[OutboxQueueHeaders::OUTBOX_ID]) || !\is_string($payload[OutboxQueueHeaders::OUTBOX_TYPE])) {
            $this->logger->warning(message: 'Outbox interceptor получил payload-массив с нестроковыми outbox-ключами и не считает его outbox-задачей.', context: [
                'outboxIdType' => \get_debug_type($payload[OutboxQueueHeaders::OUTBOX_ID]),
                'outboxTypeType' => \get_debug_type($payload[OutboxQueueHeaders::OUTBOX_TYPE]),
            ]);

            return null;
        }

        return $this->outboxQueueSerializer->envelopeFromTransportPayload([
            OutboxQueueHeaders::OUTBOX_ID => $payload[OutboxQueueHeaders::OUTBOX_ID],
            OutboxQueueHeaders::OUTBOX_TYPE => $payload[OutboxQueueHeaders::OUTBOX_TYPE],
        ]);
    }

    private function recordJobFailure(StoredOutboxEvent $storedOutboxEvent, \Throwable $exception): void
    {
        if ($this->eventBecameFinalDuringJob($storedOutboxEvent)) {
            return;
        }

        $now = new \DateTimeImmutable();
        $lastError = OutboxLastError::fromThrowable($exception);

        if ($exception instanceof RetryException) {
            $storedOutboxEvent->recordJobRetry(
                lastError: $lastError,
                outboxMaxAttempts: $this->outboxMaxAttempts(),
                now: $now,
            );
        } else {
            $storedOutboxEvent->markFailed(
                lastError: $lastError,
                outboxMaxAttempts: $this->outboxMaxAttempts(),
                now: $now,
            );
        }

        $this->entityManager->persist($storedOutboxEvent);
        $this->entityManager->run();

        $jobFailureContext = [
            'outboxId' => $storedOutboxEvent->id->value(),
            'outboxType' => $storedOutboxEvent->type->value(),
            'status' => $storedOutboxEvent->status->value,
            'attempts' => $storedOutboxEvent->attempts->value(),
            'errorClass' => $exception::class,
        ];

        // Уровень лога — по фактическому переходу статуса, а не по типу исключения:
        // RetryException на последней попытке тоже даёт failed. Окончательный переход
        // в Failed — нарушение инварианта доставки (потеря внешнего действия), поэтому
        // ERROR. Возврат в Queued (событие остаётся в обороте) — штатный retry, DEBUG.
        if ($storedOutboxEvent->isFinal()) {
            $this->logger->error(message: 'Outbox interceptor окончательно перевёл событие в failed.', context: $jobFailureContext);

            return;
        }

        $this->logger->debug(message: 'Outbox interceptor зафиксировал ошибку Job и оставил событие на повтор.', context: $jobFailureContext);
    }

    private function outboxMaxAttempts(): OutboxMaxAttempts
    {
        return OutboxMaxAttempts::fromInt($this->outboxConfig->maxAttempts);
    }

    private function eventBecameFinalDuringJob(StoredOutboxEvent $storedOutboxEvent): bool
    {
        // Job (например sync-обработчик в том же процессе) мог сам перевести нашу же Entity
        // в финальный статус, пока выполнялся. Тогда не перезаписываем его handled-ом.
        if (!$storedOutboxEvent->isFinal()) {
            return false;
        }

        $this->logger->debug(message: 'Outbox interceptor не перезаписал ставшее финальным событие.', context: [
            'outboxId' => $storedOutboxEvent->id->value(),
            'outboxType' => $storedOutboxEvent->type->value(),
            'status' => $storedOutboxEvent->status->value,
        ]);

        return true;
    }
}
