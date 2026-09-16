<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Http;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Domain\Repository\NotificationDeviceTokenRepository;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Spiral\Testing\Http\TestResponse;
use Tests\DatabaseTestCase;
use Tests\Support\Media\PersistsMedia;
use Tests\Support\Notifications\FixtureNotificationTypeDefinition;

final class NotificationHttpTest extends DatabaseTestCase
{
    use PersistsMedia;

    private const string TYPE = 'chat.message_received';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->getContainer()->get(NotificationTypeRegistryContract::class)
            ->register(FixtureNotificationTypeDefinition::allChannels(self::TYPE));

        // URL медиа-аватара автора собирается на чтении: подменяем файловый сервис, чтобы public URL был
        // предсказуем (STUBBED_MEDIA_URL) и без обращения к S3.
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn(self::STUBBED_MEDIA_URL);
        $this->getContainer()->bindSingleton(MediaFileServiceContract::class, $fileService);
    }

    public function testListNotificationsReturnsPaginatedResources(): void
    {
        $userId = UserId::generate();
        $this->persistNotification($userId);
        $this->persistNotification($userId);

        $response = $this->fakeHttp()->getWithAttributes(
            '/api/v1/notifications?limit=10',
            ['authUserId' => $userId->value()],
        );

        $response->assertOk();
        $body = $this->json($response);
        self::assertCount(2, $body['data']);
        self::assertArrayHasKey('meta', $body);
    }

    public function testListNotificationsExposesDeepLinkAction(): void
    {
        $userId = UserId::generate();
        $this->persistNotification($userId, NotificationAction::linkTo('chat', '42'));

        $response = $this->fakeHttp()->getWithAttributes(
            '/api/v1/notifications',
            ['authUserId' => $userId->value()],
        );

        $response->assertOk();
        $action = $this->json($response)['data'][0]['action'];
        self::assertSame('chat', $action['actionType']);
        self::assertSame('42', $action['actionId']);
    }

    public function testListNotificationsExposesActorSnapshotWithAvatarMedia(): void
    {
        $userId = UserId::generate();
        $actorId = UserId::generate();
        $media = $this->persistReadyPublicMedia();
        $this->persistThumbnailConversion($media);
        $this->persistNotification(
            $userId,
            actor: NotificationActor::of(userId: $actorId, name: 'Иван', avatarMediaId: $media->id->value()),
        );
        $this->cleanOrmHeap();

        $response = $this->fakeHttp()->getWithAttributes(
            '/api/v1/notifications',
            ['authUserId' => $userId->value()],
        );

        $response->assertOk();
        $actor = $this->json($response)['data'][0]['actor'];
        self::assertSame($actorId->value(), $actor['id']);
        self::assertSame('Иван', $actor['name']);
        // Аватар автора — медиа целиком (id + позиция + оригинал + конверсии), а не строка-ссылка.
        self::assertSame($media->id->value(), $actor['avatar']['id']);
        // Позиция принадлежит записи, а не медиа, поэтому у аватара её нет.
        self::assertNull($actor['avatar']['position']);
        self::assertSame(self::STUBBED_MEDIA_URL, $actor['avatar']['original']['url']);
        self::assertNull($actor['avatar']['original']['expiresAt']);

        // Конверсии аватара отдаются тем же составом полей, что и у вложений записи: вид, профиль,
        // ссылка и срок её действия.
        self::assertCount(1, $actor['avatar']['conversions']);
        $conversion = $actor['avatar']['conversions'][0];
        self::assertSame('image', $conversion['kind']);
        self::assertSame('thumbnail', $conversion['type']);
        self::assertSame(self::STUBBED_MEDIA_URL, $conversion['url']);
        self::assertNull($conversion['expiresAt']);
    }

    public function testListNotificationsReturnsNullAvatarWhenActorHasNoAvatarMedia(): void
    {
        $userId = UserId::generate();
        $actorId = UserId::generate();
        $this->persistNotification(
            $userId,
            actor: NotificationActor::of(userId: $actorId, name: 'Иван', avatarMediaId: null),
        );

        $response = $this->fakeHttp()->getWithAttributes(
            '/api/v1/notifications',
            ['authUserId' => $userId->value()],
        );

        $response->assertOk();
        $actor = $this->json($response)['data'][0]['actor'];
        self::assertSame($actorId->value(), $actor['id']);
        // Автор есть, а аватара нет: сервер не выдумывает заглушку, дефолт ставит клиент.
        self::assertNull($actor['avatar']);
    }

    public function testListNotificationsReturnsNullActorWhenNoActor(): void
    {
        $userId = UserId::generate();
        $this->persistNotification($userId);

        $response = $this->fakeHttp()->getWithAttributes(
            '/api/v1/notifications',
            ['authUserId' => $userId->value()],
        );

        $response->assertOk();
        self::assertNull($this->json($response)['data'][0]['actor']);
    }

    public function testListNotificationsReturns422ForInvalidCursor(): void
    {
        $response = $this->fakeHttp()->getWithAttributes(
            '/api/v1/notifications?cursor=not-a-uuid',
            ['authUserId' => UserId::generate()->value()],
        );

        $response->assertUnprocessable();
    }

    public function testUnreadCountReturnsCount(): void
    {
        $userId = UserId::generate();
        $this->persistNotification($userId);

        $response = $this->fakeHttp()->getWithAttributes(
            '/api/v1/notifications/unread-count',
            ['authUserId' => $userId->value()],
        );

        $response->assertOk();
        self::assertSame(1, $this->json($response)['data']['count']);
    }

    public function testMarkNotificationRead(): void
    {
        $userId = UserId::generate();
        $notification = $this->persistNotification($userId);

        $response = $this->authedSend('POST', \sprintf('/api/v1/notifications/%s/read', $notification->id->value()), $userId);

        $response->assertOk();
        self::assertTrue($this->json($response)['data']['read']);
    }

    public function testMarkNotificationReadReturns404ForForeignNotification(): void
    {
        $notification = $this->persistNotification(UserId::generate());

        $response = $this->authedSend(
            'POST',
            \sprintf('/api/v1/notifications/%s/read', $notification->id->value()),
            UserId::generate(),
        );

        $response->assertNotFound();
    }

    public function testMarkNotificationReadReturns422ForInvalidId(): void
    {
        $response = $this->authedSend('POST', '/api/v1/notifications/not-a-uuid/read', UserId::generate());

        $response->assertUnprocessable();
    }

    public function testMarkAllNotificationsReadReturnsZeroCount(): void
    {
        $userId = UserId::generate();
        $this->persistNotification($userId);
        $this->persistNotification($userId);

        $response = $this->authedSend('POST', '/api/v1/notifications/read-all', $userId);

        $response->assertOk();
        self::assertSame(0, $this->json($response)['data']['count']);
        self::assertSame(0, $this->notificationRepository()->countUnreadForRecipient($userId));
    }

    public function testGetNotificationSettingsReturnsMatrix(): void
    {
        $response = $this->fakeHttp()->getWithAttributes(
            '/api/v1/notification-settings',
            ['authUserId' => UserId::generate()->value()],
        );

        $response->assertOk();
        // Матрица содержит все зарегистрированные виды; у фикстурного вида ровно три канала.
        $fixtureRows = \array_filter(
            $this->json($response)['data'],
            static fn(array $row): bool => $row['type'] === self::TYPE,
        );
        self::assertCount(3, $fixtureRows);
    }

    public function testUpdateNotificationSettings(): void
    {
        $userId = UserId::generate();

        $response = $this->authedSend('PUT', '/api/v1/notification-settings', $userId, [
            'settings' => [
                ['type' => self::TYPE, 'channel' => 'push', 'enabled' => false],
            ],
        ]);

        $response->assertOk();
        $pushRow = $this->settingRow($this->json($response)['data'], 'push');
        self::assertFalse($pushRow['enabled']);
    }

    public function testUpdateNotificationSettingsReturns422ForUnknownType(): void
    {
        $response = $this->authedSend('PUT', '/api/v1/notification-settings', UserId::generate(), [
            'settings' => [
                ['type' => 'ghost.event', 'channel' => 'push', 'enabled' => true],
            ],
        ]);

        $response->assertUnprocessable();
    }

    public function testUpdateNotificationSettingsReturns422ForUnknownChannel(): void
    {
        $response = $this->authedSend('PUT', '/api/v1/notification-settings', UserId::generate(), [
            'settings' => [
                ['type' => self::TYPE, 'channel' => 'sms', 'enabled' => true],
            ],
        ]);

        $response->assertUnprocessable();
    }

    public function testUpdateNotificationSettingsReturns422WhenListExceedsLimit(): void
    {
        $settings = [];
        for ($i = 0; $i <= 300; $i++) {
            $settings[] = ['type' => self::TYPE, 'channel' => 'push', 'enabled' => true];
        }

        $response = $this->authedSend('PUT', '/api/v1/notification-settings', UserId::generate(), [
            'settings' => $settings,
        ]);

        $response->assertUnprocessable();
    }

    public function testRegisterDeviceToken(): void
    {
        $response = $this->authedSend('POST', '/api/v1/notification-device-tokens', UserId::generate(), [
            'token' => 'fcm-token',
            'platform' => 'ios',
        ]);

        $response->assertOk();
        self::assertSame('ios', $this->json($response)['data']['platform']);
    }

    public function testRegisterDeviceTokenReturns422ForInvalidPlatform(): void
    {
        $response = $this->authedSend('POST', '/api/v1/notification-device-tokens', UserId::generate(), [
            'token' => 'fcm-token',
            'platform' => 'desktop',
        ]);

        $response->assertUnprocessable();
    }

    public function testRegisterDeviceTokenReturns422WhenTokenMissing(): void
    {
        $response = $this->authedSend('POST', '/api/v1/notification-device-tokens', UserId::generate(), [
            'platform' => 'ios',
        ]);

        $response->assertUnprocessable();
    }

    public function testRemoveDeviceToken(): void
    {
        $owner = UserId::generate();
        $this->persistToken($owner, 'fcm-token');

        $response = $this->authedSend('DELETE', '/api/v1/notification-device-tokens', $owner, [
            'token' => 'fcm-token',
        ]);

        $response->assertOk();
        self::assertNull($this->deviceTokenRepository()->findByToken(DeviceToken::fromString('fcm-token')));
    }

    public function testRemoveDeviceTokenReturns404WhenMissing(): void
    {
        $response = $this->authedSend('DELETE', '/api/v1/notification-device-tokens', UserId::generate(), [
            'token' => 'missing-token',
        ]);

        $response->assertNotFound();
    }

    public function testRemoveDeviceTokenReturns404ForForeignUserAndKeepsToken(): void
    {
        $owner = UserId::generate();
        $this->persistToken($owner, 'fcm-token');

        $response = $this->authedSend('DELETE', '/api/v1/notification-device-tokens', UserId::generate(), [
            'token' => 'fcm-token',
        ]);

        $response->assertNotFound();
        self::assertNotNull($this->deviceTokenRepository()->findByToken(DeviceToken::fromString('fcm-token')));
    }

    /**
     * Все маршруты Notifications требуют действующую сессию. Это согласованное изменение внешнего
     * поведения: до объявления доступа маршрут без сессии падал на обязательном `authUserId`
     * в Filter, теперь отказывает общий механизм доступа тем же ответом, что и остальные
     * защищённые маршруты — переводом ключа `app.auth.unauthenticated` и кодом 401.
     */
    #[DataProvider('protectedRouteProvider')]
    public function testRouteWithoutSessionReturnsTranslatedUnauthenticated(string $method, string $uri): void
    {
        $request = $this->fakeHttp()
            ->withHeader('Accept-Language', 'en')
            ->createJsonRequest($uri, $method, [], [], []);

        $response = $this->fakeHttp()->handleRequest($request);

        $response->assertUnauthorized();
        $response->assertBodySame('{"message":"Authentication required.","code":401}');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedRouteProvider(): array
    {
        return [
            'список уведомлений' => ['GET', '/api/v1/notifications'],
            'счётчик непрочитанных' => ['GET', '/api/v1/notifications/unread-count'],
            'отметка уведомления прочитанным' => [
                'POST',
                '/api/v1/notifications/01996e0f-6c4a-7a6f-9f0e-2f5f9a3c1d20/read',
            ],
            'отметка всех прочитанными' => ['POST', '/api/v1/notifications/read-all'],
            'чтение настроек' => ['GET', '/api/v1/notification-settings'],
            'изменение настроек' => ['PUT', '/api/v1/notification-settings'],
            'регистрация токена устройства' => ['POST', '/api/v1/notification-device-tokens'],
            'удаление токена устройства' => ['DELETE', '/api/v1/notification-device-tokens'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function authedSend(string $method, string $uri, UserId $userId, array $data = []): TestResponse
    {
        $request = $this->fakeHttp()
            ->createJsonRequest($uri, $method, $data, [], [])
            ->withAttribute('authUserId', $userId->value());

        return $this->fakeHttp()->handleRequest($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(TestResponse $response): array
    {
        $decoded = \json_decode(
            json: (string) $response->getOriginalResponse()->getBody(),
            associative: true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return array<string, mixed>
     */
    private function settingRow(array $rows, string $channel): array
    {
        foreach ($rows as $row) {
            if ($row['channel'] === $channel && $row['type'] === self::TYPE) {
                return $row;
            }
        }

        self::fail(\sprintf('Нет строки настроек для канала %s', $channel));
    }

    private function persistNotification(
        UserId $userId,
        NotificationAction|null $action = null,
        NotificationActor|null $actor = null,
    ): Notification {
        $notification = Notification::create(
            outboxId: NotificationOutboxId::generate(),
            userId: $userId,
            type: NotificationTypeCode::fromString(self::TYPE),
            title: NotificationTitle::fromString('Новое сообщение'),
            body: NotificationBody::fromString('Вам пришло сообщение'),
            action: $action ?? NotificationAction::none(),
            actor: $actor ?? NotificationActor::none(),
            triggeredAt: new \DateTimeImmutable('2026-06-13 10:00:00'),
        );
        $this->persist($notification);

        return $notification;
    }

    private function persistToken(UserId $userId, string $token): void
    {
        $this->persist(NotificationDeviceToken::create(
            userId: $userId,
            token: DeviceToken::fromString($token),
            platform: DevicePlatform::Ios,
        ));
    }

    private function persist(object $entity): void
    {
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->run();
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
