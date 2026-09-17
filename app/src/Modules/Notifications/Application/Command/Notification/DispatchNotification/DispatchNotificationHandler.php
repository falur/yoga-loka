<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Notification\DispatchNotification;

use App\Modules\Notifications\Application\Contract\NotificationTypeCatalogContract;
use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Public\Event\NotificationPushRequestedEvent;
use App\Modules\Notifications\Public\Event\NotificationRealtimeRequestedEvent;
use App\Modules\Notifications\Domain\Collection\NotificationSettingCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Public\Dto\NotificationChannelCollection;
use App\Modules\Notifications\Public\Enum\NotificationChannel as PublicNotificationChannel;
use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Фоновая рассылка: после commit-а источника решает каналы доставки, идемпотентно создаёт инбокс и
 * стейджит push/realtime. Идемпотентность — по notifications.outbox_id (повтор того же события при
 * включённом database — полный no-op).
 */
final readonly class DispatchNotificationHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private NotificationSettingRepository $notificationSettingRepository,
        private NotificationTypeCatalogContract $typeCatalog,
        private IntegrationEventStoreContract $integrationEventStore,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(DispatchNotificationCommand $command): void
    {
        $outboxId = NotificationOutboxId::fromString($command->outboxId);

        if ($this->notificationRepository->findByOutboxId($outboxId) !== null) {
            $this->logger->debug(message: 'Рассылка уведомления уже выполнена, пропуск.', context: [
                'outboxId' => $command->outboxId,
            ]);

            return;
        }

        $userId = UserId::fromString($command->userId);
        $type = NotificationTypeCode::fromString($command->type);
        $defaults = $this->typeCatalog->get($type)->defaultChannels();
        $settings = $this->notificationSettingRepository->findForUserAndType(userId: $userId, type: $type);

        $notifications = new NotificationCollection();

        if ($this->channelEnabled(channel: NotificationChannel::Database, settings: $settings, defaults: $defaults)) {
            $notifications->push($this->createInbox(
                command: $command,
                outboxId: $outboxId,
                userId: $userId,
                type: $type,
            ));
        }

        if ($this->channelEnabled(channel: NotificationChannel::Push, settings: $settings, defaults: $defaults)) {
            $this->stagePush($command);
        }

        if ($this->channelEnabled(channel: NotificationChannel::Realtime, settings: $settings, defaults: $defaults)) {
            $this->stageRealtime($command);
        }

        // Единственный прогон сценария: он уносит в базу и созданный инбокс, и события outbox,
        // поставленные в ту же запись публичным контрактом Outbox. Инбокса могло и не быть —
        // прогон нужен и тогда, потому что каналы push и realtime уже застейджены.
        $this->notificationRepository->saveAll($notifications);
    }

    private function channelEnabled(
        NotificationChannel $channel,
        NotificationSettingCollection $settings,
        NotificationChannelCollection $defaults,
    ): bool {
        $setting = $settings->first(
            static fn(NotificationSetting $candidate): bool => $candidate->channel === $channel,
        );
        // Каналы по умолчанию приходят из публичного определения вида: доменный канал сверяем с
        // ними по строковому значению варианта.
        $enabled = $setting !== null
            ? $setting->isEnabled()
            : $defaults->includes(PublicNotificationChannel::from($channel->value));

        $this->logger->debug(message: 'Решение по каналу уведомления.', context: [
            'channel' => $channel->value,
            'enabled' => $enabled,
            'source' => $setting !== null ? 'setting' : 'default',
        ]);

        return $enabled;
    }

    private function createInbox(
        DispatchNotificationCommand $command,
        NotificationOutboxId $outboxId,
        UserId $userId,
        NotificationTypeCode $type,
    ): Notification {
        $notification = Notification::create(
            outboxId: $outboxId,
            userId: $userId,
            type: $type,
            title: NotificationTitle::fromString($command->title),
            body: NotificationBody::fromString($command->body),
            action: $this->action($command->action),
            actor: $this->actor($command->actor),
            triggeredAt: new \DateTimeImmutable($command->createdAt),
        );

        $this->logger->debug(message: 'Инбокс уведомления создан.', context: [
            'notificationId' => $notification->id->value(),
        ]);

        return $notification;
    }

    private function stagePush(DispatchNotificationCommand $command): void
    {
        $outboxEventId = $this->integrationEventStore->add(new NotificationPushRequestedEvent(
            userId: $command->userId,
            type: $command->type,
            title: $command->title,
            body: $command->body,
            action: $command->action,
            actor: $command->actor,
            createdAt: $command->createdAt,
        ));

        $this->logger->debug(message: 'Push-доставка застейджена.', context: [
            'outboxId' => $outboxEventId,
            'type' => $command->type,
        ]);
    }

    private function stageRealtime(DispatchNotificationCommand $command): void
    {
        $outboxEventId = $this->integrationEventStore->add(new NotificationRealtimeRequestedEvent(
            userId: $command->userId,
            type: $command->type,
            title: $command->title,
            body: $command->body,
            action: $command->action,
            actor: $command->actor,
            createdAt: $command->createdAt,
        ));

        $this->logger->debug(message: 'Realtime-доставка застейджена.', context: [
            'outboxId' => $outboxEventId,
            'type' => $command->type,
        ]);
    }

    private function action(NotificationActionDto|null $payload): NotificationAction
    {
        if ($payload === null) {
            return NotificationAction::none();
        }

        return NotificationAction::linkTo(actionType: $payload->actionType, actionId: $payload->actionId);
    }

    private function actor(NotificationActorDto|null $payload): NotificationActor
    {
        if ($payload === null) {
            return NotificationActor::none();
        }

        return NotificationActor::of(
            userId: UserId::fromString($payload->id),
            name: $payload->name,
            avatarMediaId: $payload->avatarMediaId,
        );
    }
}
