<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationCommand;
use App\Modules\Notifications\Application\Command\Push\SendPushNotification\SendPushNotificationHandler;
use App\Modules\Notifications\Application\Exception\FcmPushFailedException;
use App\Modules\Notifications\Public\Event\NotificationPushRequestedEvent;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job push-доставки. Временный сбой FCM (FcmPushFailedException::isTransient)
 * переводится в RetryException (повтор), прочее пробрасывается терминально (outbox -> failed).
 */
final class SendPushNotificationJob extends JobHandler
{
    public function invoke(
        OutboxEnvelopeDto $payload,
        string $id,
        IntegrationEventLoaderContract $integrationEventLoader,
        CommandBusInterface $commandBus,
        SendPushNotificationHandler $sendPushNotificationHandler,
        LoggerInterface $logger,
    ): void {
        $notificationPushRequested = $integrationEventLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedEventClass: NotificationPushRequestedEvent::class,
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
                'outboxId' => $payload->outboxEventId,
                'jobId' => $id,
                'errorClass' => $exception::class,
            ];

            if ($isTransient) {
                $logger->warning(message: 'Временная ошибка push-доставки, запланирован повтор.', context: $logContext);

                throw new RetryException(reason: 'Не удалось отправить push-уведомление.');
            }

            $logger->error(message: 'Ошибка push-доставки.', context: $logContext);

            throw $exception;
        }
    }
}
