<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationCommand;
use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationHandler;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job фоновой рассылки. Грузит NotificationRequestedEvent из outbox и запускает
 * DispatchNotificationCommand. Job — граница системы, поэтому здесь разрешён try-catch с
 * классификацией: доменная ошибка (неизвестный вид, битый payload) терминальна и пробрасывается
 * (outbox -> failed), прочий сбой (инфраструктура БД) временный -> RetryException (повтор).
 * Идентификатор события используется как стабильный ключ идемпотентности рассылки.
 */
final class DispatchNotificationJob extends JobHandler
{
    public function invoke(
        OutboxEnvelopeDto $payload,
        string $id,
        IntegrationEventLoaderContract $integrationEventLoader,
        CommandBusInterface $commandBus,
        DispatchNotificationHandler $dispatchNotificationHandler,
        LoggerInterface $logger,
    ): void {
        $notificationRequested = $integrationEventLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedEventClass: NotificationRequestedEvent::class,
        );

        try {
            $commandBus->dispatch(
                command: new DispatchNotificationCommand(
                    outboxId: $payload->outboxEventId,
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
                'outboxId' => $payload->outboxEventId,
                'jobId' => $id,
                'errorClass' => $domainException::class,
            ]);

            throw $domainException;
        } catch (\Throwable $exception) {
            // Временный инфраструктурный сбой (БД) -> WARN и RetryException, повтор ожидаем.
            $logger->warning(message: 'Временная ошибка рассылки уведомления, запланирован повтор.', context: [
                'outboxId' => $payload->outboxEventId,
                'jobId' => $id,
                'errorClass' => $exception::class,
            ]);

            throw new RetryException(reason: 'Не удалось выполнить рассылку уведомления.');
        }
    }
}
