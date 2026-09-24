<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationCommand;
use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationHandler;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\Exception\RetryableOutboxException;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Psr\Log\LoggerInterface;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job фоновой рассылки. Грузит NotificationRequestedEvent по идентификатору
 * доставки и запускает DispatchNotificationCommand. Job — граница системы, поэтому здесь разрешён
 * try-catch с классификацией: доменная ошибка (неизвестный вид, битый payload) терминальна и
 * пробрасывается (доставка -> failed), прочий сбой (инфраструктура БД) временный ->
 * RetryableOutboxException (повтор). Идентификатор доставки используется как стабильный ключ
 * идемпотентности рассылки: повтор той же доставки не создаёт второе уведомление.
 */
final class DispatchNotificationJob extends JobHandler
{
    public function invoke(
        string $outboxDeliveryId,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        DispatchNotificationHandler $dispatchNotificationHandler,
        LoggerInterface $logger,
    ): void {
        $notificationRequested = $outboxMessageLoader->load(
            outboxDeliveryId: $outboxDeliveryId,
            expectedMessageClass: NotificationRequestedEvent::class,
        );

        try {
            $commandBus->dispatch(
                command: new DispatchNotificationCommand(
                    outboxId: $outboxDeliveryId,
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
            // повтор не поможет -> ERROR и rethrow (доставка -> failed).
            $logger->error(message: 'Терминальная ошибка рассылки уведомления.', context: [
                'outboxDeliveryId' => $outboxDeliveryId,
                'errorClass' => $domainException::class,
            ]);

            throw $domainException;
        } catch (\Throwable $exception) {
            // Временный инфраструктурный сбой (БД) -> WARN и RetryableOutboxException, повтор ожидаем.
            $logger->warning(message: 'Временная ошибка рассылки уведомления, запланирован повтор.', context: [
                'outboxDeliveryId' => $outboxDeliveryId,
                'errorClass' => $exception::class,
            ]);

            throw new RetryableOutboxException(message: 'Не удалось выполнить рассылку уведомления.');
        }
    }
}
