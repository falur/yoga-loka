<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Application;

use App\Modules\Notifications\Application\Command\DeviceToken\RegisterNotificationDeviceToken\RegisterNotificationDeviceTokenCommand;
use App\Modules\Notifications\Application\Command\DeviceToken\RegisterNotificationDeviceToken\RegisterNotificationDeviceTokenHandler;
use App\Modules\Notifications\Application\Command\DeviceToken\RemoveNotificationDeviceToken\RemoveNotificationDeviceTokenCommand;
use App\Modules\Notifications\Application\Command\DeviceToken\RemoveNotificationDeviceToken\RemoveNotificationDeviceTokenHandler;
use App\Modules\Notifications\Application\Command\Notification\MarkAllNotificationsRead\MarkAllNotificationsReadCommand;
use App\Modules\Notifications\Application\Command\Notification\MarkAllNotificationsRead\MarkAllNotificationsReadHandler;
use App\Modules\Notifications\Application\Command\Notification\MarkNotificationRead\MarkNotificationReadCommand;
use App\Modules\Notifications\Application\Command\Notification\MarkNotificationRead\MarkNotificationReadHandler;
use App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings\NotificationSettingUpdate;
use App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings\UpdateNotificationSettingsCommand;
use App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings\UpdateNotificationSettingsHandler;
use App\Modules\Notifications\Application\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Application\Dto\NotificationSettingView;
use App\Modules\Notifications\Application\Query\Notification\GetUnreadCount\GetUnreadCountHandler;
use App\Modules\Notifications\Application\Query\Notification\GetUnreadCount\GetUnreadCountQuery;
use App\Modules\Notifications\Application\Query\Notification\ListNotifications\ListNotificationsHandler;
use App\Modules\Notifications\Application\Query\Notification\ListNotifications\ListNotificationsQuery;
use App\Modules\Notifications\Application\Query\Notification\ListNotifications\ListNotificationsResult;
use App\Modules\Notifications\Application\Query\Setting\GetNotificationSettings\GetNotificationSettingsHandler;
use App\Modules\Notifications\Application\Query\Setting\GetNotificationSettings\GetNotificationSettingsQuery;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Repository\NotificationDeviceTokenRepository;
use App\Modules\Notifications\Repository\NotificationRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Tests\DatabaseTestCase;
use Tests\Support\Notifications\FixtureNotificationTypeDefinition;

