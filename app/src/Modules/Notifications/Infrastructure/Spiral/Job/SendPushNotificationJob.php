<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationCommand;
use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationHandler;
use App\Modules\Notifications\Application\Exception\FcmPushFailedException;
use App\Modules\Notifications\Public\Event\NotificationPushRequestedEvent;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\Exception\RetryableOutboxException;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Psr\Log\LoggerInterface;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job push-доставки. Временный сбой FCM (FcmPushFailedException::isTransient)
 * переводится в повторяемую ошибку outbox (повтор), прочее пробрасывается терминально
 * (доставка -> failed). Событие грузится по идентификатору доставки.
 */
final class SendPushNotificationJob extends JobHandler
{
    public function invoke(
        string $outboxDeliveryId,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        SendPushNotificationHandler $sendPushNotificationHandler,
        LoggerInterface $logger,
    ): void {
        $notificationPushRequested = $outboxMessageLoader->load(
            outboxDeliveryId: $outboxDeliveryId,
            expectedMessageClass: NotificationPushRequestedEvent::class,
        );

        try {
            $commandBus->dispatch(
                command: new SendPushNotificationCommand(
                    userId: $notificationPushRequested->userId,
                    title: $notificationPushRequested->title,
                    body: $notificationPushRequested->body,
                    action: $notificationPushRequested->action,
                    actor: $notificationPushRequested->actor,
                ),
                handler: $sendPushNotificationHandler->handle(...),
            );
        } catch (\Throwable $exception) {
            $isTransient = $exception instanceof FcmPushFailedException && $exception->isTransient();
            $logContext = [
                'outboxDeliveryId' => $outboxDeliveryId,
                'errorClass' => $exception::class,
            ];

            if ($isTransient) {
                $logger->warning(message: 'Временная ошибка push-доставки, запланирован повтор.', context: $logContext);

                throw new RetryableOutboxException(message: 'Не удалось отправить push-уведомление.');
            }

            $logger->error(message: 'Ошибка push-доставки.', context: $logContext);

            throw $exception;
        }
    }
}
