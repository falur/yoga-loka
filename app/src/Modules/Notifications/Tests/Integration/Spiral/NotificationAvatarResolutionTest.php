<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Spiral;

use App\Modules\Notifications\Application\Command\Notification\MarkNotificationRead\MarkNotificationReadCommand;
use App\Modules\Notifications\Application\Command\Notification\MarkNotificationRead\MarkNotificationReadHandler;
use App\Modules\Notifications\Application\Query\Notification\ListNotifications\ListNotificationsHandler;
use App\Modules\Notifications\Application\Query\Notification\ListNotifications\ListNotificationsQuery;
use App\Modules\Notifications\Application\Query\Notification\ListNotifications\ListNotificationsResult;
use App\Modules\Notifications\Application\Result\NotificationResult;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;
use App\Modules\Notifications\Tests\Support\PersistsMedia;

/**
 * Снимок автора обогащается актуальным аватаром (публичное медиа), собранным на чтении из
 * сохранённого id медиа через публичный контракт Media: пакетно для страницы инбокса
 * (ListNotificationsHandler) и одним вызовом для одного уведомления (MarkNotificationReadHandler).
 */
final class NotificationAvatarResolutionTest extends DatabaseTestCase
{
    use PersistsMedia;

    public function testListResolvesActorAvatarsBatchedByPage(): void
    {
        $readyMedia = $this->persistReadyPublicMedia();
        $notReadyMedia = $this->persistNotReadyMedia();
        $removedMedia = $this->persistReadyOriginalRemovedMedia();
        $recipientId = UserId::generate();
        $actorId = UserId::generate();

        $this->persistNotification($recipientId, NotificationActor::of(userId: $actorId, name: 'A', avatarMediaId: $readyMedia->id->value()));
        $this->persistNotification($recipientId, NotificationActor::of(userId: UserId::generate(), name: 'A2', avatarMediaId: $readyMedia->id->value()));
        $this->persistNotification($recipientId, NotificationActor::of(userId: UserId::generate(), name: 'B', avatarMediaId: $notReadyMedia->id->value()));
        $this->persistNotification($recipientId, NotificationActor::of(userId: UserId::generate(), name: 'C', avatarMediaId: $removedMedia->id->value()));
        $this->persistNotification($recipientId, NotificationActor::of(userId: UserId::generate(), name: 'D', avatarMediaId: null));
        $this->persistNotification($recipientId, NotificationActor::none(), NotificationAction::linkTo('chat', '42'));

        $result = $this->listNotifications($recipientId);

        self::assertCount(6, $result->notifications);

        // Аватар резолвится в полное публичное медиа.
        $a = $this->resultForActor($result, 'A');
        self::assertNotNull($a->actor);
        self::assertSame($actorId->value(), $a->actor->id);
        self::assertNotNull($a->actor->avatar);
        self::assertSame($readyMedia->id->value(), $a->actor->avatar->id);
        self::assertNotNull($a->actor->avatar->original);
        self::assertSame(self::STUBBED_MEDIA_URL, $a->actor->avatar->original->url);
        self::assertNull($a->action);

        // Тот же avatarMediaId (дедуп в один пакетный запрос) — тоже резолвится.
        $a2 = $this->resultForActor($result, 'A2');
        self::assertNotNull($a2->actor);
        self::assertNotNull($a2->actor->avatar);

        // Медиа не финализировано -> аватара нет.
        $b = $this->resultForActor($result, 'B');
        self::assertNotNull($b->actor);
        self::assertNull($b->actor->avatar);

        // Оригинал удалён -> аватара нет (без конверсий удалённого оригинала).
        $c = $this->resultForActor($result, 'C');
        self::assertNotNull($c->actor);
        self::assertNull($c->actor->avatar);

        // У автора нет медиа-аватара -> аватара нет.
        $d = $this->resultForActor($result, 'D');
        self::assertNotNull($d->actor);
        self::assertNull($d->actor->avatar);

        // Автора нет, но есть переход.
        $withoutActor = $this->resultWithoutActor($result);
        self::assertNull($withoutActor->actor);
        self::assertNotNull($withoutActor->action);
        self::assertSame('chat', $withoutActor->action->actionType);
        self::assertSame('42', $withoutActor->action->actionId);
    }

