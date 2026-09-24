<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationCommand;
use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationHandler;
use App\Modules\Notifications\Application\Exception\CentrifugoPublishException;
use App\Modules\Notifications\Public\Event\NotificationRealtimeRequestedEvent;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\Exception\RetryableOutboxException;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Psr\Log\LoggerInterface;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job realtime-доставки. Временная недоступность Centrifugo
 * (CentrifugoPublishException::isTransient) переводится в повторяемую ошибку outbox, прочее
 * пробрасывается терминально (доставка -> failed). Событие грузится по идентификатору доставки.
 */
final class PublishRealtimeNotificationJob extends JobHandler
{
    public function invoke(
        string $outboxDeliveryId,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        PublishRealtimeNotificationHandler $publishRealtimeNotificationHandler,
        LoggerInterface $logger,
    ): void {
        $notificationRealtimeRequested = $outboxMessageLoader->load(
            outboxDeliveryId: $outboxDeliveryId,
            expectedMessageClass: NotificationRealtimeRequestedEvent::class,
        );

        try {
            $commandBus->dispatch(
                command: new PublishRealtimeNotificationCommand(
                    userId: $notificationRealtimeRequested->userId,
                    type: $notificationRealtimeRequested->type,
                    title: $notificationRealtimeRequested->title,
                    body: $notificationRealtimeRequested->body,
                    action: $notificationRealtimeRequested->action,
                    actor: $notificationRealtimeRequested->actor,
                    createdAt: $notificationRealtimeRequested->createdAt,
                ),
                handler: $publishRealtimeNotificationHandler->handle(...),
            );
        } catch (\Throwable $exception) {
            $isTransient = $exception instanceof CentrifugoPublishException && $exception->isTransient();
            $logContext = [
                'outboxDeliveryId' => $outboxDeliveryId,
                'errorClass' => $exception::class,
            ];

            if ($isTransient) {
                $logger->warning(message: 'Временная ошибка realtime-доставки, запланирован повтор.', context: $logContext);

                throw new RetryableOutboxException(message: 'Не удалось опубликовать realtime-уведомление.');
            }

            $logger->error(message: 'Ошибка realtime-доставки.', context: $logContext);

            throw $exception;
        }
    }
}
