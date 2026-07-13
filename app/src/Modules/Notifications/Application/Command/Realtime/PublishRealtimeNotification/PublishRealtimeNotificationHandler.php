<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification;

use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Dto\NotificationActorPayload;
use App\Modules\Notifications\Application\Dto\RealtimeActorPayload;
use App\Modules\Notifications\Application\Dto\RealtimeMediaPayload;
use App\Modules\Notifications\Application\Dto\RealtimeNotificationPayload;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Psr\Log\LoggerInterface;

/**
 * Публикует realtime-уведомление в персональный канал получателя `personal:#user_{userId}`. Аватар
 * автора хранится в снимке как id медиа, поэтому здесь он разрешается в полный MediaView через модуль
 * Media (актуальные ссылки к моменту публикации) и кладётся в payload той же формой, что и в HTTP-ответе
 * инбокса.
 */
final readonly class PublishRealtimeNotificationHandler
{
    public function __construct(
        private CentrifugoServiceContract $centrifugoService,
        private QueryBusInterface $queryBus,
        private FindMediaUrlHandler $findMediaUrlHandler,
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
                actor: $this->actorPayload($command->actor),
                createdAt: $command->createdAt,
            ),
        );

        $this->logger->debug(message: 'Realtime-уведомление опубликовано.', context: [
            'channel' => $channel,
            'type' => $command->type,
        ]);
    }

    private function actorPayload(NotificationActorPayload|null $actor): RealtimeActorPayload|null
    {
        if ($actor === null) {
            return null;
        }

        return new RealtimeActorPayload(
            id: $actor->id,
            name: $actor->name,
            avatar: $this->avatar($actor->avatarMediaId),
        );
    }

    private function avatar(string|null $avatarMediaId): RealtimeMediaPayload|null
    {
        if ($avatarMediaId === null) {
            return null;
        }

        $mediaUrls = $this->queryBus->dispatch(
            query: new FindMediaUrlQuery(mediaId: $avatarMediaId),
            handler: $this->findMediaUrlHandler->handle(...),
        );

        // Аватара нет, если медиа недоступно или оригинал удалён: отдаём null «всё или ничего», без
        // конверсий удалённого оригинала. Заглушку рисует клиент.
        if ($mediaUrls === null || $mediaUrls->original === null) {
            return null;
        }

        return RealtimeMediaPayload::fromView($mediaUrls->toView(id: $avatarMediaId, position: null));
    }
}