    public function testListSkipsAvatarLookupWhenNoActorHasAvatar(): void
    {
        $recipientId = UserId::generate();
        $this->persistNotification($recipientId, NotificationActor::none());
        $this->persistNotification($recipientId, NotificationActor::of(userId: UserId::generate(), name: 'D', avatarMediaId: null));

        $result = $this->listNotifications($recipientId);

        self::assertCount(2, $result->notifications);
        self::assertNull($this->resultWithoutActor($result)->actor);

        $withActor = $this->resultForActor($result, 'D');
        self::assertNotNull($withActor->actor);
        self::assertNull($withActor->actor->avatar);
    }

    public function testMarkNotificationReadResolvesSingleActorAvatar(): void
    {
        $media = $this->persistReadyPublicMedia();
        $recipientId = UserId::generate();
        $actorId = UserId::generate();

        $notification = $this->persistNotification(
            $recipientId,
            NotificationActor::of(userId: $actorId, name: 'A', avatarMediaId: $media->id->value()),
        );

        $marked = $this->markNotificationReadHandler()->handle(new MarkNotificationReadCommand(
            userId: $recipientId->value(),
            notificationId: $notification->id->value(),
        ));

        self::assertNotNull($marked->actor);
        self::assertSame($actorId->value(), $marked->actor->id);
        self::assertNotNull($marked->actor->avatar);
        self::assertSame($media->id->value(), $marked->actor->avatar->id);
    }

    /**
     * У автора уведомления может не быть аватара: тогда отметка о прочтении возвращает автора без
     * ссылки и к Media не обращается — форма ответа та же, что у списка инбокса.
     */
    public function testMarkNotificationReadSkipsAvatarLookupWhenActorHasNoAvatar(): void
    {
        $recipientId = UserId::generate();
        $actorId = UserId::generate();

        $notification = $this->persistNotification(
            $recipientId,
            NotificationActor::of(userId: $actorId, name: 'B', avatarMediaId: null),
        );

        $marked = $this->markNotificationReadHandler()->handle(new MarkNotificationReadCommand(
            userId: $recipientId->value(),
            notificationId: $notification->id->value(),
        ));

        self::assertNotNull($marked->actor);
        self::assertSame($actorId->value(), $marked->actor->id);
        self::assertNull($marked->actor->avatar);
    }

    private function resultForActor(ListNotificationsResult $result, string $name): NotificationResult
    {
        foreach ($result->notifications as $notification) {
            if ($notification->actor?->name === $name) {
                return $notification;
            }
        }

        self::fail(\sprintf('Нет уведомления с автором %s', $name));
    }

    private function resultWithoutActor(ListNotificationsResult $result): NotificationResult
    {
        foreach ($result->notifications as $notification) {
            if ($notification->actor === null) {
                return $notification;
            }
        }

        self::fail('Нет уведомления без автора');
    }

    private function listNotifications(UserId $recipientId): ListNotificationsResult
    {
        return $this->listNotificationsHandler()->handle(
            new ListNotificationsQuery(userId: $recipientId->value(), cursor: null, limit: 10),
        );
    }

    private function persistNotification(UserId $recipientId, NotificationActor $actor, NotificationAction|null $action = null): Notification
    {
        $notification = Notification::create(
            outboxId: NotificationOutboxId::generate(),
            userId: $recipientId,
            type: NotificationTypeCode::fromString('chat.message_received'),
            title: NotificationTitle::fromString('Заголовок'),
            body: NotificationBody::fromString('Тело'),
            action: $action ?? NotificationAction::none(),
            actor: $actor,
            triggeredAt: new \DateTimeImmutable('2026-06-13 10:00:00'),
        );
        $this->notificationRepository()->save($notification);

        return $notification;
    }

    private function listNotificationsHandler(): ListNotificationsHandler
    {
        return new ListNotificationsHandler(
            notificationRepository: $this->notificationRepository(),
            media: $this->stubbedMediaContract(),
        );
    }

    private function markNotificationReadHandler(): MarkNotificationReadHandler
    {
        return new MarkNotificationReadHandler(
            notificationRepository: $this->notificationRepository(),
            media: $this->stubbedMediaContract(),
            logger: new NullLogger(),
        );
    }

    private function notificationRepository(): NotificationRepository
    {
        return $this->getContainer()->get(NotificationRepository::class);
    }
}
