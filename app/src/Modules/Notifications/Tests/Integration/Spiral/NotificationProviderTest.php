<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Spiral;

use App\Modules\Notifications\Application\Exception\NotificationTypeRegistryException;
use App\Modules\Notifications\Public\Contract\NotificationContract;
use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Public\Dto\NotificationContentDto;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Shared\Domain\ValueObject\UserId;
use Tests\DatabaseTestCase;
use App\Modules\Notifications\Tests\Unit\Application\Fixture\FixtureNotificationTypeDefinition;
use Tests\Support\Notifications\RecordingOutboxEventStore;

/**
 * Публичный контракт Notifications: отправка раскладывается в сценарий модуля, примитивы публичного
 * DTO становятся доменными значениями (отсутствие перехода и автора — none()), а в outbox уходит
 * ровно одно событие. Незарегистрированный вид даёт ту же ошибку реестра, что и раньше, и событие
 * при этом не стейджится.
 */
final class NotificationProviderTest extends DatabaseTestCase
{
    private const string TYPE = 'chat.message_received';

    private RecordingOutboxEventStore $outboxStore;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->outboxStore = new RecordingOutboxEventStore();
        $this->getContainer()->bindSingleton(IntegrationEventStoreContract::class, $this->outboxStore);
    }

    public function testSendStagesOneEventWithActionAndActor(): void
    {
        $this->registerType(self::TYPE);
        $recipientId = UserId::generate()->value();
        $actorId = UserId::generate()->value();
        $avatarMediaId = UserId::generate()->value();

        $this->notifications()->send(
            recipientUserId: $recipientId,
            content: new NotificationContentDto(
                typeCode: self::TYPE,
                title: 'Новое сообщение',
                body: 'Вам пришло сообщение',
                action: new NotificationActionDto(actionType: 'chat', actionId: '42'),
                actor: new NotificationActorDto(id: $actorId, name: 'Иван', avatarMediaId: $avatarMediaId),
            ),
        );

        $event = $this->stagedEvent();
        self::assertSame($recipientId, $event->userId);
        self::assertSame(self::TYPE, $event->type);
        self::assertSame('Новое сообщение', $event->title);
        self::assertSame('Вам пришло сообщение', $event->body);
        self::assertNotNull($event->action);
        self::assertSame('chat', $event->action->actionType);
        self::assertSame('42', $event->action->actionId);
        self::assertNotNull($event->actor);
        self::assertSame($actorId, $event->actor->id);
        self::assertSame('Иван', $event->actor->name);
        self::assertSame($avatarMediaId, $event->actor->avatarMediaId);
    }

    public function testSendWithoutActionAndActorStagesEventWithoutThem(): void
    {
        $this->registerType('chat.system_notice');

        $this->notifications()->send(
            recipientUserId: UserId::generate()->value(),
            content: new NotificationContentDto(
                typeCode: 'chat.system_notice',
                title: 'Системное уведомление',
                body: 'Профилактические работы',
                action: null,
                actor: null,
            ),
        );

        $event = $this->stagedEvent();
        self::assertNull($event->action);
        self::assertNull($event->actor);
    }

    public function testSendRejectsUnregisteredTypeWithoutStaging(): void
    {
        try {
            $this->notifications()->send(
                recipientUserId: UserId::generate()->value(),
                content: new NotificationContentDto(
                    typeCode: 'chat.never_registered',
                    title: 'Новое сообщение',
                    body: 'Вам пришло сообщение',
                    action: null,
                    actor: null,
                ),
            );

            self::fail('Незарегистрированный вид уведомления должен быть отклонён.');
        } catch (NotificationTypeRegistryException) {
            self::assertSame([], $this->outboxStore->messages);
        }
    }

    private function registerType(string $code): void
    {
        $this->getContainer()->get(NotificationTypeRegistryContract::class)
            ->register(FixtureNotificationTypeDefinition::allChannels($code));
    }

    private function notifications(): NotificationContract
    {
        return $this->getContainer()->get(NotificationContract::class);
    }

    private function stagedEvent(): NotificationRequestedEvent
    {
        self::assertCount(1, $this->outboxStore->messages);
        $event = $this->outboxStore->messages[0];
        self::assertInstanceOf(NotificationRequestedEvent::class, $event);

        return $event;
    }
}
