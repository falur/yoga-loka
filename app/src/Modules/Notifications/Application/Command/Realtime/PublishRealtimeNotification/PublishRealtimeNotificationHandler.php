<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification;

use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Dto\RealtimeNotificationPayload;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

/**
 * Публикует realtime-уведомление в персональный канал получателя `personal:#user_{userId}`.
 */
final readonly class PublishRealtimeNotificationHandler
{
    public function __construct(
        private CentrifugoServiceContract $centrifugoService,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(PublishRealtimeNotificationCommand $command): void
    {
        $channel = \sprintf('personal:#user_%s', $command->userId);

        $this->centrifugoService->publish(
            channel: $channel,
            payload: new RealtimeNotificationPayload(
                type: $command->type,
                title: $command->title,
                body: $command->body,
                action: $command->action,
                actor: $command->actor,
                createdAt: $command->createdAt,
            ),
        );

        $this->logger->debug(message: 'Realtime-уведомление опубликовано.', context: [
            'channel' => $channel,
            'type' => $command->type,
        ]);
    }
}