final class NotificationUseCaseTest extends DatabaseTestCase
{
    private const string TYPE = 'chat.message_received';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(NotificationTypeRegistryContract::class)
            ->register(FixtureNotificationTypeDefinition::allChannels(self::TYPE));
    }

    public function testMarkNotificationReadMarksOneAndClearsUnread(): void
    {
        $userId = UserId::generate();
        $notification = $this->persistNotification($userId);

        $marked = $this->commandBus()->dispatch(
            command: new MarkNotificationReadCommand(userId: $userId->value(), notificationId: $notification->id->value()),
            handler: $this->getContainer()->get(MarkNotificationReadHandler::class)->handle(...),
        );

        self::assertInstanceOf(Notification::class, $marked);
        self::assertTrue($marked->isRead());
        self::assertSame(0, $this->notificationRepository()->countUnreadForRecipient($userId));
    }

    public function testMarkNotificationReadRejectsForeignRecipient(): void
    {
        $notification = $this->persistNotification(UserId::generate());

        $this->expectException(NotFoundException::class);

        $this->commandBus()->dispatch(
            command: new MarkNotificationReadCommand(
                userId: UserId::generate()->value(),
                notificationId: $notification->id->value(),
            ),
            handler: $this->getContainer()->get(MarkNotificationReadHandler::class)->handle(...),
        );
    }

    public function testMarkAllNotificationsReadClearsUnreadCount(): void
    {
        $userId = UserId::generate();
        $this->persistNotification($userId);
        $this->persistNotification($userId);

        $this->commandBus()->dispatch(
            command: new MarkAllNotificationsReadCommand(userId: $userId->value()),
            handler: $this->getContainer()->get(MarkAllNotificationsReadHandler::class)->handle(...),
        );

        self::assertSame(0, $this->notificationRepository()->countUnreadForRecipient($userId));
    }

    public function testUpdateNotificationSettingsCreatesThenUpdates(): void
    {
        $userId = UserId::generate();

        $this->updateSettings($userId, new NotificationSettingUpdate(type: self::TYPE, channel: 'push', enabled: false));
        self::assertFalse($this->pushView($userId)->enabled);

        $this->updateSettings($userId, new NotificationSettingUpdate(type: self::TYPE, channel: 'push', enabled: true));
        self::assertTrue($this->pushView($userId)->enabled);

        // Повторное выключение существующей строки — ветка disable().
        $this->updateSettings($userId, new NotificationSettingUpdate(type: self::TYPE, channel: 'push', enabled: false));
        self::assertFalse($this->pushView($userId)->enabled);
    }

    public function testUpdateNotificationSettingsRejectsUnknownType(): void
    {
        $this->expectException(ValidationException::class);

        $this->updateSettings(
            UserId::generate(),
            new NotificationSettingUpdate(type: 'ghost.event', channel: 'push', enabled: true),
        );
    }

    public function testUpdateNotificationSettingsRejectsUnknownChannel(): void
    {
        $this->expectException(ValidationException::class);

        $this->updateSettings(
            UserId::generate(),
            new NotificationSettingUpdate(type: self::TYPE, channel: 'sms', enabled: true),
        );
    }

    public function testRegisterDeviceTokenCreatesAndReassigns(): void
    {
        $firstOwner = UserId::generate();
        $secondOwner = UserId::generate();

        $this->registerToken($firstOwner, 'fcm-token', DevicePlatform::Ios);
        self::assertCount(1, $this->deviceTokenRepository()->findAllForUser($firstOwner));

        $this->registerToken($secondOwner, 'fcm-token', DevicePlatform::Android);
        self::assertCount(0, $this->deviceTokenRepository()->findAllForUser($firstOwner));
        self::assertCount(1, $this->deviceTokenRepository()->findAllForUser($secondOwner));
    }

    public function testRegisterDeviceTokenRejectsUnknownPlatform(): void
    {
        $this->expectException(ValidationException::class);

        $this->commandBus()->dispatch(
            command: new RegisterNotificationDeviceTokenCommand(
                userId: UserId::generate()->value(),
                token: 'fcm-token',
                platform: 'desktop',
            ),
            handler: $this->getContainer()->get(RegisterNotificationDeviceTokenHandler::class)->handle(...),
        );
    }

    public function testRemoveDeviceTokenDeletesIt(): void
    {
        $owner = UserId::generate();
        $this->registerToken($owner, 'fcm-token', DevicePlatform::Ios);

        $this->commandBus()->dispatch(
            command: new RemoveNotificationDeviceTokenCommand(userId: $owner->value(), token: 'fcm-token'),
            handler: $this->getContainer()->get(RemoveNotificationDeviceTokenHandler::class)->handle(...),
        );

        self::assertNull($this->deviceTokenRepository()->findByToken(DeviceToken::fromString('fcm-token')));
    }

    public function testRemoveDeviceTokenRejectsMissingToken(): void
    {
        $this->expectException(NotFoundException::class);

        $this->commandBus()->dispatch(
            command: new RemoveNotificationDeviceTokenCommand(userId: UserId::generate()->value(), token: 'missing-token'),
            handler: $this->getContainer()->get(RemoveNotificationDeviceTokenHandler::class)->handle(...),
        );
    }

    public function testListNotificationsPaginatesByCursor(): void
    {
        $userId = UserId::generate();
        $created = [];
        for ($index = 0; $index < 3; $index++) {
            $created[] = $this->persistNotification($userId);
        }
        $expectedDesc = $this->idsDesc($created);

        $firstPage = $this->listNotifications($userId, cursor: null, limit: 2);
        self::assertSame(\array_slice($expectedDesc, 0, 2), $this->idsOf($firstPage->notifications->all()));
        self::assertNotNull($firstPage->nextCursor);

        $secondPage = $this->listNotifications($userId, cursor: $firstPage->nextCursor, limit: 2);
        self::assertSame(\array_slice($expectedDesc, 2), $this->idsOf($secondPage->notifications->all()));
        self::assertNull($secondPage->nextCursor);
    }

    public function testGetUnreadCountCountsUnreadOnly(): void
    {
        $userId = UserId::generate();
        $this->persistNotification($userId);
        $read = $this->persistNotification($userId);
        $read->markRead(new \DateTimeImmutable('2026-06-13 11:00:00'));
        $this->entityManager()->persist($read);
        $this->entityManager()->run();

        $result = $this->queryBus()->dispatch(
            query: new GetUnreadCountQuery(userId: $userId->value()),
            handler: $this->getContainer()->get(GetUnreadCountHandler::class)->handle(...),
        );

        self::assertSame(1, $result->count);
    }

    public function testGetNotificationSettingsReturnsMatrixWithOverride(): void
    {
        $userId = UserId::generate();
        $this->updateSettings($userId, new NotificationSettingUpdate(type: self::TYPE, channel: 'realtime', enabled: false));

        $views = $this->getContainer()->get(GetNotificationSettingsHandler::class)->handle(
            new GetNotificationSettingsQuery(userId: $userId->value()),
        );

        // Матрица содержит все зарегистрированные виды; у фикстурного вида ровно три канала.
        $fixtureViews = $views->filter(
            static fn(NotificationSettingView $view): bool => $view->type->value() === self::TYPE,
        );
        self::assertCount(3, $fixtureViews);
        self::assertTrue($this->viewFor($views->all(), NotificationChannel::Push)->enabled);
        self::assertFalse($this->viewFor($views->all(), NotificationChannel::Realtime)->enabled);
        self::assertTrue($this->viewFor($views->all(), NotificationChannel::Realtime)->default);
    }

    private function updateSettings(UserId $userId, NotificationSettingUpdate $update): void
    {
        $this->commandBus()->dispatch(
            command: new UpdateNotificationSettingsCommand(userId: $userId->value(), updates: [$update]),
            handler: $this->getContainer()->get(UpdateNotificationSettingsHandler::class)->handle(...),
        );
    }

    private function pushView(UserId $userId): NotificationSettingView
    {
        $views = $this->getContainer()->get(GetNotificationSettingsHandler::class)->handle(
            new GetNotificationSettingsQuery(userId: $userId->value()),
        );

        return $this->viewFor($views->all(), NotificationChannel::Push);
    }

    /**
     * @param list<NotificationSettingView> $views
     */
    private function viewFor(array $views, NotificationChannel $channel): NotificationSettingView
    {
        foreach ($views as $view) {
            if ($view->channel === $channel && $view->type->value() === self::TYPE) {
                return $view;
            }
        }

        self::fail(\sprintf('Нет строки настроек для канала %s', $channel->value));
    }

    private function registerToken(UserId $userId, string $token, DevicePlatform $platform): void
    {
        $this->commandBus()->dispatch(
            command: new RegisterNotificationDeviceTokenCommand(
                userId: $userId->value(),
                token: $token,
                platform: $platform->value,
            ),
            handler: $this->getContainer()->get(RegisterNotificationDeviceTokenHandler::class)->handle(...),
        );
    }

    private function listNotifications(UserId $userId, string|null $cursor, int $limit): ListNotificationsResult
    {
        return $this->queryBus()->dispatch(
            query: new ListNotificationsQuery(userId: $userId->value(), cursor: $cursor, limit: $limit),
            handler: $this->getContainer()->get(ListNotificationsHandler::class)->handle(...),
        );
    }

    private function persistNotification(UserId $userId): Notification
    {
        $notification = Notification::create(
            outboxId: NotificationOutboxId::generate(),
            userId: $userId,
            type: NotificationTypeCode::fromString(self::TYPE),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: NotificationAction::none(),
            actor: NotificationActor::none(),
            triggeredAt: new \DateTimeImmutable('2026-06-13 10:00:00'),
        );
        $this->entityManager()->persist($notification);
        $this->entityManager()->run();

        return $notification;
    }

    /**
     * @param list<Notification> $notifications
     *
     * @return list<string>
     */
    private function idsDesc(array $notifications): array
    {
        $ids = $this->idsOf($notifications);
        \rsort($ids);

        return $ids;
    }

    /**
     * @param list<Notification> $notifications
     *
     * @return list<string>
     */
    private function idsOf(array $notifications): array
    {
        return \array_map(static fn(Notification $notification): string => $notification->id->value(), $notifications);
    }

    private function commandBus(): CommandBusInterface
    {
        return $this->getContainer()->get(CommandBusInterface::class);
    }

    private function queryBus(): QueryBusInterface
    {
        return $this->getContainer()->get(QueryBusInterface::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function notificationRepository(): NotificationRepository
    {
        return $this->getContainer()->get(NotificationRepository::class);
    }

    private function deviceTokenRepository(): NotificationDeviceTokenRepository
    {
        return $this->getContainer()->get(NotificationDeviceTokenRepository::class);
    }
}
