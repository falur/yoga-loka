<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Application;

use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationCommand;
use App\Modules\Notifications\Application\Command\Realtime\PublishRealtimeNotification\PublishRealtimeNotificationHandler;
use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Notifications\Application\Contract\RealtimeNotificationPayload;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;
use Tests\Support\Media\PersistsMedia;

/**
 * Аватар автора хранится в снимке как id медиа, поэтому обработчик резолвит его в полное медиа
 * через модуль Media к моменту публикации и кладёт в realtime-payload той же формой, что и HTTP-инбокс.
 */
final class PublishRealtimeNotificationHandlerTest extends DatabaseTestCase
{
    use PersistsMedia;

    private string|null $capturedChannel = null;

    public function testPublishesToPersonalChannelResolvingAvatarMedia(): void
    {
        $media = $this->persistReadyPublicMedia();
        $this->persistThumbnailConversion($media);
        // Сбрасываем identity map, чтобы конверсии подгрузились eager при резолве (иначе ORM отдаст
        // кэшированное медиа без загруженной связи).
        $this->cleanOrmHeap();
        $actorId = UserId::generate();

        $payload = $this->publish(
            new NotificationActorDto(id: $actorId->value(), name: 'Иван', avatarMediaId: $media->id->value()),
        );

        self::assertSame('personal:#user_user-1', $this->capturedChannel);
        self::assertSame('chat.message_received', $payload->type);
        self::assertNotNull($payload->action);
        self::assertSame('chat', $payload->action->actionType);
        self::assertNotNull($payload->actor);
        self::assertSame($actorId->value(), $payload->actor->id);
        self::assertSame('Иван', $payload->actor->name);
        self::assertNotNull($payload->actor->avatar);
        self::assertSame($media->id->value(), $payload->actor->avatar->id);

        $encoded = $this->encode($payload);
        self::assertSame(self::STUBBED_MEDIA_URL, $encoded['actor']['avatar']['original']['url']);
        self::assertNull($encoded['actor']['avatar']['original']['expiresAt']);
        self::assertSame(MediaConversionKind::Image->value, $encoded['actor']['avatar']['conversions'][0]['kind']);
        self::assertSame(MediaImageConversionType::Thumbnail->value, $encoded['actor']['avatar']['conversions'][0]['type']);
        self::assertSame(self::STUBBED_MEDIA_URL, $encoded['actor']['avatar']['conversions'][0]['url']);
    }

    public function testResolvesAvatarToNullWhenMediaUnavailable(): void
    {
        $notReady = $this->persistNotReadyMedia();

        $payload = $this->publish(
            new NotificationActorDto(id: UserId::generate()->value(), name: 'Иван', avatarMediaId: $notReady->id->value()),
        );

        self::assertNotNull($payload->actor);
        self::assertNull($payload->actor->avatar);
    }

    public function testResolvesAvatarToNullWhenOriginalRemoved(): void
    {
        $removed = $this->persistReadyOriginalRemovedMedia();

        $payload = $this->publish(
            new NotificationActorDto(id: UserId::generate()->value(), name: 'Иван', avatarMediaId: $removed->id->value()),
        );

        self::assertNotNull($payload->actor);
        self::assertNull($payload->actor->avatar);
    }

    public function testResolvesAvatarToNullWhenActorHasNoAvatarMedia(): void
    {
        $payload = $this->publish(
            new NotificationActorDto(id: UserId::generate()->value(), name: 'Иван', avatarMediaId: null),
        );

        self::assertNotNull($payload->actor);
        self::assertNull($payload->actor->avatar);
    }

    public function testPublishesNullActorWhenNoActor(): void
    {
        $payload = $this->publish(null);

        self::assertNull($payload->actor);
    }

    private function publish(NotificationActorDto|null $actor): RealtimeNotificationPayload
    {
        $capturedPayload = null;
        $centrifugoService = $this->createMock(CentrifugoServiceContract::class);
        $centrifugoService->expects(self::once())
            ->method('publish')
            ->willReturnCallback(function (string $channel, RealtimeNotificationPayload $payload) use (&$capturedPayload): void {
                $this->capturedChannel = $channel;
                $capturedPayload = $payload;
            });

        new PublishRealtimeNotificationHandler(
            centrifugoService: $centrifugoService,
            media: $this->stubbedMediaContract(),
            logger: new NullLogger(),
        )->handle(new PublishRealtimeNotificationCommand(
            userId: 'user-1',
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: new NotificationActionDto(actionType: 'chat', actionId: '42'),
            actor: $actor,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));

        self::assertInstanceOf(RealtimeNotificationPayload::class, $capturedPayload);

        return $capturedPayload;
    }

    /**
     * @return array<string, mixed>
     */
    private function encode(RealtimeNotificationPayload $payload): array
    {
        $decoded = \json_decode(\json_encode($payload, \JSON_THROW_ON_ERROR), associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
