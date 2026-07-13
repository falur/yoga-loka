<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Application;

use App\Modules\Notifications\Application\View\NotificationViewAssembler;
use App\Modules\Notifications\Domain\Collection\NotificationCollection;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Tests\DatabaseTestCase;
use Tests\Support\Media\PersistsMedia;

/**
 * Ассемблер обогащает снимок автора актуальным аватаром (MediaView), собирая его на чтении из
 * сохранённого id медиа через модуль Media одним пакетным запросом.
 */
final class NotificationViewAssemblerTest extends DatabaseTestCase
{
    use PersistsMedia;

    public function testAssemblesViewsResolvingActorAvatars(): void
    {
        $readyMedia = $this->persistReadyPublicMedia();
        $notReadyMedia = $this->persistNotReadyMedia();
        $removedMedia = $this->persistReadyOriginalRemovedMedia();
        $actorId = UserId::generate();

        $views = $this->assembler()->fromNotifications(new NotificationCollection([
            $this->notification(NotificationActor::of(userId: $actorId, name: 'A', avatarMediaId: $readyMedia->id->value())),
            $this->notification(NotificationActor::of(userId: UserId::generate(), name: 'A2', avatarMediaId: $readyMedia->id->value())),
            $this->notification(NotificationActor::of(userId: UserId::generate(), name: 'B', avatarMediaId: $notReadyMedia->id->value())),
            $this->notification(NotificationActor::of(userId: UserId::generate(), name: 'C', avatarMediaId: $removedMedia->id->value())),
            $this->notification(NotificationActor::of(userId: UserId::generate(), name: 'D', avatarMediaId: null)),
            $this->notification(NotificationActor::none(), NotificationAction::linkTo('chat', '42')),
        ]));

        self::assertCount(6, $views);
        $list = $views->all();

        // 0: аватар резолвится в полный MediaView.
        self::assertNotNull($list[0]->actor);
        self::assertSame($actorId->value(), $list[0]->actor->id);
        self::assertNotNull($list[0]->actor->avatar);
        self::assertSame($readyMedia->id->value(), $list[0]->actor->avatar->id);
        self::assertNotNull($list[0]->actor->avatar->original);
        self::assertSame(self::STUBBED_MEDIA_URL, $list[0]->actor->avatar->original->url);
        self::assertNull($list[0]->action);

        // 1: тот же avatarMediaId (дедуп в один запрос) — тоже резолвится.
        self::assertNotNull($list[1]->actor);
        self::assertNotNull($list[1]->actor->avatar);

        // 2: медиа не финализировано -> аватара нет.
        self::assertNotNull($list[2]->actor);
        self::assertNull($list[2]->actor->avatar);

        // 3: оригинал удалён -> аватара нет (без конверсий удалённого оригинала).
        self::assertNotNull($list[3]->actor);
        self::assertNull($list[3]->actor->avatar);

        // 4: у автора нет медиа-аватара -> аватара нет.
        self::assertNotNull($list[4]->actor);
        self::assertNull($list[4]->actor->avatar);

        // 5: автора нет, но есть переход.
        self::assertNull($list[5]->actor);
        self::assertNotNull($list[5]->action);
        self::assertSame('chat', $list[5]->action->actionType);
        self::assertSame('42', $list[5]->action->actionId);
    }

    public function testAssemblesWithoutAvatarLookupWhenNoActorHasAvatar(): void
    {
        $views = $this->assembler()->fromNotifications(new NotificationCollection([
            $this->notification(NotificationActor::none()),
            $this->notification(NotificationActor::of(userId: UserId::generate(), name: 'D', avatarMediaId: null)),
        ]));

        self::assertCount(2, $views);
        self::assertNull($views->all()[0]->actor);
        self::assertNotNull($views->all()[1]->actor);
        self::assertNull($views->all()[1]->actor->avatar);
    }

    public function testAssemblesSingleNotificationView(): void
    {
        $media = $this->persistReadyPublicMedia();
        $actorId = UserId::generate();

        $view = $this->assembler()->fromNotification(
            $this->notification(NotificationActor::of(userId: $actorId, name: 'A', avatarMediaId: $media->id->value())),
        );

        self::assertNotNull($view->actor);
        self::assertSame($actorId->value(), $view->actor->id);
        self::assertNotNull($view->actor->avatar);
        self::assertSame($media->id->value(), $view->actor->avatar->id);
    }

    private function assembler(): NotificationViewAssembler
    {
        return new NotificationViewAssembler(
            queryBus: $this->getContainer()->get(QueryBusInterface::class),
            findMediaUrlsHandler: $this->stubbedFindMediaUrlsHandler(),
        );
    }

    private function notification(NotificationActor $actor, NotificationAction|null $action = null): Notification
    {
        return Notification::create(
            outboxId: NotificationOutboxId::generate(),
            userId: UserId::generate(),
            type: NotificationTypeCode::fromString('chat.message_received'),
            title: NotificationTitle::fromString('Заголовок'),
            body: NotificationBody::fromString('Тело'),
            action: $action ?? NotificationAction::none(),
            actor: $actor,
            triggeredAt: new \DateTimeImmutable('2026-06-13 10:00:00'),
        );
    }
}
