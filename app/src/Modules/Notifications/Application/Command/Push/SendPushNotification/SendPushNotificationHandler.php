<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Push\SendPushNotification;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Notifications\Application\Contract\FcmPushSenderContract;
use App\Modules\Notifications\Application\Contract\OnlinePresenceContract;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Application\Dto\NotificationPush;
use App\Modules\Notifications\Application\Dto\NotificationPushActorPayload;
use App\Modules\Notifications\Domain\Collection\NotificationDeviceTokenCollection;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Repository\NotificationDeviceTokenRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

/**
 * Отправляет push на активные токены получателя и удаляет токены, признанные FCM невалидными.
 * Без #[Transactional]: внешний вызов FCM не оборачиваем в транзакцию; удаление токенов
 * фиксируется отдельным run().
 */
final readonly class SendPushNotificationHandler
{
    public function __construct(
        private NotificationDeviceTokenRepository $notificationDeviceTokenRepository,
        private FcmPushSenderContract $fcmPushSender,
        private OnlinePresenceContract $onlinePresence,
        private MediaContract $media,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(SendPushNotificationCommand $command): void
    {
        $deviceTokens = $this->notificationDeviceTokenRepository->findAllForUser(UserId::fromString($command->userId));

        if ($deviceTokens->isEmpty()) {
            $this->logger->debug(message: 'Нет push-токенов для отправки.', context: ['userId' => $command->userId]);

            return;
        }

        if ($this->onlinePresence->isOnline(UserId::fromString($command->userId))) {
            $this->logger->debug(message: 'Получатель онлайн, push-уведомление пропущено.', context: ['userId' => $command->userId]);

            return;
        }

        $result = $this->fcmPushSender->send(
            push: new NotificationPush(
                title: $command->title,
                body: $command->body,
                action: $command->action,
                actor: $this->actorPayload($command->actor),
            ),
            tokens: $this->tokenValues($deviceTokens),
        );

        $this->logger->debug(message: 'Push отправлен.', context: [
            'userId' => $command->userId,
            'tokens' => $deviceTokens->count(),
            'invalidTokens' => \count($result->invalidTokens),
        ]);

        $this->removeInvalidTokens(deviceTokens: $deviceTokens, invalidTokenValues: $result->invalidTokens);
    }

    /**
     * @param list<string> $invalidTokenValues
     */
    private function removeInvalidTokens(NotificationDeviceTokenCollection $deviceTokens, array $invalidTokenValues): void
    {
        if ($invalidTokenValues === []) {
            return;
        }

        foreach ($deviceTokens as $deviceToken) {
            if (!\in_array(needle: $deviceToken->token->value(), haystack: $invalidTokenValues, strict: true)) {
                continue;
            }

            $this->entityManager->delete($deviceToken);
            $this->logger->debug(message: 'Удалён невалидный push-токен.', context: [
                'deviceTokenId' => $deviceToken->id->value(),
            ]);
        }

        $this->entityManager->run();
    }

    private function actorPayload(NotificationActorDto|null $actor): NotificationPushActorPayload|null
    {
        if ($actor === null) {
            return null;
        }

        return new NotificationPushActorPayload(
            id: $actor->id,
            name: $actor->name,
            avatarUrl: $this->avatarUrl($actor->avatarMediaId),
        );
    }

    /**
     * Аватар автора хранится как id медиа — для push разрешаем его в одну ссылку (original) к моменту
     * отправки через публичный контракт Media. Обращение пакетное: набор из одного идентификатора, и
     * только когда аватар у автора есть. Медиа недоступно или оригинал удалён -> ссылки нет (null),
     * ключ в data не кладётся.
     */
    private function avatarUrl(string|null $avatarMediaId): string|null
    {
        if ($avatarMediaId === null) {
            return null;
        }

        return $this->media->urlsByIds([$avatarMediaId])->get($avatarMediaId)?->original?->url;
    }

    /**
     * @return list<string>
     */
    private function tokenValues(NotificationDeviceTokenCollection $deviceTokens): array
    {
        return $deviceTokens->mapToList(
            static fn(NotificationDeviceToken $deviceToken): string => $deviceToken->token->value(),
        );
    }
}
