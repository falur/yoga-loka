<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Application\Contract\RealtimeActorPayload;
use App\Modules\Notifications\Application\Contract\RealtimeMediaPayload;
use App\Modules\Notifications\Application\Contract\RealtimeNotificationPayload;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

/**
 * Публикует realtime-уведомление в персональный канал получателя `personal:#user_{userId}`. Аватар
 * автора хранится в снимке как id медиа, поэтому здесь он разрешается в полное медиа через публичный
 * контракт Media (актуальные ссылки к моменту публикации) и кладётся в payload той же формой, что и в
 * HTTP-ответе инбокса. Обращение к соседу пакетное: набор из одного идентификатора, и только когда
 * аватар у автора есть.
 */
final readonly class PublishRealtimeNotificationHandler
{
    public function __construct(
        private CentrifugoServiceContract $centrifugoService,
        private MediaContract $media,
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

    private function actorPayload(NotificationActorDto|null $actor): RealtimeActorPayload|null
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

        $media = $this->media->urlsByIds([$avatarMediaId])->get($avatarMediaId);

        // Аватара нет, если медиа недоступно или оригинал удалён: отдаём null «всё или ничего», без
        // конверсий удалённого оригинала. Заглушку рисует клиент.
        if ($media === null || $media->original === null) {
            return null;
        }

        return RealtimeMediaPayload::fromDto($media);
    }
}
