<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationCommand;
use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationHandler;
use App\Modules\Notifications\Application\Exception\CentrifugoPublishException;
use App\Modules\Notifications\Public\Event\NotificationRealtimeRequestedEvent;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Dto\OutboxEnvelopeDto;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job realtime-доставки. Временная недоступность Centrifugo
 * (CentrifugoPublishException::isTransient) переводится в RetryException, прочее пробрасывается
 * терминально (outbox -> failed).
 */
final class PublishRealtimeNotificationJob extends JobHandler
{
    public function invoke(
        OutboxEnvelopeDto $payload,
        string $id,
        IntegrationEventLoaderContract $integrationEventLoader,
        CommandBusInterface $commandBus,
        PublishRealtimeNotificationHandler $publishRealtimeNotificationHandler,
        LoggerInterface $logger,
    ): void {
        $notificationRealtimeRequested = $integrationEventLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedEventClass: NotificationRealtimeRequestedEvent::class,
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
                'outboxId' => $payload->outboxEventId,
                'jobId' => $id,
                'errorClass' => $exception::class,
            ];

            if ($isTransient) {
                $logger->warning(message: 'Временная ошибка realtime-доставки, запланирован повтор.', context: $logContext);

                throw new RetryException(reason: 'Не удалось опубликовать realtime-уведомление.');
            }

            $logger->error(message: 'Ошибка realtime-доставки.', context: $logContext);

            throw $exception;
        }
    }
}
