<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationCommand;
use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationHandler;
use App\Modules\Notifications\Application\Message\NotificationRequested;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job фоновой рассылки. Грузит NotificationRequested из outbox и запускает
 * DispatchNotificationCommand. Job — граница системы, поэтому здесь разрешён try-catch с
 * классификацией: доменная ошибка (неизвестный вид, битый payload) терминальна и пробрасывается
 * (outbox -> failed), прочий сбой (инфраструктура БД) временный -> RetryException (повтор).
 * Идентификатор события используется как стабильный ключ идемпотентности рассылки.
 */
final class DispatchNotificationJob extends JobHandler
{
    public function invoke(
        OutboxQueueEnvelope $payload,
        string $id,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        DispatchNotificationHandler $dispatchNotificationHandler,
        LoggerInterface $logger,
    ): void {
        $notificationRequested = $outboxMessageLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedMessageClass: NotificationRequested::class,
        );

        try {
            $commandBus->dispatch(
                command: new DispatchNotificationCommand(
                    outboxId: $payload->outboxEventId->value(),
                    userId: $notificationRequested->userId,
                    type: $notificationRequested->type,
                    title: $notificationRequested->title,
                    body: $notificationRequested->body,
                    action: $notificationRequested->action,
                    actor: $notificationRequested->actor,
                    createdAt: $notificationRequested->createdAt,
                ),
                handler: $dispatchNotificationHandler->handle(...),
            );
        } catch (\DomainException $domainException) {
            // Терминальная ошибка данных/конфигурации (вид не зарегистрирован, битый payload):
            // повтор не поможет -> ERROR и rethrow (outbox -> failed).
            $logger->error(message: 'Терминальная ошибка рассылки уведомления.', context: [
                'outboxId' => $payload->outboxEventId->value(),
                'jobId' => $id,
                'errorClass' => $domainException::class,
            ]);

            throw $domainException;
        } catch (\Throwable $exception) {
            // Временный инфраструктурный сбой (БД) -> WARN и RetryException, повтор ожидаем.
            $logger->warning(message: 'Временная ошибка рассылки уведомления, запланирован повтор.', context: [
                'outboxId' => $payload->outboxEventId->value(),
                'jobId' => $id,
                'errorClass' => $exception::class,
            ]);

            throw new RetryException(reason: 'Не удалось выполнить рассылку уведомления.');
        }
    }
}
